<?php

use App\Enums\UserRole;
use App\Models\Family;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('guests are redirected from products/create', function () {
    auth()->logout();

    $this->get(route('products.create'))->assertRedirect(route('login'));
});

test('authenticated users see the create wizard page', function () {
    $this->get(route('products.create'))
        ->assertOk()
        ->assertSeeLivewire('pages::products.create');
});

test('the wizard mounts on step 1', function () {
    Livewire::test('pages::products.create')->assertSet('step', 1);
});

test('save redirects to the product ficha page', function () {
    $family = Family::factory()->create();

    Livewire::test('pages::products.create')
        ->set('newName', 'Producto redirigido')
        ->set('newFamilyId', $family->id)
        ->call('nextStep')
        ->call('save')
        ->assertRedirect(route('products.skus', Product::where('name', 'Producto redirigido')->first()));
});

test('save without skus stays on the form and shows a toast (no redirect)', function () {
    $family = Family::factory()->create();

    Livewire::test('pages::products.create')
        ->set('newName', 'Sin variantes')
        ->set('newFamilyId', $family->id)
        ->set('newHasVariants', true)
        ->call('nextStep')
        ->call('save')
        ->assertNoRedirect();

    expect(Product::where('name', 'Sin variantes')->exists())->toBeFalse();
});

test('non-admin users without create permission cannot mount the page', function () {
    $this->actingAs(User::factory()->state(['role' => UserRole::Seller])->create());

    Livewire::test('pages::products.create')
        ->assertForbidden();
});

test('warehouse role can create products', function () {
    $this->actingAs(User::factory()->warehouse()->create());

    $family = Family::factory()->create();

    Livewire::test('pages::products.create')
        ->set('newName', 'Producto del almacenero')
        ->set('newFamilyId', $family->id)
        ->call('nextStep')
        ->call('save')
        ->assertHasNoErrors();

    expect(Product::where('name', 'Producto del almacenero')->exists())->toBeTrue();
});

test('changing family resets draft attributes', function () {
    $f1 = Family::factory()->create();
    $f2 = Family::factory()->create();

    Livewire::test('pages::products.create')
        ->set('newFamilyId', $f1->id)
        ->set('draftAttributes', ['color' => 'rojo'])
        ->set('newFamilyId', $f2->id)
        ->assertSet('draftAttributes', []);
});

test('removeVariant drops the variant from newSkus and reindexes', function () {
    $family = Family::factory()->create();

    $component = Livewire::test('pages::products.create')
        ->set('newName', 'Test')
        ->set('newFamilyId', $family->id)
        ->set('newHasVariants', true)
        ->call('nextStep')
        ->set('draftVariantName', 'A')
        ->call('addVariant')
        ->set('draftVariantName', 'B')
        ->call('addVariant')
        ->set('draftVariantName', 'C')
        ->call('addVariant')
        ->call('removeVariant', 1);

    $skus = $component->get('newSkus');
    expect($skus)->toHaveCount(2)
        ->and(array_keys($skus))->toBe([0, 1])
        ->and($skus[0]['variant_name'])->toBe('A')
        ->and($skus[1]['variant_name'])->toBe('C');
});

test('backStep returns to step 1', function () {
    $family = Family::factory()->create();

    Livewire::test('pages::products.create')
        ->set('newName', 'Test')
        ->set('newFamilyId', $family->id)
        ->call('nextStep')
        ->assertSet('step', 2)
        ->call('backStep')
        ->assertSet('step', 1);
});

test('the products index button links to the create page', function () {
    Livewire::test('pages::products.index')
        ->assertSeeHtml('href="'.route('products.create').'"');
});
