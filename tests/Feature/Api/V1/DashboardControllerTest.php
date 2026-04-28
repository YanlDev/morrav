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
    Warehouse::factory()->create(['code' => 'CEN', 'type' => 'central']);
});

/** Crea una venta confirmada del usuario en una fecha específica. */
function recordSale(User $user, Warehouse $warehouse, Sku $sku, ?string $occurredAt = null): void
{
    $movement = Movement::factory()->confirmed()->create([
        'type' => 'sale',
        'origin_warehouse_id' => $warehouse->id,
        'created_by' => $user->id,
        'confirmed_by' => $user->id,
        'occurred_at' => $occurredAt ?? now(),
    ]);

    MovementLine::create([
        'movement_id' => $movement->id,
        'sku_id' => $sku->id,
        'warehouse_id' => $warehouse->id,
        'direction' => 'out',
        'quantity' => 1,
    ]);
}

it('returns todays confirmed sales for the current user only', function () {
    $sku = Sku::factory()->create();
    $other = User::factory()->create();

    recordSale($this->user, $this->store, $sku);
    recordSale($this->user, $this->store, $sku);
    recordSale($other, $this->store, $sku); // otro usuario, no cuenta
    recordSale($this->user, $this->store, $sku, now()->subDay()->toDateTimeString()); // ayer, no cuenta

    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/dashboard');

    $response->assertOk()->assertJsonPath('data.sales_today', 2);
});

it('returns only active stores in the dashboard', function () {
    Warehouse::factory()->create(['code' => 'TDA2', 'type' => 'store', 'active' => false]);

    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/dashboard');

    $response->assertOk()
        ->assertJsonCount(1, 'data.stores')
        ->assertJsonPath('data.stores.0.code', 'TDA1');
});

it('rejects dashboard without a token', function () {
    $this->getJson('/api/v1/dashboard')->assertUnauthorized();
});
