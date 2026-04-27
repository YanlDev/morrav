<?php

use App\Models\Family;
use App\Models\FamilyAttribute;
use Database\Seeders\AttributeSeeder;
use Database\Seeders\FamilyAttributeSeeder;
use Database\Seeders\FamilySeeder;

it('assigns the 4 simple attributes to each family', function () {
    $this->seed([FamilySeeder::class, AttributeSeeder::class, FamilyAttributeSeeder::class]);

    foreach (['OFICINA', 'PELUQUERIA', 'HOGAR', 'INSTITUCIONES'] as $code) {
        $family = Family::where('code', $code)->first();
        expect($family->attributes()->count())
            ->toBe(4, "Familia {$code} debe tener 4 atributos.");
    }
});

it('keeps every attribute optional so alta exprés never blocks', function () {
    $this->seed([FamilySeeder::class, AttributeSeeder::class, FamilyAttributeSeeder::class]);

    expect(FamilyAttribute::where('is_required', true)->count())->toBe(0);
});

it('marks color and material as key attributes', function () {
    $this->seed([FamilySeeder::class, AttributeSeeder::class, FamilyAttributeSeeder::class]);

    $oficina = Family::where('code', 'OFICINA')->first();

    $color = $oficina->attributes()->where('code', 'color')->first();
    $material = $oficina->attributes()->where('code', 'material')->first();
    $modelo = $oficina->attributes()->where('code', 'modelo')->first();

    expect($color->pivot->is_key)->toBeTrue()
        ->and($material->pivot->is_key)->toBeTrue()
        ->and($modelo->pivot->is_key)->toBeFalse();
});

it('is idempotent', function () {
    $this->seed([FamilySeeder::class, AttributeSeeder::class]);
    $this->seed(FamilyAttributeSeeder::class);
    $this->seed(FamilyAttributeSeeder::class);

    $total = Family::all()->sum(fn ($f) => $f->attributes()->count());

    expect($total)->toBe(4 * 4);
});

it('skips silently if a family or attribute is missing', function () {
    $this->seed(FamilyAttributeSeeder::class);

    expect(FamilyAttribute::count())->toBe(0);
});
