<?php

use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Sku;
use App\Models\Warehouse;

function inboundLine(Sku $sku, Warehouse $warehouse, float $qty, string $status = 'confirmed'): MovementLine
{
    $movement = Movement::factory()->state(['status' => $status, 'type' => 'inbound'])->create();

    return MovementLine::factory()->create([
        'movement_id' => $movement->id,
        'sku_id' => $sku->id,
        'warehouse_id' => $warehouse->id,
        'direction' => 'in',
        'quantity' => $qty,
    ]);
}

function outboundLine(Sku $sku, Warehouse $warehouse, float $qty, string $status = 'confirmed'): MovementLine
{
    $movement = Movement::factory()->state(['status' => $status, 'type' => 'outbound'])->create();

    return MovementLine::factory()->create([
        'movement_id' => $movement->id,
        'sku_id' => $sku->id,
        'warehouse_id' => $warehouse->id,
        'direction' => 'out',
        'quantity' => $qty,
    ]);
}

it('sums confirmed inbound quantities', function () {
    $sku = Sku::factory()->create();
    $warehouse = Warehouse::factory()->create();

    inboundLine($sku, $warehouse, 10);
    inboundLine($sku, $warehouse, 5);

    expect($sku->stockAt($warehouse->id))->toBe(15.0);
});

it('subtracts confirmed outbound quantities', function () {
    $sku = Sku::factory()->create();
    $warehouse = Warehouse::factory()->create();

    inboundLine($sku, $warehouse, 20);
    outboundLine($sku, $warehouse, 7);

    expect($sku->stockAt($warehouse->id))->toBe(13.0);
});

it('ignores draft movements', function () {
    $sku = Sku::factory()->create();
    $warehouse = Warehouse::factory()->create();

    inboundLine($sku, $warehouse, 10, status: 'confirmed');
    inboundLine($sku, $warehouse, 99, status: 'draft');

    expect($sku->stockAt($warehouse->id))->toBe(10.0);
});

it('ignores voided movements', function () {
    $sku = Sku::factory()->create();
    $warehouse = Warehouse::factory()->create();

    inboundLine($sku, $warehouse, 10, status: 'confirmed');
    inboundLine($sku, $warehouse, 50, status: 'voided');

    expect($sku->stockAt($warehouse->id))->toBe(10.0);
});

it('isolates stock per warehouse', function () {
    $sku = Sku::factory()->create();
    $central = Warehouse::factory()->create();
    $store = Warehouse::factory()->create();

    inboundLine($sku, $central, 100);
    inboundLine($sku, $store, 25);

    expect($sku->stockAt($central->id))->toBe(100.0)
        ->and($sku->stockAt($store->id))->toBe(25.0);
});

it('returns zero for a sku with no movements', function () {
    $sku = Sku::factory()->create();
    $warehouse = Warehouse::factory()->create();

    expect($sku->stockAt($warehouse->id))->toBe(0.0);
});

it('handles a transfer as two linked lines', function () {
    $sku = Sku::factory()->create();
    $origin = Warehouse::factory()->create();
    $destination = Warehouse::factory()->create();

    inboundLine($sku, $origin, 50);

    $transfer = Movement::factory()->confirmed()->transfer()->create();
    MovementLine::factory()->create([
        'movement_id' => $transfer->id,
        'sku_id' => $sku->id,
        'warehouse_id' => $origin->id,
        'direction' => 'out',
        'quantity' => 20,
    ]);
    MovementLine::factory()->create([
        'movement_id' => $transfer->id,
        'sku_id' => $sku->id,
        'warehouse_id' => $destination->id,
        'direction' => 'in',
        'quantity' => 20,
    ]);

    expect($sku->stockAt($origin->id))->toBe(30.0)
        ->and($sku->stockAt($destination->id))->toBe(20.0);
});
