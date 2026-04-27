<?php

use App\Models\Attribute;
use App\Models\Family;
use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Subfamily;
use App\Models\Warehouse;
use App\Services\Catalog\ProductCreator;

it('creates product with one SKU and no attributes', function () {
    $family = Family::factory()->create();

    $result = app(ProductCreator::class)->create(
        ['name' => 'Silla X', 'family_id' => $family->id],
        [['variant_name' => null, 'sale_price' => 100.0, 'purchase_price' => 60.0]],
    );

    expect($result['product']->name)->toBe('Silla X')
        ->and($result['skus'])->toHaveCount(1)
        ->and($result['product']->internal_code)->toStartWith('PROD-')
        ->and($result['skus']->first()->internal_code)->toStartWith('SKU-');
});

it('creates product with multiple SKUs', function () {
    $family = Family::factory()->create();

    $result = app(ProductCreator::class)->create(
        ['name' => 'Silla Multi', 'family_id' => $family->id],
        [
            ['variant_name' => 'Negro', 'sale_price' => 100.0],
            ['variant_name' => 'Gris', 'sale_price' => 90.0],
            ['variant_name' => 'Rojo', 'sale_price' => 95.0],
        ],
    );

    expect($result['skus'])->toHaveCount(3);

    $variantNames = $result['skus']->pluck('variant_name')->all();
    expect($variantNames)->toContain('Negro', 'Gris', 'Rojo');
});

it('attaches attributes to each SKU', function () {
    $family = Family::factory()->create();
    Attribute::factory()->create(['code' => 'color']);
    Attribute::factory()->create(['code' => 'material']);

    $result = app(ProductCreator::class)->create(
        ['name' => 'Silla Con Atributos', 'family_id' => $family->id],
        [[
            'variant_name' => 'Negro / Cuero',
            'attributes' => ['color' => 'negro', 'material' => 'cuero'],
        ]],
    );

    $sku = $result['skus']->first();
    $values = $sku->attributeValues()->with('attribute')->get()->pluck('value', 'attribute.code');

    expect($values->get('color'))->toBe('negro')
        ->and($values->get('material'))->toBe('cuero');
});

it('falls back to PENDIENTE subfamily when none provided', function () {
    $family = Family::factory()->create(['code' => 'FAM_X']);
    $pending = Subfamily::factory()->create(['family_id' => $family->id, 'code' => 'PENDIENTE']);

    $result = app(ProductCreator::class)->create(
        ['name' => 'Sin subfamilia', 'family_id' => $family->id],
        [['variant_name' => null]],
    );

    expect($result['product']->subfamily_id)->toBe($pending->id);
});

it('ignores subfamily from another family and uses PENDIENTE', function () {
    $familyA = Family::factory()->create(['code' => 'FAM_A']);
    $familyB = Family::factory()->create(['code' => 'FAM_B']);
    $subOfB = Subfamily::factory()->create(['family_id' => $familyB->id]);
    $pendingA = Subfamily::factory()->create(['family_id' => $familyA->id, 'code' => 'PENDIENTE']);

    $result = app(ProductCreator::class)->create(
        ['name' => 'Cross family', 'family_id' => $familyA->id, 'subfamily_id' => $subOfB->id],
        [['variant_name' => null]],
    );

    expect($result['product']->subfamily_id)->toBe($pendingA->id);
});

it('creates movement lines when a movement is provided', function () {
    $family = Family::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $movement = Movement::factory()->state([
        'status' => 'draft',
        'type' => 'initial_load',
        'destination_warehouse_id' => $warehouse->id,
    ])->create();

    app(ProductCreator::class)->create(
        ['name' => 'Con movimiento', 'family_id' => $family->id],
        [
            ['variant_name' => 'A', 'quantity' => 5.0],
            ['variant_name' => 'B', 'quantity' => 3.0],
        ],
        $movement,
    );

    $lines = MovementLine::where('movement_id', $movement->id)->get();

    expect($lines)->toHaveCount(2)
        ->and($lines->sum('quantity'))->toBe(8.0)
        ->and($lines->every(fn ($l) => $l->direction === 'in'))->toBeTrue()
        ->and($lines->every(fn ($l) => $l->warehouse_id === $warehouse->id))->toBeTrue();
});

it('does not create movement lines when quantity is missing or zero', function () {
    $family = Family::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $movement = Movement::factory()->state([
        'status' => 'draft',
        'type' => 'inbound',
        'destination_warehouse_id' => $warehouse->id,
    ])->create();

    app(ProductCreator::class)->create(
        ['name' => 'Sin qty', 'family_id' => $family->id],
        [['variant_name' => 'A']],
        $movement,
    );

    expect(MovementLine::where('movement_id', $movement->id)->count())->toBe(0);
});

it('throws when no SKUs are provided', function () {
    $family = Family::factory()->create();

    app(ProductCreator::class)->create(['name' => 'X', 'family_id' => $family->id], []);
})->throws(InvalidArgumentException::class);

it('sets status to active by default', function () {
    $family = Family::factory()->create();

    $result = app(ProductCreator::class)->create(
        ['name' => 'Por defecto activo', 'family_id' => $family->id],
        [['variant_name' => null]],
    );

    expect($result['product']->status)->toBe('active')
        ->and($result['skus']->first()->status)->toBe('active');
});

it('generates unique internal codes when creating multiple products in sequence', function () {
    $family = Family::factory()->create();

    $r1 = app(ProductCreator::class)->create(
        ['name' => 'Uno', 'family_id' => $family->id],
        [['variant_name' => null]],
    );

    $r2 = app(ProductCreator::class)->create(
        ['name' => 'Dos', 'family_id' => $family->id],
        [['variant_name' => null]],
    );

    expect($r1['product']->internal_code)->not->toBe($r2['product']->internal_code)
        ->and($r1['skus']->first()->internal_code)->not->toBe($r2['skus']->first()->internal_code);
});
