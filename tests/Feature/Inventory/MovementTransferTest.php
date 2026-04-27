<?php

use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Sku;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->origin = Warehouse::factory()->create(['code' => 'ALM']);
    $this->destination = Warehouse::factory()->create(['code' => 'TDA1']);
});

it('can create a transfer draft with origin and destination', function () {
    Livewire::test('pages::movements.index')
        ->call('openCreate')
        ->set('newType', 'transfer')
        ->set('newOriginWarehouseId', $this->origin->id)
        ->set('newDestinationWarehouseId', $this->destination->id)
        ->set('newOccurredAt', now()->format('Y-m-d\TH:i'))
        ->call('create')
        ->assertHasNoErrors();

    $movement = Movement::first();

    expect($movement)->not->toBeNull()
        ->and($movement->type)->toBe('transfer')
        ->and($movement->status)->toBe('draft')
        ->and($movement->origin_warehouse_id)->toBe($this->origin->id)
        ->and($movement->destination_warehouse_id)->toBe($this->destination->id);
});

it('rejects a transfer where origin equals destination', function () {
    Livewire::test('pages::movements.index')
        ->call('openCreate')
        ->set('newType', 'transfer')
        ->set('newOriginWarehouseId', $this->origin->id)
        ->set('newDestinationWarehouseId', $this->origin->id)
        ->set('newOccurredAt', now()->format('Y-m-d\TH:i'))
        ->call('create')
        ->assertHasErrors(['newDestinationWarehouseId']);
});

it('rejects a transfer without origin', function () {
    Livewire::test('pages::movements.index')
        ->call('openCreate')
        ->set('newType', 'transfer')
        ->set('newDestinationWarehouseId', $this->destination->id)
        ->set('newOccurredAt', now()->format('Y-m-d\TH:i'))
        ->call('create')
        ->assertHasErrors(['newOriginWarehouseId']);
});

it('rejects a transfer without destination', function () {
    Livewire::test('pages::movements.index')
        ->call('openCreate')
        ->set('newType', 'transfer')
        ->set('newOriginWarehouseId', $this->origin->id)
        ->set('newOccurredAt', now()->format('Y-m-d\TH:i'))
        ->call('create')
        ->assertHasErrors(['newDestinationWarehouseId']);
});

it('addLine creates two movement_lines (out from origin, in to destination)', function () {
    $sku = Sku::factory()->create();
    $movement = Movement::factory()->state([
        'type' => 'transfer',
        'status' => 'draft',
        'origin_warehouse_id' => $this->origin->id,
        'destination_warehouse_id' => $this->destination->id,
    ])->create();

    Livewire::test('pages::movements.show', ['movement' => $movement])
        ->set('lineSkuId', $sku->id)
        ->set('lineQuantity', '2')
        ->call('addLine')
        ->assertHasNoErrors();

    $lines = MovementLine::where('movement_id', $movement->id)->get();

    expect($lines)->toHaveCount(2);

    $outLine = $lines->firstWhere('direction', 'out');
    $inLine = $lines->firstWhere('direction', 'in');

    expect($outLine)->not->toBeNull()
        ->and($outLine->warehouse_id)->toBe($this->origin->id)
        ->and((float) $outLine->quantity)->toBe(2.0);

    expect($inLine)->not->toBeNull()
        ->and($inLine->warehouse_id)->toBe($this->destination->id)
        ->and((float) $inLine->quantity)->toBe(2.0);
});

it('confirming a transfer moves stock from origin to destination', function () {
    $sku = Sku::factory()->create();
    $movement = Movement::factory()->state([
        'type' => 'transfer',
        'status' => 'draft',
        'origin_warehouse_id' => $this->origin->id,
        'destination_warehouse_id' => $this->destination->id,
    ])->create();

    // Primero cargar stock en origen con un ingreso confirmado
    $inbound = Movement::factory()->state([
        'type' => 'inbound',
        'status' => 'confirmed',
        'destination_warehouse_id' => $this->origin->id,
    ])->create();
    MovementLine::factory()->create([
        'movement_id' => $inbound->id,
        'sku_id' => $sku->id,
        'warehouse_id' => $this->origin->id,
        'direction' => 'in',
        'quantity' => 10,
    ]);

    Livewire::test('pages::movements.show', ['movement' => $movement])
        ->set('lineSkuId', $sku->id)
        ->set('lineQuantity', '3')
        ->call('addLine')
        ->call('confirm')
        ->assertHasNoErrors();

    expect($sku->stockAt($this->origin->id))->toBe(7.0)
        ->and($sku->stockAt($this->destination->id))->toBe(3.0);
});

it('removing a transfer line removes both halves', function () {
    $sku = Sku::factory()->create();
    $movement = Movement::factory()->state([
        'type' => 'transfer',
        'status' => 'draft',
        'origin_warehouse_id' => $this->origin->id,
        'destination_warehouse_id' => $this->destination->id,
    ])->create();

    $component = Livewire::test('pages::movements.show', ['movement' => $movement])
        ->set('lineSkuId', $sku->id)
        ->set('lineQuantity', '5')
        ->call('addLine');

    expect(MovementLine::where('movement_id', $movement->id)->count())->toBe(2);

    $anyLine = MovementLine::where('movement_id', $movement->id)->first();

    $component->call('removeLine', $anyLine->id);

    expect(MovementLine::where('movement_id', $movement->id)->count())->toBe(0);
});

it('shows only the outbound half of a transfer in the lines list', function () {
    $sku = Sku::factory()->create();
    $movement = Movement::factory()->state([
        'type' => 'transfer',
        'status' => 'draft',
        'origin_warehouse_id' => $this->origin->id,
        'destination_warehouse_id' => $this->destination->id,
    ])->create();

    $component = Livewire::test('pages::movements.show', ['movement' => $movement])
        ->set('lineSkuId', $sku->id)
        ->set('lineQuantity', '4')
        ->call('addLine');

    $lines = $component->invade()->lines();

    expect($lines)->toHaveCount(1)
        ->and($lines->first()->direction)->toBe('out')
        ->and($lines->first()->warehouse_id)->toBe($this->origin->id);
});

it('stock view reflects a confirmed transfer', function () {
    $sku = Sku::factory()->create();

    $inbound = Movement::factory()->state([
        'type' => 'inbound',
        'status' => 'confirmed',
        'destination_warehouse_id' => $this->origin->id,
    ])->create();
    MovementLine::factory()->create([
        'movement_id' => $inbound->id,
        'sku_id' => $sku->id,
        'warehouse_id' => $this->origin->id,
        'direction' => 'in',
        'quantity' => 10,
    ]);

    $transfer = Movement::factory()->state([
        'type' => 'transfer',
        'status' => 'confirmed',
        'origin_warehouse_id' => $this->origin->id,
        'destination_warehouse_id' => $this->destination->id,
    ])->create();
    MovementLine::factory()->create([
        'movement_id' => $transfer->id,
        'sku_id' => $sku->id,
        'warehouse_id' => $this->origin->id,
        'direction' => 'out',
        'quantity' => 4,
    ]);
    MovementLine::factory()->create([
        'movement_id' => $transfer->id,
        'sku_id' => $sku->id,
        'warehouse_id' => $this->destination->id,
        'direction' => 'in',
        'quantity' => 4,
    ]);

    $matrix = Livewire::test('pages::stock.index')->invade()->stockMatrix();

    expect($matrix[$sku->id][$this->origin->id])->toBe(6.0)
        ->and($matrix[$sku->id][$this->destination->id])->toBe(4.0);
});
