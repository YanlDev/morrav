<?php

namespace App\Services\Catalog;

use App\Models\Attribute;
use App\Models\Family;
use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Product;
use App\Models\Sku;
use App\Models\SkuAttribute;
use App\Models\Subfamily;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Crea un producto, sus SKUs y opcionalmente las líneas de un movimiento
 * asociado, todo en una transacción única. Cuando se provee movimiento,
 * agrega una línea por cada SKU con la cantidad indicada.
 */
class ProductCreator
{
    /**
     * @param  array{name: string, family_id: int, subfamily_id?: int|null, brand?: string|null, description?: string|null, unit_of_measure?: string|null, status?: string|null, created_by?: int|null}  $productData
     * @param  array<int, array{variant_name?: string|null, sale_price?: float|null, purchase_price?: float|null, status?: string|null, attributes?: array<string, string|null>, quantity?: float|null}>  $skusData
     * @return array{product: Product, skus: Collection<int, Sku>}
     */
    public function create(array $productData, array $skusData, ?Movement $movement = null): array
    {
        if ($skusData === []) {
            throw new \InvalidArgumentException('Al menos un SKU debe ser provisto.');
        }

        return DB::transaction(function () use ($productData, $skusData, $movement) {
            $family = Family::findOrFail($productData['family_id']);
            $subfamilyId = $this->resolveSubfamilyId($family, $productData['subfamily_id'] ?? null);

            $lastProductId = Product::withTrashed()->max('id') ?? 0;
            $nextProductId = $lastProductId + 1;

            $product = Product::create([
                'internal_code' => $this->productCode($nextProductId),
                'name' => $productData['name'],
                'description' => $productData['description'] ?? null,
                'family_id' => $family->id,
                'subfamily_id' => $subfamilyId,
                'brand' => $productData['brand'] ?? null,
                'unit_of_measure' => $productData['unit_of_measure'] ?? 'unit',
                'status' => $productData['status'] ?? 'active',
                'fingerprint' => hash('sha256', mb_strtolower(trim($productData['name'])).'|'.$family->id),
                'created_by' => $productData['created_by'] ?? null,
            ]);

            $skus = new Collection;
            $lastSkuId = Sku::withTrashed()->max('id') ?? 0;

            foreach ($skusData as $skuData) {
                $lastSkuId++;
                $sku = $this->createSku($product, $lastSkuId, $skuData);
                $skus->push($sku);

                if ($movement !== null && isset($skuData['quantity']) && $skuData['quantity'] > 0) {
                    $this->createMovementLine($movement, $sku, (float) $skuData['quantity']);
                }
            }

            return ['product' => $product, 'skus' => $skus];
        });
    }

    /**
     * @param  array{variant_name?: string|null, sale_price?: float|null, purchase_price?: float|null, status?: string|null, attributes?: array<string, string|null>}  $skuData
     */
    private function createSku(Product $product, int $nextSkuId, array $skuData): Sku
    {
        $attributes = $skuData['attributes'] ?? [];
        $fingerprint = hash('sha256', $product->id.'|'.json_encode($attributes));

        $sku = Sku::create([
            'product_id' => $product->id,
            'internal_code' => $this->skuCode($nextSkuId),
            'variant_name' => $skuData['variant_name'] ?? null,
            'sale_price' => $skuData['sale_price'] ?? null,
            'purchase_price' => $skuData['purchase_price'] ?? null,
            'status' => $skuData['status'] ?? 'active',
            'fingerprint' => $fingerprint,
        ]);

        foreach ($attributes as $attrCode => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $attribute = Attribute::where('code', $attrCode)->first();

            if (! $attribute) {
                continue;
            }

            SkuAttribute::create([
                'sku_id' => $sku->id,
                'attribute_id' => $attribute->id,
                'value' => (string) $value,
            ]);
        }

        return $sku;
    }

    private function createMovementLine(Movement $movement, Sku $sku, float $quantity): void
    {
        $warehouseId = match ($movement->type) {
            'inbound', 'initial_load' => $movement->destination_warehouse_id,
            'outbound', 'adjustment' => $movement->origin_warehouse_id,
            default => null,
        };

        if ($warehouseId === null) {
            return;
        }

        MovementLine::create([
            'movement_id' => $movement->id,
            'sku_id' => $sku->id,
            'warehouse_id' => $warehouseId,
            'direction' => 'in',
            'quantity' => $quantity,
        ]);
    }

    private function resolveSubfamilyId(Family $family, ?int $subfamilyId): ?int
    {
        if ($subfamilyId !== null) {
            $belongs = Subfamily::where('id', $subfamilyId)
                ->where('family_id', $family->id)
                ->exists();

            if ($belongs) {
                return $subfamilyId;
            }
        }

        $pending = Subfamily::where('family_id', $family->id)
            ->where('code', 'PENDIENTE')
            ->first();

        return $pending?->id;
    }

    private function productCode(int $id): string
    {
        return 'PROD-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    private function skuCode(int $id): string
    {
        return 'SKU-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}
