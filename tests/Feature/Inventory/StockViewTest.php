<?php

use App\Models\Family;
use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Product;
use App\Models\Sku;
use App\Models\Subfamily;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function seedStock(Sku $sku, Warehouse $warehouse, float $qty, string $direction = 'in', string $status = 'confirmed'): void
{
    $movement = Movement::factory()->state([
        'status' => $status,
        'type' => $direction === 'in' ? 'inbound' : 'outbound',
    ])->create();

    MovementLine::factory()->create([
        'movement_id' => $movement->id,
        'sku_id' => $sku->id,
        'warehouse_id' => $warehouse->id,
        'direction' => $direction,
        'quantity' => $qty,
    ]);
}

it('lists SKUs with stock pivoted by warehouse', function () {
    $warehouse = Warehouse::factory()->create(['code' => 'ALM']);
    $sku = Sku::factory()->create();

    seedStock($sku, $warehouse, 12);

    Livewire::test('pages::stock.index')
        ->assertOk()
        ->assertSee($sku->internal_code);
});

it('shows negative stock when outbounds exceed inbounds', function () {
    $warehouse = Warehouse::factory()->create();
    $sku = Sku::factory()->create();

    seedStock($sku, $warehouse, 5);
    seedStock($sku, $warehouse, 8, 'out');

    $component = Livewire::test('pages::stock.index');
    $matrix = $component->invade()->stockMatrix();

    expect($matrix[$sku->id][$warehouse->id])->toBe(-3.0);
});

it('ignores non-confirmed movements in the matrix', function () {
    $warehouse = Warehouse::factory()->create();
    $sku = Sku::factory()->create();

    seedStock($sku, $warehouse, 10, 'in', 'draft');
    seedStock($sku, $warehouse, 4, 'in', 'voided');
    seedStock($sku, $warehouse, 3, 'in', 'confirmed');

    $component = Livewire::test('pages::stock.index');

    expect($component->invade()->stockMatrix()[$sku->id][$warehouse->id] ?? 0)
        ->toBe(3.0);
});

it('filters by family', function () {
    $oficina = Family::factory()->create(['code' => 'OFICINA']);
    $hogar = Family::factory()->create(['code' => 'HOGAR']);

    $sillaOficina = Sku::factory()
        ->for(Product::factory()->state(['family_id' => $oficina->id]))
        ->create();
    $sofaHogar = Sku::factory()
        ->for(Product::factory()->state(['family_id' => $hogar->id]))
        ->create();

    Livewire::test('pages::stock.index')
        ->set('familyFilter', (string) $oficina->id)
        ->assertSee($sillaOficina->internal_code)
        ->assertDontSee($sofaHogar->internal_code);
});

it('filters by subfamily', function () {
    $family = Family::factory()->create();
    $subA = Subfamily::factory()->for($family)->create();
    $subB = Subfamily::factory()->for($family)->create();

    $skuA = Sku::factory()
        ->for(Product::factory()->state(['family_id' => $family->id, 'subfamily_id' => $subA->id]))
        ->create();
    $skuB = Sku::factory()
        ->for(Product::factory()->state(['family_id' => $family->id, 'subfamily_id' => $subB->id]))
        ->create();

    Livewire::test('pages::stock.index')
        ->set('familyFilter', (string) $family->id)
        ->set('subfamilyFilter', (string) $subA->id)
        ->assertSee($skuA->internal_code)
        ->assertDontSee($skuB->internal_code);
});

it('searches by SKU code and product name', function () {
    $sku = Sku::factory()
        ->for(Product::factory()->state(['name' => 'Silla Milano Gerencial']))
        ->create(['internal_code' => 'SKU-000999']);

    Sku::factory()->create(['internal_code' => 'SKU-999999']);

    Livewire::test('pages::stock.index')
        ->set('search', 'Milano')
        ->assertSee('SKU-000999')
        ->assertDontSee('SKU-999999');
});

it('clears subfamily filter when family changes', function () {
    $family = Family::factory()->create();
    $sub = Subfamily::factory()->for($family)->create();

    Livewire::test('pages::stock.index')
        ->set('familyFilter', (string) $family->id)
        ->set('subfamilyFilter', (string) $sub->id)
        ->set('familyFilter', '')
        ->assertSet('subfamilyFilter', '');
});

it('filter "negative" only shows SKUs with negative total', function () {
    $warehouse = Warehouse::factory()->create();

    $skuPositive = Sku::factory()->create();
    $skuNegative = Sku::factory()->create();

    seedStock($skuPositive, $warehouse, 5);
    seedStock($skuNegative, $warehouse, 2);
    seedStock($skuNegative, $warehouse, 7, 'out');

    Livewire::test('pages::stock.index')
        ->set('stockFilter', 'negative')
        ->assertSee($skuNegative->internal_code)
        ->assertDontSee($skuPositive->internal_code);
});

it('filter "with_stock" only shows SKUs with positive total', function () {
    $warehouse = Warehouse::factory()->create();

    $skuPositive = Sku::factory()->create();
    $skuZero = Sku::factory()->create();

    seedStock($skuPositive, $warehouse, 10);
    seedStock($skuZero, $warehouse, 3);
    seedStock($skuZero, $warehouse, 3, 'out');

    Livewire::test('pages::stock.index')
        ->set('stockFilter', 'with_stock')
        ->assertSee($skuPositive->internal_code)
        ->assertDontSee($skuZero->internal_code);
});

it('totals card reflects counts across families', function () {
    $warehouse = Warehouse::factory()->create();

    $withStock = Sku::factory()->create();
    $negative = Sku::factory()->create();
    Sku::factory()->create();

    seedStock($withStock, $warehouse, 10);
    seedStock($negative, $warehouse, 2);
    seedStock($negative, $warehouse, 5, 'out');

    $totals = Livewire::test('pages::stock.index')->invade()->totals();

    expect($totals['with_stock'])->toBe(1)
        ->and($totals['negative'])->toBe(1)
        ->and($totals['skus'])->toBeGreaterThanOrEqual(3);
});
