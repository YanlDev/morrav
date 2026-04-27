<?php

use App\Models\Attribute;
use App\Models\Family;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email_verified_at' => now()]));
});

test('opening the manager sets the parent family', function () {
    $family = Family::factory()->create();

    Livewire::test('pages::families.index')
        ->call('openFamilyAttributes', $family->id)
        ->assertSet('attributeParentId', $family->id);
});

test('available attributes excludes already assigned ones', function () {
    $family = Family::factory()->create();
    $color = Attribute::factory()->create(['code' => 'zz_color']);
    $material = Attribute::factory()->create(['code' => 'zz_material']);
    $family->attributes()->attach($color);

    $component = Livewire::test('pages::families.index')
        ->call('openFamilyAttributes', $family->id);

    $available = $component->instance()->availableAttributes;
    $assigned = $component->instance()->familyAttributes;

    expect($assigned->pluck('code')->all())->toContain('zz_color');
    expect($available->pluck('code')->all())
        ->toContain('zz_material')
        ->not->toContain('zz_color');
});

test('users can attach an attribute to a family', function () {
    $family = Family::factory()->create();
    $color = Attribute::factory()->create(['code' => 'color']);

    Livewire::test('pages::families.index')
        ->call('openFamilyAttributes', $family->id)
        ->set('attachingAttributeId', $color->id)
        ->call('attachAttribute')
        ->assertHasNoErrors();

    expect($family->attributes()->count())->toBe(1);
    $pivot = $family->attributes()->first()->pivot;
    expect($pivot->is_required)->toBeFalse()
        ->and($pivot->is_key)->toBeFalse()
        ->and($pivot->sort_order)->toBe(0);
});

test('attaching without selecting an attribute fails validation', function () {
    $family = Family::factory()->create();

    Livewire::test('pages::families.index')
        ->call('openFamilyAttributes', $family->id)
        ->call('attachAttribute')
        ->assertHasErrors(['attachingAttributeId']);
});

test('attaching the same attribute twice does not duplicate', function () {
    $family = Family::factory()->create();
    $color = Attribute::factory()->create();
    $family->attributes()->attach($color);

    Livewire::test('pages::families.index')
        ->call('openFamilyAttributes', $family->id)
        ->set('attachingAttributeId', $color->id)
        ->call('attachAttribute');

    expect($family->attributes()->count())->toBe(1);
});

test('users can toggle is_required flag', function () {
    $family = Family::factory()->create();
    $color = Attribute::factory()->create();
    $family->attributes()->attach($color, ['is_required' => false]);

    Livewire::test('pages::families.index')
        ->call('openFamilyAttributes', $family->id)
        ->call('toggleRequired', $color->id);

    expect($family->attributes()->first()->pivot->is_required)->toBeTrue();
});

test('users can toggle is_key flag', function () {
    $family = Family::factory()->create();
    $color = Attribute::factory()->create();
    $family->attributes()->attach($color, ['is_key' => false]);

    Livewire::test('pages::families.index')
        ->call('openFamilyAttributes', $family->id)
        ->call('toggleKey', $color->id);

    expect($family->attributes()->first()->pivot->is_key)->toBeTrue();
});

test('users can detach an attribute', function () {
    $family = Family::factory()->create();
    $color = Attribute::factory()->create();
    $family->attributes()->attach($color);

    Livewire::test('pages::families.index')
        ->call('openFamilyAttributes', $family->id)
        ->call('detachAttribute', $color->id);

    expect($family->attributes()->count())->toBe(0);
});

test('attribute count appears in families list', function () {
    $family = Family::factory()->create(['code' => 'ZZ-TEST']);
    Attribute::factory()->count(3)->create()->each(fn ($attr) => $family->attributes()->attach($attr));

    Livewire::test('pages::families.index')
        ->assertSee('ZZ-TEST')
        ->assertSee('3');
});

test('attribute assignments are isolated between families', function () {
    $sillas = Family::factory()->create();
    $mesas = Family::factory()->create();
    $color = Attribute::factory()->create(['code' => 'zz_color']);
    $material = Attribute::factory()->create(['code' => 'zz_material']);

    $sillas->attributes()->attach($color);
    $mesas->attributes()->attach($material);

    $component = Livewire::test('pages::families.index')
        ->call('openFamilyAttributes', $sillas->id);

    expect($component->instance()->familyAttributes->pluck('code')->all())
        ->toContain('zz_color')
        ->not->toContain('zz_material');
});
