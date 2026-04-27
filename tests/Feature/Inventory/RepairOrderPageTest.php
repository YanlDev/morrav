<?php

use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\RepairOrder;
use App\Models\Sku;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->central = Warehouse::factory()->create(['code' => 'ALM', 'type' => 'central']);
    $this->workshop = Warehouse::factory()->create(['code' => 'TALLER', 'type' => 'workshop']);
    $this->scrap = Warehouse::factory()->create(['code' => 'MERMA', 'type' => 'scrap']);
});

function seedWorkshopStock(Sku $sku, Warehouse $workshop, float $qty, User $user): void
{
    $movement = Movement::factory()->confirmed()->create([
        'type' => 'transfer',
        'destination_warehouse_id' => $workshop->id,
        'created_by' => $user->id,
        'confirmed_by' => $user->id,
    ]);

    MovementLine::create([
        'movement_id' => $movement->id,
        'sku_id' => $sku->id,
        'warehouse_id' => $workshop->id,
        'direction' => 'in',
        'quantity' => $qty,
    ]);
}

it('renders the repair-orders index for authorized users', function () {
    Livewire::test('pages::repair-orders.index')
        ->assertOk();
});

it('creates a repair order through the Livewire form', function () {
    $sku = Sku::factory()->create();
    seedWorkshopStock($sku, $this->workshop, 6, $this->user);

    Livewire::test('pages::repair-orders.index')
        ->call('openCreate')
        ->set('newLines.0.sku_id', $sku->id)
        ->set('newLines.0.quantity_claimed', 4)
        ->set('newNotes', 'Daño en patas')
        ->call('create');

    $order = RepairOrder::first();

    expect($order)->not->toBeNull()
        ->and($order->status)->toBe('open')
        ->and($order->notes)->toBe('Daño en patas')
        ->and($order->lines)->toHaveCount(1)
        ->and((float) $order->lines->first()->quantity_claimed)->toBe(4.0);
});

it('closes a repair order with completed outcome from the show page', function () {
    $sku = Sku::factory()->create();
    seedWorkshopStock($sku, $this->workshop, 6, $this->user);

    Livewire::test('pages::repair-orders.index')
        ->call('openCreate')
        ->set('newLines.0.sku_id', $sku->id)
        ->set('newLines.0.quantity_claimed', 5)
        ->call('create');

    $order = RepairOrder::with('lines')->first();
    $line = $order->lines->first();

    Livewire::test('pages::repair-orders.show', ['repairOrder' => $order])
        ->call('openClose')
        ->set("closure.{$line->id}.quantity_repaired", 4)
        ->set("closure.{$line->id}.quantity_scrapped", 1)
        ->set("closure.{$line->id}.destination_warehouse_id", $this->central->id)
        ->call('close');

    $order->refresh();

    expect($order->status)->toBe('closed')
        ->and($order->outcome)->toBe('completed');

    expect($sku->stockAt($this->workshop->id))->toBe(1.0)
        ->and($sku->stockAt($this->central->id))->toBe(4.0)
        ->and($sku->stockAt($this->scrap->id))->toBe(1.0);

    // Two movements referenced this order (ALM transfer + MERMA transfer).
    $movs = Movement::where('reference_type', 'repair_order')
        ->where('reference_id', $order->id)
        ->get();
    expect($movs)->toHaveCount(2);
});

it('cancels a repair order without generating movements', function () {
    $sku = Sku::factory()->create();
    seedWorkshopStock($sku, $this->workshop, 4, $this->user);

    Livewire::test('pages::repair-orders.index')
        ->call('openCreate')
        ->set('newLines.0.sku_id', $sku->id)
        ->set('newLines.0.quantity_claimed', 3)
        ->call('create');

    $order = RepairOrder::first();

    Livewire::test('pages::repair-orders.show', ['repairOrder' => $order])
        ->call('openCancel')
        ->set('cancelReason', 'Error al abrir')
        ->call('cancel');

    $order->refresh();

    expect($order->status)->toBe('closed')
        ->and($order->outcome)->toBe('cancelled')
        ->and($order->notes)->toContain('Cancelada');

    expect(Movement::where('reference_type', 'repair_order')->count())->toBe(0);
});

it('does not create a repair order when the form has no valid lines', function () {
    Livewire::test('pages::repair-orders.index')
        ->call('openCreate')
        ->call('create')
        ->assertOk();

    expect(RepairOrder::count())->toBe(0);
});
