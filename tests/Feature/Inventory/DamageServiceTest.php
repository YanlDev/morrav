<?php

use App\Enums\DamageReason;
use App\Models\DamageReport;
use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Sku;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Damage\DamageService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = Warehouse::factory()->create(['code' => 'TDA1', 'type' => 'store']);
    $this->workshop = Warehouse::factory()->create(['code' => 'TALLER', 'type' => 'workshop']);
    $this->sku = Sku::factory()->create();
    $this->service = app(DamageService::class);
});

/**
 * Mete N unidades del SKU en la tienda con un inbound confirmado.
 */
function seedStockForDamage(Sku $sku, Warehouse $warehouse, float $qty, User $user): void
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

it('creates a damage report and a confirmed transfer to the workshop', function () {
    seedStockForDamage($this->sku, $this->store, 10, $this->user);

    $report = $this->service->report(
        user: $this->user,
        sku: $this->sku,
        warehouse: $this->store,
        quantity: 3,
        reason: DamageReason::Broken,
        notes: 'Pata rota',
    );

    expect($report)->toBeInstanceOf(DamageReport::class)
        ->and((float) $report->quantity)->toBe(3.0)
        ->and($report->reason_code)->toBe(DamageReason::Broken)
        ->and($report->reason_notes)->toBe('Pata rota')
        ->and($report->reported_by)->toBe($this->user->id)
        ->and($report->repair_order_line_id)->toBeNull();

    $movement = $report->movement;
    expect($movement->type)->toBe('transfer')
        ->and($movement->status)->toBe('confirmed')
        ->and($movement->reference_type)->toBe('damage_report')
        ->and($movement->reference_id)->toBe($report->id)
        ->and($movement->lines)->toHaveCount(2);

    expect($this->sku->stockAt($this->store->id))->toBe(7.0)
        ->and($this->sku->stockAt($this->workshop->id))->toBe(3.0);
});

it('rejects when quantity exceeds available stock', function () {
    seedStockForDamage($this->sku, $this->store, 2, $this->user);

    expect(fn () => $this->service->report(
        user: $this->user,
        sku: $this->sku,
        warehouse: $this->store,
        quantity: 5,
    ))->toThrow(RuntimeException::class, 'Stock insuficiente');

    expect($this->sku->stockAt($this->store->id))->toBe(2.0);
    expect(DamageReport::count())->toBe(0);
});

it('rejects zero or negative quantities', function () {
    seedStockForDamage($this->sku, $this->store, 5, $this->user);

    expect(fn () => $this->service->report($this->user, $this->sku, $this->store, 0))
        ->toThrow(RuntimeException::class, 'mayor a cero');

    expect(fn () => $this->service->report($this->user, $this->sku, $this->store, -1))
        ->toThrow(RuntimeException::class, 'mayor a cero');
});

it('rejects when no workshop warehouse is configured', function () {
    $this->workshop->update(['active' => false]);
    seedStockForDamage($this->sku, $this->store, 5, $this->user);

    expect(fn () => $this->service->report($this->user, $this->sku, $this->store, 1))
        ->toThrow(RuntimeException::class, 'taller');
});

it('works without a reason code', function () {
    seedStockForDamage($this->sku, $this->store, 5, $this->user);

    $report = $this->service->report($this->user, $this->sku, $this->store, 1);

    expect($report->reason_code)->toBeNull()
        ->and($report->movement->reason)->toBe('Daño reportado');
});

it('sets the pending scope correctly', function () {
    seedStockForDamage($this->sku, $this->store, 5, $this->user);

    $report = $this->service->report($this->user, $this->sku, $this->store, 1);

    expect(DamageReport::pending()->count())->toBe(1)
        ->and(DamageReport::claimed()->count())->toBe(0)
        ->and($report->isPending())->toBeTrue();
});
