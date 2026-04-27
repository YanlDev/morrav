<?php

use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Sku;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function confirmedLine(Sku $sku, Warehouse $warehouse, float $qty, string $direction = 'in'): void
{
    $movement = Movement::factory()->state([
        'status' => 'confirmed',
        'type' => $direction === 'in' ? 'inbound' : 'outbound',
        'occurred_at' => now(),
    ])->create();

    MovementLine::factory()->create([
        'movement_id' => $movement->id,
        'sku_id' => $sku->id,
        'warehouse_id' => $warehouse->id,
        'direction' => $direction,
        'quantity' => $qty,
    ]);
}

it('counts total SKUs across all statuses', function () {
    Sku::factory()->count(3)->create(['status' => 'active']);
    Sku::factory()->count(2)->create(['status' => 'draft']);
    Sku::factory()->create(['status' => 'discontinued']);

    $component = Livewire::test('pages::dashboard.index');

    expect($component->invade()->totalSkus())->toBe(6);
    expect($component->invade()->skusByStatus())
        ->toMatchArray(['active' => 3, 'draft' => 2, 'discontinued' => 1]);
});

it('counts movements occurred today only', function () {
    Movement::factory()->create(['occurred_at' => now()]);
    Movement::factory()->create(['occurred_at' => now()]);
    Movement::factory()->create(['occurred_at' => now()->subDay()]);
    Movement::factory()->create(['occurred_at' => now()->addDay()]);

    expect(Livewire::test('pages::dashboard.index')->invade()->movementsTodayCount())
        ->toBe(2);
});

it('counts (sku, warehouse) pairs with negative stock', function () {
    $warehouse = Warehouse::factory()->create();

    $skuNegative = Sku::factory()->create();
    confirmedLine($skuNegative, $warehouse, 2, 'in');
    confirmedLine($skuNegative, $warehouse, 5, 'out');

    $skuPositive = Sku::factory()->create();
    confirmedLine($skuPositive, $warehouse, 10, 'in');

    $skuZero = Sku::factory()->create();
    confirmedLine($skuZero, $warehouse, 3, 'in');
    confirmedLine($skuZero, $warehouse, 3, 'out');

    expect(Livewire::test('pages::dashboard.index')->invade()->negativeStockCount())
        ->toBe(1);
});

it('ignores draft and voided movements when counting negative stock', function () {
    $warehouse = Warehouse::factory()->create();
    $sku = Sku::factory()->create();

    $draft = Movement::factory()->state(['status' => 'draft', 'type' => 'outbound'])->create();
    MovementLine::factory()->create([
        'movement_id' => $draft->id,
        'sku_id' => $sku->id,
        'warehouse_id' => $warehouse->id,
        'direction' => 'out',
        'quantity' => 100,
    ]);

    expect(Livewire::test('pages::dashboard.index')->invade()->negativeStockCount())
        ->toBe(0);
});

it('shows the five most recent movements ordered by occurrence', function () {
    $older = Movement::factory()->create(['occurred_at' => now()->subDays(3)]);
    $newest = Movement::factory()->create(['occurred_at' => now()]);

    Movement::factory()->count(5)->create(['occurred_at' => now()->subDay()]);

    $recent = Livewire::test('pages::dashboard.index')->invade()->recentMovements();

    expect($recent)->toHaveCount(5)
        ->and($recent->first()->id)->toBe($newest->id)
        ->and($recent->contains('id', $older->id))->toBeFalse();
});

it('renders successfully for an authenticated user', function () {
    Livewire::test('pages::dashboard.index')
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('SKUs totales')
        ->assertSee('Movimientos hoy')
        ->assertSee('Alertas de stock negativo');
});
