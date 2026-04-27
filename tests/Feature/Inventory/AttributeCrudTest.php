<?php

use App\Models\Attribute;
use App\Models\Family;
use App\Models\Sku;
use App\Models\SkuAttribute;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email_verified_at' => now()]));
});

test('guests are redirected from attributes index', function () {
    auth()->logout();

    $this->get(route('attributes.index'))->assertRedirect(route('login'));
});

test('authenticated users can see the attributes index', function () {
    $this->get(route('attributes.index'))
        ->assertOk()
        ->assertSeeLivewire('pages::attributes.index');
});

test('the index lists existing attributes', function () {
    Attribute::factory()->create(['code' => 'zz_color', 'name' => 'ZZ Color']);
    Attribute::factory()->create(['code' => 'zz_material', 'name' => 'ZZ Material']);

    Livewire::test('pages::attributes.index')
        ->assertSee('zz_color')
        ->assertSee('ZZ Color')
        ->assertSee('zz_material')
        ->assertSee('ZZ Material');
});

test('search filters attributes', function () {
    Attribute::factory()->create(['code' => 'zz_color', 'name' => 'ZZ Color']);
    Attribute::factory()->create(['code' => 'zz_material', 'name' => 'ZZ Material']);

    Livewire::test('pages::attributes.index')
        ->set('search', 'color')
        ->assertSee('zz_color')
        ->assertDontSee('zz_material');
});

test('type filter limits the list', function () {
    Attribute::factory()->create(['code' => 'zz_text_attr', 'type' => 'text']);
    Attribute::factory()->number()->create(['code' => 'zz_number_attr']);

    Livewire::test('pages::attributes.index')
        ->set('typeFilter', 'number')
        ->assertSee('zz_number_attr')
        ->assertDontSee('zz_text_attr');
});

test('users can create a text attribute', function () {
    Livewire::test('pages::attributes.index')
        ->call('openCreate')
        ->set('code', 'color')
        ->set('name', 'Color')
        ->set('type', 'text')
        ->call('save')
        ->assertHasNoErrors();

    expect(Attribute::where('code', 'color')->first())
        ->not->toBeNull()
        ->type->toBe('text');
});

test('users can create a number attribute with unit', function () {
    Livewire::test('pages::attributes.index')
        ->call('openCreate')
        ->set('code', 'largo_cm')
        ->set('name', 'Largo')
        ->set('type', 'number')
        ->set('unit', 'cm')
        ->call('save')
        ->assertHasNoErrors();

    expect(Attribute::where('code', 'largo_cm')->first())
        ->not->toBeNull()
        ->unit->toBe('cm');
});

test('users can create a list attribute with parsed options', function () {
    Livewire::test('pages::attributes.index')
        ->call('openCreate')
        ->set('code', 'tamano')
        ->set('name', 'Tamaño')
        ->set('type', 'list')
        ->set('optionsText', "pequeño\nmediano\ngrande\n")
        ->call('save')
        ->assertHasNoErrors();

    expect(Attribute::where('code', 'tamano')->first()->options)
        ->toBe(['pequeño', 'mediano', 'grande']);
});

test('list attribute requires options', function () {
    Livewire::test('pages::attributes.index')
        ->set('code', 'tamano')
        ->set('name', 'Tamaño')
        ->set('type', 'list')
        ->set('optionsText', '')
        ->call('save')
        ->assertHasErrors(['optionsText' => 'required']);
});

test('code is lowercased on save', function () {
    Livewire::test('pages::attributes.index')
        ->set('code', 'COLOR')
        ->set('name', 'Color')
        ->set('type', 'text')
        ->call('save')
        ->assertHasNoErrors();

    expect(Attribute::where('code', 'color')->exists())->toBeTrue();
});

test('code rejects invalid characters', function () {
    Livewire::test('pages::attributes.index')
        ->set('code', '1invalid')
        ->set('name', 'Test')
        ->set('type', 'text')
        ->call('save')
        ->assertHasErrors(['code' => 'regex']);
});

test('duplicate code is rejected', function () {
    Attribute::factory()->create(['code' => 'color']);

    Livewire::test('pages::attributes.index')
        ->set('code', 'color')
        ->set('name', 'Otro')
        ->set('type', 'text')
        ->call('save')
        ->assertHasErrors(['code' => 'unique']);
});

test('users can edit an existing attribute', function () {
    $attribute = Attribute::factory()->create(['code' => 'color', 'name' => 'Color']);

    Livewire::test('pages::attributes.index')
        ->call('openEdit', $attribute->id)
        ->assertSet('code', 'color')
        ->set('name', 'Color principal')
        ->call('save')
        ->assertHasNoErrors();

    expect($attribute->refresh()->name)->toBe('Color principal');
});

test('users can delete an unused attribute', function () {
    $attribute = Attribute::factory()->create();

    Livewire::test('pages::attributes.index')
        ->call('confirmDelete', $attribute->id)
        ->call('delete');

    expect(Attribute::find($attribute->id))->toBeNull();
});

test('delete blocked when attribute is assigned to a family', function () {
    $attribute = Attribute::factory()->create();
    $family = Family::factory()->create();
    $family->attributes()->attach($attribute);

    Livewire::test('pages::attributes.index')
        ->call('confirmDelete', $attribute->id)
        ->call('delete');

    expect(Attribute::find($attribute->id))->not->toBeNull();
});

test('delete blocked when attribute has sku values', function () {
    $attribute = Attribute::factory()->create();
    $sku = Sku::factory()->create();
    SkuAttribute::factory()->create([
        'sku_id' => $sku->id,
        'attribute_id' => $attribute->id,
    ]);

    Livewire::test('pages::attributes.index')
        ->call('confirmDelete', $attribute->id)
        ->call('delete');

    expect(Attribute::find($attribute->id))->not->toBeNull();
});
