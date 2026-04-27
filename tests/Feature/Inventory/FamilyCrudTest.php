<?php

use App\Models\Family;
use App\Models\Product;
use App\Models\Subfamily;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email_verified_at' => now()]));
});

test('guests are redirected from the families index', function () {
    auth()->logout();

    $this->get(route('families.index'))->assertRedirect(route('login'));
});

test('authenticated users can see the families index', function () {
    Family::factory()->create(['code' => 'SILLAS', 'name' => 'Sillas']);

    $this->get(route('families.index'))
        ->assertOk()
        ->assertSeeLivewire('pages::families.index');
});

test('the index lists existing families with counts', function () {
    $family = Family::factory()->create(['code' => 'Z-MESAS', 'name' => 'Z Mesas']);
    Subfamily::factory()->count(3)->create(['family_id' => $family->id]);
    Product::factory()->count(2)->create(['family_id' => $family->id]);

    Livewire::test('pages::families.index')
        ->assertSee('Z-MESAS')
        ->assertSee('Z Mesas')
        ->assertSee('3')
        ->assertSee('2');
});

test('search filters families by code or name', function () {
    Family::factory()->create(['code' => 'Z-SILLAS', 'name' => 'Z Sillas']);
    Family::factory()->create(['code' => 'Z-MESAS', 'name' => 'Z Mesas']);

    Livewire::test('pages::families.index')
        ->set('search', 'MESAS')
        ->assertSee('Z-MESAS')
        ->assertDontSee('Z-SILLAS');
});

test('users can create a new family', function () {
    Livewire::test('pages::families.index')
        ->call('openCreate')
        ->set('code', 'LAMPARAS')
        ->set('name', 'Lámparas')
        ->set('description', 'Iluminación decorativa.')
        ->call('save')
        ->assertHasNoErrors();

    expect(Family::where('code', 'LAMPARAS')->first())
        ->not->toBeNull()
        ->name->toBe('Lámparas')
        ->active->toBeTrue();
});

test('code is auto-uppercased on save', function () {
    Livewire::test('pages::families.index')
        ->set('code', 'lamparas')
        ->set('name', 'Lámparas')
        ->call('save')
        ->assertHasNoErrors();

    expect(Family::where('code', 'LAMPARAS')->exists())->toBeTrue();
});

test('code rejects invalid characters', function () {
    Livewire::test('pages::families.index')
        ->set('code', 'CON ESPACIO')
        ->set('name', 'Test')
        ->call('save')
        ->assertHasErrors(['code' => 'regex']);
});

test('duplicate codes are rejected', function () {
    Family::factory()->create(['code' => 'SILLAS']);

    Livewire::test('pages::families.index')
        ->set('code', 'SILLAS')
        ->set('name', 'Otra')
        ->call('save')
        ->assertHasErrors(['code' => 'unique']);
});

test('users can edit an existing family', function () {
    $family = Family::factory()->create(['code' => 'SILLAS', 'name' => 'Sillas']);

    Livewire::test('pages::families.index')
        ->call('openEdit', $family->id)
        ->assertSet('code', 'SILLAS')
        ->set('name', 'Sillas y butacas')
        ->call('save')
        ->assertHasNoErrors();

    expect($family->refresh()->name)->toBe('Sillas y butacas');
});

test('editing preserves the same code without conflict', function () {
    $family = Family::factory()->create(['code' => 'SILLAS']);

    Livewire::test('pages::families.index')
        ->call('openEdit', $family->id)
        ->call('save')
        ->assertHasNoErrors();
});

test('users can toggle a family active state', function () {
    $family = Family::factory()->create(['active' => true]);

    Livewire::test('pages::families.index')
        ->call('toggleActive', $family->id);

    expect($family->refresh()->active)->toBeFalse();
});

test('users can delete an empty family', function () {
    $family = Family::factory()->create();

    Livewire::test('pages::families.index')
        ->call('confirmDelete', $family->id)
        ->call('delete');

    expect(Family::find($family->id))->toBeNull();
});

test('delete is blocked when family has subfamilies', function () {
    $family = Family::factory()->create();
    Subfamily::factory()->create(['family_id' => $family->id]);

    Livewire::test('pages::families.index')
        ->call('confirmDelete', $family->id)
        ->call('delete');

    expect(Family::find($family->id))->not->toBeNull();
});

test('delete is blocked when family has products', function () {
    $family = Family::factory()->create();
    Product::factory()->create(['family_id' => $family->id]);

    Livewire::test('pages::families.index')
        ->call('confirmDelete', $family->id)
        ->call('delete');

    expect(Family::find($family->id))->not->toBeNull();
});
