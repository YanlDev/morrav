<?php

use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Sku;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email_verified_at' => now()]));
});

test('guests are redirected from movements index', function () {
    auth()->logout();

    $this->get(route('movements.index'))->assertRedirect(route('login'));
});

test('authenticated users can see movements index', function () {
    $this->get(route('movements.index'))
        ->assertOk()
        ->assertSeeLivewire('pages::movements.index');
});

test('users can create an inbound draft movement', function () {
    $warehouse = Warehouse::factory()->create();

    Livewire::test('pages::movements.index')
        ->call('openCreate')
        ->set('newType', 'inbound')
        ->set('newDestinationWarehouseId', $warehouse->id)
        ->set('newReason', 'OC #100')
        ->call('create')
        ->assertHasNoErrors();

    $movement = Movement::first();
    expect($movement)
        ->not->toBeNull()
        ->type->toBe('inbound')
        ->status->toBe('draft')
        ->destination_warehouse_id->toBe($warehouse->id)
        ->created_by->toBe(auth()->id());
});

test('inbound requires destination warehouse', function () {
    Livewire::test('pages::movements.index')
        ->set('newType', 'inbound')
        ->set('newDestinationWarehouseId', null)
        ->call('create')
        ->assertHasErrors(['newDestinationWarehouseId']);
});

test('outbound requires origin warehouse', function () {
    Livewire::test('pages::movements.index')
        ->set('newType', 'outbound')
        ->set('newOriginWarehouseId', null)
        ->call('create')
        ->assertHasErrors(['newOriginWarehouseId']);
});

test('movement numbers are auto-generated sequentially', function () {
    $w = Warehouse::factory()->create();

    Livewire::test('pages::movements.index')
        ->set('newType', 'inbound')
        ->set('newDestinationWarehouseId', $w->id)
        ->call('create');

    Livewire::test('pages::movements.index')
        ->set('newType', 'inbound')
        ->set('newDestinationWarehouseId', $w->id)
        ->call('create');

    expect(Movement::pluck('number')->all())->each->toStartWith('MOV-');
    expect(Movement::count())->toBe(2);
});

test('show page renders movement details', function () {
    $warehouse = Warehouse::factory()->create();
    $movement = Movement::factory()->inbound()->create([
        'destination_warehouse_id' => $warehouse->id,
    ]);

    $this->get(route('movements.show', $movement))
        ->assertOk()
        ->assertSee($movement->number)
        ->assertSee('Ingreso');
});

test('users can add a line to a draft inbound movement', function () {
    $warehouse = Warehouse::factory()->create();
    $movement = Movement::factory()->inbound()->create([
        'destination_warehouse_id' => $warehouse->id,
    ]);
    $sku = Sku::factory()->create();

    Livewire::test('pages::movements.show', ['movement' => $movement])
        ->set('lineSkuId', $sku->id)
        ->set('lineQuantity', '10')
        ->set('lineUnitCost', '25.50')
        ->call('addLine')
        ->assertHasNoErrors();

    expect(MovementLine::where('movement_id', $movement->id)->count())->toBe(1);
    $line = MovementLine::where('movement_id', $movement->id)->first();
    expect($line)
        ->sku_id->toBe($sku->id)
        ->warehouse_id->toBe($warehouse->id)
        ->direction->toBe('in')
        ->quantity->toEqual('10.00');
});

test('lines cannot be added to confirmed movements', function () {
    $warehouse = Warehouse::factory()->create();
    $movement = Movement::factory()->inbound()->confirmed()->create([
        'destination_warehouse_id' => $warehouse->id,
    ]);
    $sku = Sku::factory()->create();

    Livewire::test('pages::movements.show', ['movement' => $movement])
        ->set('lineSkuId', $sku->id)
        ->set('lineQuantity', '5')
        ->call('addLine')
        ->assertStatus(403);
});

test('users can remove a line from a draft movement', function () {
    $warehouse = Warehouse::factory()->create();
    $movement = Movement::factory()->inbound()->create([
        'destination_warehouse_id' => $warehouse->id,
    ]);
    $line = MovementLine::factory()->inbound()->create([
        'movement_id' => $movement->id,
        'warehouse_id' => $warehouse->id,
    ]);

    Livewire::test('pages::movements.show', ['movement' => $movement])
        ->call('removeLine', $line->id);

    expect(MovementLine::find($line->id))->toBeNull();
});

