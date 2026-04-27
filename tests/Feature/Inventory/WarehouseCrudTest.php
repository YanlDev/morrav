<?php

use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email_verified_at' => now()]));
});

test('guests are redirected from the warehouses index', function () {
    auth()->logout();

    $this->get(route('warehouses.index'))->assertRedirect(route('login'));
});

test('authenticated users can see the warehouses index', function () {
    Warehouse::factory()->create(['code' => 'ALM', 'name' => 'Almacén Central']);

    $this->get(route('warehouses.index'))
        ->assertOk()
        ->assertSeeLivewire('pages::warehouses.index');
});

test('the index lists existing warehouses', function () {
    Warehouse::factory()->create(['code' => 'ALM', 'name' => 'Central']);
    Warehouse::factory()->create(['code' => 'TDA1', 'name' => 'Tienda Uno']);

    Livewire::test('pages::warehouses.index')
        ->assertSee('ALM')
        ->assertSee('Central')
        ->assertSee('TDA1')
        ->assertSee('Tienda Uno');
});

test('search filters warehouses by code or name', function () {
    Warehouse::factory()->create(['code' => 'Z-CENTRAL', 'name' => 'Central Zona']);
    Warehouse::factory()->create(['code' => 'Z-NORTE', 'name' => 'Tienda Norte']);

    Livewire::test('pages::warehouses.index')
        ->set('search', 'Norte')
        ->assertSee('Z-NORTE')
        ->assertDontSee('Z-CENTRAL');
});

test('type filter limits the list', function () {
    Warehouse::factory()->asCentral()->create(['code' => 'Z-CENTRAL']);
    Warehouse::factory()->asStore()->create(['code' => 'Z-TIENDA1']);
    Warehouse::factory()->asStore()->create(['code' => 'Z-TIENDA2']);

    Livewire::test('pages::warehouses.index')
        ->set('typeFilter', 'store')
        ->assertSee('Z-TIENDA1')
        ->assertSee('Z-TIENDA2')
        ->assertDontSee('Z-CENTRAL');
});

test('users can create a new warehouse', function () {
    Livewire::test('pages::warehouses.index')
        ->call('openCreate')
        ->set('code', 'TDA9')
        ->set('name', 'Tienda Nueva')
        ->set('type', 'store')
        ->set('address', 'Av. Principal 123')
        ->call('save')
        ->assertHasNoErrors();

    expect(Warehouse::where('code', 'TDA9')->first())
        ->not->toBeNull()
        ->name->toBe('Tienda Nueva')
        ->type->toBe('store')
        ->active->toBeTrue();
});

test('creating a warehouse with a duplicate code fails validation', function () {
    Warehouse::factory()->create(['code' => 'ALM']);

    Livewire::test('pages::warehouses.index')
        ->set('code', 'ALM')
        ->set('name', 'Otro Almacén')
        ->set('type', 'central')
        ->call('save')
        ->assertHasErrors(['code' => 'unique']);
});

test('required fields are validated', function () {
    Livewire::test('pages::warehouses.index')
        ->set('code', '')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['code' => 'required', 'name' => 'required']);
});

test('users can edit an existing warehouse', function () {
    $warehouse = Warehouse::factory()->create(['code' => 'TDA1', 'name' => 'Tienda Vieja']);

    Livewire::test('pages::warehouses.index')
        ->call('openEdit', $warehouse->id)
        ->assertSet('editingId', $warehouse->id)
        ->assertSet('code', 'TDA1')
        ->set('name', 'Tienda Renombrada')
        ->call('save')
        ->assertHasNoErrors();

    expect($warehouse->refresh()->name)->toBe('Tienda Renombrada');
});

test('editing allows keeping the same code', function () {
    $warehouse = Warehouse::factory()->create(['code' => 'ALM']);

    Livewire::test('pages::warehouses.index')
        ->call('openEdit', $warehouse->id)
        ->call('save')
        ->assertHasNoErrors();
});

test('users can toggle a warehouse active state', function () {
    $warehouse = Warehouse::factory()->create(['active' => true]);

    Livewire::test('pages::warehouses.index')
        ->call('toggleActive', $warehouse->id);

    expect($warehouse->refresh()->active)->toBeFalse();

    Livewire::test('pages::warehouses.index')
        ->call('toggleActive', $warehouse->id);

    expect($warehouse->refresh()->active)->toBeTrue();
});

test('users can delete a warehouse with no movement lines', function () {
    $warehouse = Warehouse::factory()->create();

    Livewire::test('pages::warehouses.index')
        ->call('confirmDelete', $warehouse->id)
        ->call('delete');

    expect(Warehouse::find($warehouse->id))->toBeNull();
});

test('delete is blocked when warehouse has movement lines', function () {
    $warehouse = Warehouse::factory()->create();
    $movement = Movement::factory()->confirmed()->create();
    MovementLine::factory()->create([
        'movement_id' => $movement->id,
        'warehouse_id' => $warehouse->id,
    ]);

    Livewire::test('pages::warehouses.index')
        ->call('confirmDelete', $warehouse->id)
        ->call('delete');

    expect(Warehouse::find($warehouse->id))->not->toBeNull();
});
