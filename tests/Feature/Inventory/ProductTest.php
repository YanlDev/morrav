<?php

use App\Models\Family;
use App\Models\Product;
use App\Models\Sku;
use App\Models\Subfamily;

it('belongs to a family', function () {
    $family = Family::factory()->create();
    $product = Product::factory()->create(['family_id' => $family->id]);

    expect($product->family->id)->toBe($family->id);
});

it('belongs to a subfamily when set', function () {
    $family = Family::factory()->create();
    $subfamily = Subfamily::factory()->create(['family_id' => $family->id]);
    $product = Product::factory()->create([
        'family_id' => $family->id,
        'subfamily_id' => $subfamily->id,
    ]);

    expect($product->subfamily->id)->toBe($subfamily->id);
});

it('has many skus', function () {
    $product = Product::factory()->create();
    Sku::factory()->count(3)->create(['product_id' => $product->id]);

    expect($product->skus)->toHaveCount(3);
});

it('supports soft deletes', function () {
    $product = Product::factory()->create();
    $product->delete();

    expect(Product::count())->toBe(0)
        ->and(Product::withTrashed()->count())->toBe(1);
});

it('filters by status via scopes', function () {
    Product::factory()->count(2)->create(['status' => 'active']);
    Product::factory()->draft()->create();

    expect(Product::active()->count())->toBe(2)
        ->and(Product::draft()->count())->toBe(1);
});
