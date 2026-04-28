<?php

use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Sku;
use App\Models\User;
use App\Models\Warehouse;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = Warehouse::factory()->create(['code' => 'TDA1', 'type' => 'store']);
    $this->sku = Sku::factory()->create(['internal_code' => 'SKU-000001', 'sale_price' => 50.00]);
});

/** Stock inicial vía inbound confirmado. */
function seedStockForSaleApi(Sku $sku, Warehouse $warehouse, float $qty, User $user): void
{
    $movement = Movement::factory()->confirmed()->create([
        'type' => 'inbound',
        'destination_warehouse_id' => $warehouse->id,
        'created_by' => $user->id,
        'confirmed_by' => $user->id,
    ]);

    MovementLine::create([
        'movement_id' => $movement->id,
        'sku_id' => $sku->id,
        'warehouse_id' => $warehouse->id,
        'direction' => 'in',
        'quantity' => $qty,
    ]);
}

it('creates a sale and returns the movement with its lines', function () {
    seedStockForSaleApi($this->sku, $this->store, 10, $this->user);

    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/sales', [
        'sku_id' => $this->sku->id,
        'warehouse_id' => $this->store->id,
        'quantity' => 3,
        'notes' => 'Cliente de paso',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.type', 'sale')
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonCount(1, 'data.lines')
        ->assertJsonPath('data.lines.0.quantity', 3)
        ->assertJsonPath('data.lines.0.direction', 'out');

    expect($this->sku->stockAt($this->store->id))->toBe(7.0);
});

it('returns 422 when stock is insufficient', function () {
    seedStockForSaleApi($this->sku, $this->store, 2, $this->user);

    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/sales', [
        'sku_id' => $this->sku->id,
        'warehouse_id' => $this->store->id,
        'quantity' => 5,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['quantity' => 'Stock insuficiente']);
});

it('validates required fields on sale', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/sales', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sku_id', 'warehouse_id', 'quantity']);
});

it('rejects sales without a token', function () {
    $this->postJson('/api/v1/sales', [
        'sku_id' => $this->sku->id,
        'warehouse_id' => $this->store->id,
        'quantity' => 1,
    ])->assertUnauthorized();
});

it('lists todays sales for the current user via /sales/mine', function () {
    seedStockForSaleApi($this->sku, $this->store, 10, $this->user);
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/sales', [
        'sku_id' => $this->sku->id,
        'warehouse_id' => $this->store->id,
        'quantity' => 2,
    ])->assertCreated();

    $response = $this->getJson('/api/v1/sales/mine');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'sale')
        ->assertJsonPath('meta.summary.count', 1)
        ->assertJsonPath('meta.summary.total_units', 2)
        ->assertJsonPath('meta.summary.total_revenue', 100); // 2 * 50
});

it('only returns sales of the current user, not others', function () {
    seedStockForSaleApi($this->sku, $this->store, 10, $this->user);
    $other = User::factory()->create();
    seedStockForSaleApi($this->sku, $this->store, 10, $other);

    Sanctum::actingAs($other);
    $this->postJson('/api/v1/sales', [
        'sku_id' => $this->sku->id,
        'warehouse_id' => $this->store->id,
        'quantity' => 1,
    ])->assertCreated();

    Sanctum::actingAs($this->user);
    $response = $this->getJson('/api/v1/sales/mine');

    $response->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.summary.count', 0);
});

it('filters /sales/mine by from-to date range', function () {
    seedStockForSaleApi($this->sku, $this->store, 10, $this->user);

    $oldMovement = Movement::factory()->confirmed()->create([
        'type' => 'sale',
        'origin_warehouse_id' => $this->store->id,
        'created_by' => $this->user->id,
        'confirmed_by' => $this->user->id,
        'occurred_at' => now()->subDays(2),
    ]);
    MovementLine::create([
        'movement_id' => $oldMovement->id,
        'sku_id' => $this->sku->id,
        'warehouse_id' => $this->store->id,
        'direction' => 'out',
        'quantity' => 1,
    ]);

    Sanctum::actingAs($this->user);
    $this->postJson('/api/v1/sales', [
        'sku_id' => $this->sku->id,
        'warehouse_id' => $this->store->id,
        'quantity' => 4,
    ])->assertCreated();

    $todayOnly = $this->getJson('/api/v1/sales/mine');
    $todayOnly->assertOk()->assertJsonPath('meta.summary.count', 1);

    $threeDays = $this->getJson('/api/v1/sales/mine?from='.now()->subDays(3)->toDateString().'&to='.now()->toDateString());
    $threeDays->assertOk()->assertJsonPath('meta.summary.count', 2);
});

it('rejects sales/mine with from after to', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/sales/mine?from=2026-04-30&to=2026-04-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to']);
});

it('rejects sales/mine without a token', function () {
    $this->getJson('/api/v1/sales/mine')->assertUnauthorized();
});
