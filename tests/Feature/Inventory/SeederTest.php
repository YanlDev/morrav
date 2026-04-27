<?php

use App\Models\Family;
use App\Models\Subfamily;
use App\Models\Warehouse;
use Database\Seeders\FamilySeeder;
use Database\Seeders\WarehouseSeeder;

it('seeds the six initial warehouses including TALLER and MERMA', function () {
    $this->seed(WarehouseSeeder::class);

    expect(Warehouse::count())->toBe(6)
        ->and(Warehouse::pluck('code')->all())
        ->toEqualCanonicalizing(['ALM', 'TDA1', 'TDA2', 'TDA3', 'TALLER', 'MERMA']);

    expect(Warehouse::where('code', 'ALM')->first()->type)->toBe('central')
        ->and(Warehouse::where('type', 'store')->count())->toBe(3)
        ->and(Warehouse::where('code', 'TALLER')->first()->type)->toBe('workshop')
        ->and(Warehouse::where('code', 'MERMA')->first()->type)->toBe('scrap');
});

it('is idempotent for warehouses', function () {
    $this->seed(WarehouseSeeder::class);
    $this->seed(WarehouseSeeder::class);

    expect(Warehouse::count())->toBe(6);
});

it('seeds the four Morrav families with subfamilies', function () {
    $this->seed(FamilySeeder::class);

    expect(Family::count())->toBe(4)
        ->and(Subfamily::count())->toBe(24);

    expect(Family::pluck('code')->all())
        ->toEqualCanonicalizing(['OFICINA', 'PELUQUERIA', 'HOGAR', 'INSTITUCIONES']);

    $oficina = Family::where('code', 'OFICINA')->first();
    expect($oficina->subfamilies)->toHaveCount(8);

    $hogar = Family::where('code', 'HOGAR')->first();
    expect($hogar->subfamilies->pluck('code')->all())
        ->toContain('COMEDOR', 'SOFAS', 'DORMITORIO', 'PENDIENTE');
});

it('includes a PENDIENTE subfamily per family as express-creation fallback', function () {
    $this->seed(FamilySeeder::class);

    foreach (['OFICINA', 'PELUQUERIA', 'HOGAR', 'INSTITUCIONES'] as $code) {
        $family = Family::where('code', $code)->first();
        expect($family->subfamilies->where('code', 'PENDIENTE'))->toHaveCount(1);
    }
});

it('is idempotent for families and subfamilies', function () {
    $this->seed(FamilySeeder::class);
    $this->seed(FamilySeeder::class);

    expect(Family::count())->toBe(4)
        ->and(Subfamily::count())->toBe(24);
});
