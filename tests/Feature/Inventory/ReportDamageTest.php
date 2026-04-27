<?php

use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Product;
use App\Models\Sku;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->store = Warehouse::factory()->create(['code' => 'TDA1', 'type' => 'store']);
    $this->workshop = Warehouse::factory()->create(['code' => 'TALLER', 'type' => 'workshop']);

    $this->product = Product::factory()->create();
    $this->sku = Sku::factory()->create(['product_id' => $this->product->id]);

    // Stock inicial en TDA1: 5 unidades.
    $movement = Movement::factory()->confirmed()->create([
        'type' => 'inbound',
        'destination_warehouse_id' => $this->store->id,
        'created_by' => $this->user->id,
        'confirmed_by' => $this->user->id,
    ]);
    MovementLine::create([
        'movement_id' => $movement->id,
        'sku_id' => $this->sku->id,
        'warehouse_id' => $this->store->id,
        'direction' => 'in',
        'quantity' => 5,
    ]);
});

it('moves the requested quantity from the chosen warehouse into the workshop', function () {
    Livewire::test('pages::products.skus', ['product' => $this->product])
        ->call('openDamageReport', $this->sku->id)
        ->set('damageOriginWarehouseId', $this->store->id)
        ->set('damageQuantity', 2)
        ->set('damageNotes', 'Tela manchada')
        ->call('reportDamage')
        ->assertHasNoErrors();

    expect($this->sku->stockAt($this->store->id))->toBe(3.0)
        ->and($this->sku->stockAt($this->workshop->id))->toBe(2.0);

    $movement = Movement::query()
        ->where('type', 'transfer')
        ->where('destination_warehouse_id', $this->workshop->id)
        ->latest()
        ->first();

    expect($movement)->not->toBeNull()
        ->and($movement->status)->toBe('confirmed')
        ->and($movement->reason)->toContain('Reportado dañado');
});

it('rejects a damage report whose quantity exceeds the available stock', function () {
    Livewire::test('pages::products.skus', ['product' => $this->product])
        ->call('openDamageReport', $this->sku->id)
        ->set('damageOriginWarehouseId', $this->store->id)
        ->set('damageQuantity', 10)
        ->call('reportDamage')
        ->assertHasErrors(['damageQuantity']);

    expect($this->sku->stockAt($this->workshop->id))->toBe(0.0);
});

it('does not list workshop or scrap as eligible source warehouses', function () {
    Warehouse::factory()->create(['code' => 'MERMA', 'type' => 'scrap']);

    // Stock en taller también: aún así no debería aparecer como origen.
    $movement = Movement::factory()->confirmed()->create([
        'type' => 'inbound',
        'destination_warehouse_id' => $this->workshop->id,
        'created_by' => $this->user->id,
        'confirmed_by' => $this->user->id,
    ]);
    MovementLine::create([
        'movement_id' => $movement->id,
        'sku_id' => $this->sku->id,
        'warehouse_id' => $this->workshop->id,
        'direction' => 'in',
        'quantity' => 2,
    ]);

    $component = Livewire::test('pages::products.skus', ['product' => $this->product])
        ->call('openDamageReport', $this->sku->id);

    $eligible = $component->instance()->damageEligibleWarehouses;
    $codes = collect($eligible)->pluck('warehouse.code')->all();

    expect($codes)->toContain('TDA1')
        ->not->toContain('TALLER')
        ->not->toContain('MERMA');
});
