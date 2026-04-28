<?php

namespace App\Http\Resources;

use App\Models\Sku;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @mixin Sku
 */
class SkuResource extends JsonResource
{
    /**
     * Filas de stock por almacén, opcionales. Cada fila tiene la forma
     * `['warehouse' => Warehouse, 'qty' => float]`. Se inyectan vía `withStock()`.
     */
    private ?Collection $stockRows = null;

    /**
     * @param  array<int, array{warehouse: Warehouse, qty: float}>  $rows
     */
    public function withStock(array $rows): static
    {
        $this->stockRows = collect($rows);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'internal_code' => $this->internal_code,
            'variant_name' => $this->variant_name,
            'status' => $this->status,
            'photo' => $this->photo,
            'sale_price' => $this->sale_price !== null ? (float) $this->sale_price : null,
            'purchase_price' => $this->when(
                $user?->canSeeFinancials() ?? false,
                fn () => $this->purchase_price !== null ? (float) $this->purchase_price : null,
            ),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'internal_code' => $this->product->internal_code,
            ]),
            'stock_by_warehouse' => $this->when(
                $this->stockRows !== null,
                fn () => StockResource::collection($this->stockRows),
            ),
        ];
    }
}
