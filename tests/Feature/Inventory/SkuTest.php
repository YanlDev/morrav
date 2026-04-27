<?php

use App\Models\Attribute;
use App\Models\Product;
use App\Models\Sku;
use App\Models\SkuAttribute;
use App\Models\SkuExternalCode;
use Illuminate\Database\QueryException;

it('belongs to a product', function () {
    $product = Product::factory()->create();
    $sku = Sku::factory()->create(['product_id' => $product->id]);

    expect($sku->product->id)->toBe($product->id);
});

it('has many external codes', function () {
    $sku = Sku::factory()->create();
    SkuExternalCode::factory()->count(2)->create(['sku_id' => $sku->id]);

    expect($sku->externalCodes)->toHaveCount(2);
});

it('has many attribute values', function () {
    $sku = Sku::factory()->create();
    $color = Attribute::factory()->create(['code' => 'color']);
    $material = Attribute::factory()->create(['code' => 'material']);

    SkuAttribute::factory()->create(['sku_id' => $sku->id, 'attribute_id' => $color->id, 'value' => 'black']);
    SkuAttribute::factory()->create(['sku_id' => $sku->id, 'attribute_id' => $material->id, 'value' => 'mesh']);

    expect($sku->attributeValues)->toHaveCount(2);
});

it('enforces unique external code per type', function () {
    $sku = Sku::factory()->create();
    SkuExternalCode::factory()->create([
        'sku_id' => $sku->id,
        'code' => '7501234567890',
        'type' => 'barcode',
    ]);

    expect(fn () => SkuExternalCode::factory()->create([
        'sku_id' => $sku->id,
        'code' => '7501234567890',
        'type' => 'barcode',
    ]))->toThrow(QueryException::class);
});
