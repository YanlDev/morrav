<?php

use App\Models\Warehouse;
use Illuminate\Database\QueryException;

it('creates a warehouse with all fields', function () {
    $warehouse = Warehouse::factory()->asCentral()->create([
        'code' => 'ALM',
        'name' => 'Almacén Central',
    ]);

    expect($warehouse)
        ->code->toBe('ALM')
        ->name->toBe('Almacén Central')
        ->type->toBe('central')
        ->active->toBeTrue();
});

it('enforces unique warehouse code', function () {
    Warehouse::factory()->create(['code' => 'TDA1']);

    expect(fn () => Warehouse::factory()->create(['code' => 'TDA1']))
        ->toThrow(QueryException::class);
});

it('filters active warehouses via scope', function () {
    Warehouse::factory()->count(2)->create(['active' => true]);
    Warehouse::factory()->create(['active' => false]);

    expect(Warehouse::active()->count())->toBe(2);
});