test('users can confirm a draft movement with lines', function () {
    $warehouse = Warehouse::factory()->create();
    $movement = Movement::factory()->inbound()->create([
        'destination_warehouse_id' => $warehouse->id,
    ]);
    MovementLine::factory()->inbound()->create([
        'movement_id' => $movement->id,
        'warehouse_id' => $warehouse->id,
    ]);

    Livewire::test('pages::movements.show', ['movement' => $movement])
        ->call('confirm');

    expect($movement->refresh())
        ->status->toBe('confirmed')
        ->confirmed_by->toBe(auth()->id())
        ->confirmed_at->not->toBeNull();
});

test('cannot confirm a movement without lines', function () {
    $warehouse = Warehouse::factory()->create();
    $movement = Movement::factory()->inbound()->create([
        'destination_warehouse_id' => $warehouse->id,
    ]);

    Livewire::test('pages::movements.show', ['movement' => $movement])
        ->call('confirm');

    expect($movement->refresh()->status)->toBe('draft');
});

test('confirmed movements update stock calculation', function () {
    $warehouse = Warehouse::factory()->create();
    $sku = Sku::factory()->create();

    Livewire::test('pages::movements.index')
        ->set('newType', 'inbound')
        ->set('newDestinationWarehouseId', $warehouse->id)
        ->call('create');

    $movement = Movement::first();

    Livewire::test('pages::movements.show', ['movement' => $movement])
        ->set('lineSkuId', $sku->id)
        ->set('lineQuantity', '15')
        ->call('addLine')
        ->call('confirm');

    expect($sku->stockAt($warehouse->id))->toBe(15.0);
});

test('draft movements can be discarded', function () {
    $warehouse = Warehouse::factory()->create();
    $movement = Movement::factory()->inbound()->create([
        'destination_warehouse_id' => $warehouse->id,
    ]);
    MovementLine::factory()->count(2)->create(['movement_id' => $movement->id]);

    Livewire::test('pages::movements.show', ['movement' => $movement])
        ->call('deleteDraft');

    expect(Movement::find($movement->id))->toBeNull();
    expect(MovementLine::where('movement_id', $movement->id)->count())->toBe(0);
});

test('confirmed movement can be voided with reason', function () {
    $warehouse = Warehouse::factory()->create();
    $movement = Movement::factory()->inbound()->confirmed()->create([
        'destination_warehouse_id' => $warehouse->id,
    ]);
    MovementLine::factory()->inbound()->create([
        'movement_id' => $movement->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
    ]);
    $sku = Sku::find(MovementLine::first()->sku_id);

    Livewire::test('pages::movements.show', ['movement' => $movement])
        ->call('openVoid')
        ->set('voidReason', 'Error de conteo')
        ->call('voidMovement')
        ->assertHasNoErrors();

    expect($movement->refresh())
        ->status->toBe('voided')
        ->void_reason->toBe('Error de conteo');

    expect($sku->stockAt($warehouse->id))->toBe(0.0);
});

test('void requires a minimum reason length', function () {
    $warehouse = Warehouse::factory()->create();
    $movement = Movement::factory()->inbound()->confirmed()->create([
        'destination_warehouse_id' => $warehouse->id,
    ]);

    Livewire::test('pages::movements.show', ['movement' => $movement])
        ->set('voidReason', 'x')
        ->call('voidMovement')
        ->assertHasErrors(['voidReason' => 'min']);
});

test('adjustment supports both in and out directions', function () {
    $warehouse = Warehouse::factory()->create();
    $movement = Movement::factory()->create([
        'type' => 'adjustment',
        'status' => 'draft',
        'origin_warehouse_id' => $warehouse->id,
    ]);
    $sku = Sku::factory()->create();

    Livewire::test('pages::movements.show', ['movement' => $movement])
        ->set('lineSkuId', $sku->id)
        ->set('lineQuantity', '3')
        ->set('lineDirection', 'out')
        ->call('addLine');

    $line = MovementLine::where('movement_id', $movement->id)->first();
    expect($line->direction)->toBe('out');
});

test('filters limit the movements list', function () {
    $w1 = Warehouse::factory()->create();
    $w2 = Warehouse::factory()->create();

    Movement::factory()->inbound()->create(['destination_warehouse_id' => $w1->id, 'number' => 'MOV-A']);
    Movement::factory()->outbound()->create(['origin_warehouse_id' => $w2->id, 'number' => 'MOV-B']);

    Livewire::test('pages::movements.index')
        ->set('typeFilter', 'inbound')
        ->assertSee('MOV-A')
        ->assertDontSee('MOV-B');
});
