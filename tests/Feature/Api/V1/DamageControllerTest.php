<?php

use App\Models\DamageReport;
use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Sku;
use App\Models\User;
use App\Models\Warehouse;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = Warehouse::factory()->create(['code' => 'TDA1', 'type' => 'store']);
    $this->workshop = Warehouse::factory()->create(['code' => 'TALLER', 'type' => 'workshop']);
    $this->sku = Sku::factory()->create();
});

function seedStockForDamageApi(Sku $sku, Warehouse $warehouse, float $qty, User $user): void
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

it('creates a damage report and moves stock to the workshop', function () {
    seedStockForDamageApi($this->sku, $this->store, 10, $this->user);

    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/damages', [
        'sku_id' => $this->sku->id,
        'warehouse_id' => $this->store->id,
        'quantity' => 2,
        'reason_code' => 'broken',
        'notes' => 'Pata rota',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.quantity', 2)
        ->assertJsonPath('data.reason_code', 'broken')
        ->assertJsonPath('data.reason_label', 'Roto')
        ->assertJsonPath('data.is_pending', true);

    expect(DamageReport::count())->toBe(1)
        ->and($this->sku->stockAt($this->store->id))->toBe(8.0)
        ->and($this->sku->stockAt($this->workshop->id))->toBe(2.0);
});

it('accepts damage report without a reason_code', function () {
    seedStockForDamageApi($this->sku, $this->store, 5, $this->user);

    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/damages', [
        'sku_id' => $this->sku->id,
        'warehouse_id' => $this->store->id,
        'quantity' => 1,
    ]);

    $response->assertOk()->assertJsonPath('data.reason_code', null);
});

it('rejects an unknown reason_code with 422', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/damages', [
        'sku_id' => $this->sku->id,
        'warehouse_id' => $this->store->id,
        'quantity' => 1,
        'reason_code' => 'invalid-value',
    ])->assertUnprocessable()->assertJsonValidationErrors(['reason_code']);
});

it('returns 422 when stock is insufficient', function () {
    seedStockForDamageApi($this->sku, $this->store, 1, $this->user);

    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/damages', [
        'sku_id' => $this->sku->id,
        'warehouse_id' => $this->store->id,
        'quantity' => 5,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['quantity' => 'Stock insuficiente']);
});

it('validates required fields on damage', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/damages', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sku_id', 'warehouse_id', 'quantity']);
});

it('rejects damages without a token', function () {
    $this->postJson('/api/v1/damages', [
        'sku_id' => $this->sku->id,
        'warehouse_id' => $this->store->id,
        'quantity' => 1,
    ])->assertUnauthorized();
});
