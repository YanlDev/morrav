<?php

use App\Models\Family;
use App\Models\Product;
use App\Models\Subfamily;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email_verified_at' => now()]));
});

test('opening the manager loads the selected family subfamilies', function () {
    $family = Family::factory()->create(['code' => 'SILLAS']);
    Subfamily::factory()->create(['family_id' => $family->id, 'code' => 'OFICINA', 'name' => 'Oficina']);
    Subfamily::factory()->create(['family_id' => $family->id, 'code' => 'GAMING', 'name' => 'Gaming']);

    Livewire::test('pages::families.index')
        ->call('openSubfamilies', $family->id)
        ->assertSet('subfamilyParentId', $family->id)
        ->assertSee('OFICINA')
        ->assertSee('Oficina')
        ->assertSee('GAMING')
        ->assertSee('Gaming');
});

test('subfamilies only list children of the selected family', function () {
    $sillas = Family::factory()->create();
    $mesas = Family::factory()->create();

    Subfamily::factory()->create(['family_id' => $sillas->id, 'code' => 'A-OFICINA']);
    Subfamily::factory()->create(['family_id' => $mesas->id, 'code' => 'B-COMEDOR']);

    Livewire::test('pages::families.index')
        ->call('openSubfamilies', $sillas->id)
        ->assertSee('A-OFICINA')
        ->assertDontSee('B-COMEDOR');
});

test('users can create a subfamily inside a family', function () {
    $family = Family::factory()->create();

    Livewire::test('pages::families.index')
        ->call('openSubfamilies', $family->id)
        ->call('showAddSubfamily')
        ->set('subfamilyCode', 'ERGONOMICA')
        ->set('subfamilyName', 'Sillas ergonómicas')
        ->call('saveSubfamily')
        ->assertHasNoErrors();

    expect(Subfamily::where('family_id', $family->id)->where('code', 'ERGONOMICA')->exists())->toBeTrue();
});

test('subfamily code is auto-uppercased', function () {
    $family = Family::factory()->create();

    Livewire::test('pages::families.index')
        ->call('openSubfamilies', $family->id)
        ->set('subfamilyCode', 'ergonomica')
        ->set('subfamilyName', 'Ergonómica')
        ->call('saveSubfamily')
        ->assertHasNoErrors();

    expect(Subfamily::where('code', 'ERGONOMICA')->exists())->toBeTrue();
});

test('subfamily code must be unique within the same family', function () {
    $family = Family::factory()->create();
    Subfamily::factory()->create(['family_id' => $family->id, 'code' => 'OFICINA']);

    Livewire::test('pages::families.index')
        ->call('openSubfamilies', $family->id)
        ->set('subfamilyCode', 'OFICINA')
        ->set('subfamilyName', 'Otra')
        ->call('saveSubfamily')
        ->assertHasErrors(['subfamilyCode' => 'unique']);
});

test('same subfamily code is allowed across different families', function () {
    $sillas = Family::factory()->create();
    $mesas = Family::factory()->create();
    Subfamily::factory()->create(['family_id' => $sillas->id, 'code' => 'COMEDOR']);

    Livewire::test('pages::families.index')
        ->call('openSubfamilies', $mesas->id)
        ->set('subfamilyCode', 'COMEDOR')
        ->set('subfamilyName', 'Mesas de comedor')
        ->call('saveSubfamily')
        ->assertHasNoErrors();

    expect(Subfamily::where('code', 'COMEDOR')->count())->toBe(2);
});

test('subfamily code rejects invalid characters', function () {
    $family = Family::factory()->create();

    Livewire::test('pages::families.index')
        ->call('openSubfamilies', $family->id)
        ->set('subfamilyCode', 'con espacio')
        ->set('subfamilyName', 'Test')
        ->call('saveSubfamily')
        ->assertHasErrors(['subfamilyCode' => 'regex']);
});

test('users can edit an existing subfamily', function () {
    $family = Family::factory()->create();
    $subfamily = Subfamily::factory()->create([
        'family_id' => $family->id,
        'code' => 'OFICINA',
        'name' => 'Oficina',
    ]);

    Livewire::test('pages::families.index')
        ->call('openSubfamilies', $family->id)
        ->call('editSubfamily', $subfamily->id)
        ->assertSet('editingSubfamilyId', $subfamily->id)
        ->assertSet('subfamilyCode', 'OFICINA')
        ->set('subfamilyName', 'Oficina ejecutiva')
        ->call('saveSubfamily')
        ->assertHasNoErrors();

    expect($subfamily->refresh()->name)->toBe('Oficina ejecutiva');
});

test('users can toggle subfamily active state', function () {
    $family = Family::factory()->create();
    $subfamily = Subfamily::factory()->create(['family_id' => $family->id, 'active' => true]);

    Livewire::test('pages::families.index')
        ->call('openSubfamilies', $family->id)
        ->call('toggleSubfamilyActive', $subfamily->id);

    expect($subfamily->refresh()->active)->toBeFalse();
});

test('cancel subfamily form resets fields without saving', function () {
    $family = Family::factory()->create();

    Livewire::test('pages::families.index')
        ->call('openSubfamilies', $family->id)
        ->call('showAddSubfamily')
        ->set('subfamilyCode', 'TEMP')
        ->set('subfamilyName', 'Temporal')
        ->call('cancelSubfamilyForm')
        ->assertSet('subfamilyCode', '')
        ->assertSet('subfamilyName', '')
        ->assertSet('showSubfamilyForm', false);

    expect(Subfamily::where('code', 'TEMP')->exists())->toBeFalse();
});

test('users can delete an empty subfamily', function () {
    $family = Family::factory()->create();
    $subfamily = Subfamily::factory()->create(['family_id' => $family->id]);

    Livewire::test('pages::families.index')
        ->call('openSubfamilies', $family->id)
        ->call('confirmDeleteSubfamily', $subfamily->id)
        ->call('deleteSubfamily');

    expect(Subfamily::find($subfamily->id))->toBeNull();
});

test('delete subfamily is blocked when it has products', function () {
    $family = Family::factory()->create();
    $subfamily = Subfamily::factory()->create(['family_id' => $family->id]);
    Product::factory()->create([
        'family_id' => $family->id,
        'subfamily_id' => $subfamily->id,
    ]);

    Livewire::test('pages::families.index')
        ->call('openSubfamilies', $family->id)
        ->call('confirmDeleteSubfamily', $subfamily->id)
        ->call('deleteSubfamily');

    expect(Subfamily::find($subfamily->id))->not->toBeNull();
});
