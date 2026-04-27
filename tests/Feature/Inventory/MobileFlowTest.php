<?php

use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Product;
use App\Models\Sku;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

beforeEach(function () {
    $this->store = Warehouse::factory()->create(['code' => 'TDA1', 'type' => 'store']);
    Warehouse::factory()->create(['code' => 'TALLER', 'type' => 'workshop']);

    $this->user = User::factory()->seller()->create();
    $this->actingAs($this->user);

    $this->product = Product::factory()->create();
    $this->sku = Sku::factory()->create([
        'product_id' => $this->product->id,
        'internal_code' => 'SKU-000999',
        'sale_price' => 199.50,
    ]);

    // Stock inicial: 8 unidades en TDA1.
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
        'quantity' => 8,
    ]);
});

it('renders the mobile home with 4 action buttons', function () {
    $this->get(route('m.index'))
        ->assertOk()
        ->assertSee('Vender')
        ->assertSee('Consultar')
        ->assertSee('Reportar dañado')
        ->assertSee('Mis ventas');
});

it('looks up a SKU from the QR code on the sell page', function () {
    Livewire::test('pages::m.sell')
        ->set('warehouseId', $this->store->id)
        ->set('scannedCode', 'SKU-000999')
        ->call('lookupSku')
        ->assertSet('step', 'confirm')
        ->assertSet('skuId', $this->sku->id);
});

it('extracts the SKU code from a full URL when scanning', function () {
    Livewire::test('pages::m.sell')
        ->set('warehouseId', $this->store->id)
        ->set('scannedCode', 'http://almacen.test/products/by-sku/SKU-000999')
        ->call('lookupSku')
        ->assertSet('step', 'confirm')
        ->assertSet('skuId', $this->sku->id);
});

it('records a sale through the mobile flow and decreases stock', function () {
    Livewire::test('pages::m.sell')
        ->set('warehouseId', $this->store->id)
        ->set('scannedCode', 'SKU-000999')
        ->call('lookupSku')
        ->set('quantity', 3)
        ->call('confirm')
        ->assertSet('step', 'done');

    expect($this->sku->stockAt($this->store->id))->toBe(5.0);
    expect(Movement::where('type', 'sale')->count())->toBe(1);
});

it('does not record a sale when quantity exceeds available stock', function () {
    Livewire::test('pages::m.sell')
        ->set('warehouseId', $this->store->id)
        ->set('scannedCode', 'SKU-000999')
        ->call('lookupSku')
        ->set('quantity', 100)
        ->call('confirm');

    expect($this->sku->stockAt($this->store->id))->toBe(8.0);
    expect(Movement::where('type', 'sale')->count())->toBe(0);
});

it('looks up SKU on the consultar page and shows stock by warehouse', function () {
    Livewire::test('pages::m.lookup')
        ->set('scannedCode', 'SKU-000999')
        ->call('lookupSku')
        ->assertSet('skuId', $this->sku->id);
});

it('reports damage and moves stock to the workshop from mobile', function () {
    $workshop = Warehouse::where('code', 'TALLER')->first();

    Livewire::test('pages::m.damage')
        ->set('warehouseId', $this->store->id)
        ->set('scannedCode', 'SKU-000999')
        ->call('lookupSku')
        ->set('quantity', 2)
        ->set('notes', 'Pata floja')
        ->call('report');

    expect($this->sku->stockAt($this->store->id))->toBe(6.0)
        ->and($this->sku->stockAt($workshop->id))->toBe(2.0);
});

it('shows todays sales in the history page', function () {
    // Hago una venta a través del flujo móvil.
    Livewire::test('pages::m.sell')
        ->set('warehouseId', $this->store->id)
        ->set('scannedCode', 'SKU-000999')
        ->call('lookupSku')
        ->set('quantity', 2)
        ->call('confirm');

    Livewire::test('pages::m.history')
        ->assertSee('MOV-')
        ->assertSee('TDA1');
});
