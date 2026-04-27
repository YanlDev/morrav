<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Family;
use App\Models\Product;
use App\Models\Sku;
use App\Models\SkuAttribute;
use App\Models\Subfamily;
use Illuminate\Database\Seeder;

class DemoProductSeeder extends Seeder
{
    public function run(): void
    {
        $demo = [
            [
                'family' => 'OFICINA',
                'subfamily' => 'SILLAS',
                'product' => [
                    'name' => 'Silla gerencial Milano',
                    'description' => 'Silla gerencial con respaldo alto y apoyabrazos.',
                    'brand' => 'ErgoMax',
                ],
                'skus' => [
                    [
                        'variant_name' => 'Negro / Cuero',
                        'sale_price' => 890.00,
                        'purchase_price' => 520.00,
                        'attrs' => ['color' => 'negro', 'material' => 'cuero', 'modelo' => 'Milano'],
                    ],
                    [
                        'variant_name' => 'Gris / Tela',
                        'sale_price' => 790.00,
                        'purchase_price' => 460.00,
                        'attrs' => ['color' => 'gris', 'material' => 'tela', 'modelo' => 'Milano'],
                    ],
                ],
            ],
            [
                'family' => 'OFICINA',
                'subfamily' => 'ESCRITORIOS',
                'product' => [
                    'name' => 'Escritorio gerencial 1.80m',
                    'description' => 'Escritorio gerencial en melamina con cajonería integrada.',
                    'brand' => 'WoodLine',
                ],
                'skus' => [
                    [
                        'variant_name' => 'Natural',
                        'sale_price' => 1850.00,
                        'purchase_price' => 1050.00,
                        'attrs' => ['color' => 'natural', 'material' => 'melamina', 'medida' => '1.80m'],
                    ],
                ],
            ],
            [
                'family' => 'HOGAR',
                'subfamily' => 'SOFAS',
                'product' => [
                    'name' => 'Sofá 3 cuerpos Florencia',
                    'description' => 'Sofá de sala de 3 cuerpos con estructura reforzada.',
                    'brand' => 'Florencia',
                ],
                'skus' => [
                    [
                        'variant_name' => 'Gris / Tela',
                        'sale_price' => 2890.00,
                        'purchase_price' => 1700.00,
                        'attrs' => ['color' => 'gris', 'material' => 'tela', 'medida' => '2.00m'],
                    ],
                ],
            ],
        ];

        $lastProductId = Product::withTrashed()->max('id') ?? 0;
        $lastSkuId = Sku::withTrashed()->max('id') ?? 0;

        foreach ($demo as $item) {
            $family = Family::where('code', $item['family'])->first();

            if (! $family) {
                continue;
            }

            $subfamily = Subfamily::where('family_id', $family->id)
                ->where('code', $item['subfamily'])
                ->first();

            $lastProductId++;

            $product = Product::firstOrCreate(
                ['name' => $item['product']['name'], 'family_id' => $family->id],
                [
                    'internal_code' => 'PROD-'.str_pad((string) $lastProductId, 6, '0', STR_PAD_LEFT),
                    'description' => $item['product']['description'],
                    'subfamily_id' => $subfamily?->id,
                    'brand' => $item['product']['brand'],
                    'unit_of_measure' => 'unit',
                    'status' => 'active',
                    'fingerprint' => hash('sha256', mb_strtolower($item['product']['name']).'|'.$family->id),
                ],
            );

            foreach ($item['skus'] as $skuData) {
                $lastSkuId++;

                $sku = Sku::firstOrCreate(
                    ['product_id' => $product->id, 'variant_name' => $skuData['variant_name']],
                    [
                        'internal_code' => 'SKU-'.str_pad((string) $lastSkuId, 6, '0', STR_PAD_LEFT),
                        'sale_price' => $skuData['sale_price'],
                        'purchase_price' => $skuData['purchase_price'],
                        'status' => 'active',
                        'fingerprint' => hash('sha256', $product->id.'|'.json_encode($skuData['attrs'])),
                    ],
                );

                foreach ($skuData['attrs'] as $attrCode => $value) {
                    $attribute = Attribute::where('code', $attrCode)->first();

                    if (! $attribute) {
                        continue;
                    }

                    SkuAttribute::updateOrCreate(
                        ['sku_id' => $sku->id, 'attribute_id' => $attribute->id],
                        ['value' => $value],
                    );
                }
            }
        }
    }
}
