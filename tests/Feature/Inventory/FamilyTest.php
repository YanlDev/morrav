<?php

use App\Models\Attribute;
use App\Models\Family;
use App\Models\Product;
use App\Models\Subfamily;

it('has many subfamilies', function () {
    $family = Family::factory()->create();
    Subfamily::factory()->count(3)->create(['family_id' => $family->id]);

    expect($family->subfamilies)->toHaveCount(3);
});

it('has many products', function () {
    $family = Family::factory()->create();
    Product::factory()->count(2)->create(['family_id' => $family->id]);

    expect($family->products)->toHaveCount(2);
});

it('attaches attributes with pivot data', function () {
    $family = Family::factory()->create();
    $color = Attribute::factory()->create(['code' => 'color']);

    $family->attributes()->attach($color, [
        'is_required' => true,
        'is_key' => true,
        'sort_order' => 1,
    ]);

    $attached = $family->attributes()->first();

    expect($attached->pivot->is_required)->toBeTrue()
        ->and($attached->pivot->is_key)->toBeTrue()
        ->and($attached->pivot->sort_order)->toBe(1);
});
