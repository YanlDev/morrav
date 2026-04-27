<?php

use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Sku;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Sales\SaleService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = Warehouse::factory()->create(['code' => 'TDA1', 'type' => 'store']);
    $this->sku = Sku::factory()->create();
    $this->service = app(SaleService::class);
});

/**
 * Mete N unidades del SKU en el almacén con un inbound confirmado.
 */
function seedStockForSale(Sku $sku, Warehouse $warehouse, float $qty, User $user): void
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

it('records a confirmed sale movement and decreases stock', function () {
    seedStockForSale($this->sku, $this->store, 10, $this->user);

    $movement = $this->service->sell(
        user: $this->user,
        sku: $this->sku,
        warehouse: $this->store,
        quantity: 3,
    );

    expect($movement->type)->toBe('sale')
        ->and($movement->status)->toBe('confirmed')
        ->and($movement->origin_warehouse_id)->toBe($this->store->id)
        ->and($movement->lines)->toHaveCount(1);

    $line = $movement->lines->first();
    expect($line->direction)->toBe('out')
        ->and((float) $line->quantity)->toBe(3.0);

    expect($this->sku->stockAt($this->store->id))->toBe(7.0);
});

it('rejects sales that exceed available stock', function () {
    seedStockForSale($this->sku, $this->store, 2, $this->user);

    expect(fn () => $this->service->sell(
        user: $this->user,
        sku: $this->sku,
        warehouse: $this->store,
        quantity: 5,
    ))->toThrow(RuntimeException::class, 'Stock insuficiente');

    // Nada se movió.
    expect($this->sku->stockAt($this->store->id))->toBe(2.0);
    expect(Movement::where('type', 'sale')->count())->toBe(0);
});

it('rejects sales of zero or negative quantity', function () {
    seedStockForSale($this->sku, $this->store, 5, $this->user);

    expect(fn () => $this->service->sell($this->user, $this->sku, $this->store, 0))
        ->toThrow(RuntimeException::class, 'mayor a cero');

    expect(fn () => $this->service->sell($this->user, $this->sku, $this->store, -1))
        ->toThrow(RuntimeException::class, 'mayor a cero');
});

it('stores the optional notes on both movement and line', function () {
    seedStockForSale($this->sku, $this->store, 5, $this->user);

    $movement = $this->service->sell(
        user: $this->user,
        sku: $this->sku,
        warehouse: $this->store,
        quantity: 1,
        notes: 'Cliente: Juan Pérez',
    );

    expect($movement->reason)->toBe('Cliente: Juan Pérez')
        ->and($movement->lines->first()->notes)->toBe('Cliente: Juan Pérez');
});

it('records the user who created and confirmed the sale', function () {
    seedStockForSale($this->sku, $this->store, 5, $this->user);

    $movement = $this->service->sell($this->user, $this->sku, $this->store, 1);

    expect($movement->created_by)->toBe($this->user->id)
        ->and($movement->confirmed_by)->toBe($this->user->id)
        ->and($movement->confirmed_at)->not->toBeNull();
});
