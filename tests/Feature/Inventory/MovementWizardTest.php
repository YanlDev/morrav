<?php

use App\Models\Family;
use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->warehouse = Warehouse::factory()->create(['code' => 'ALM']);
});

function initialLoadDraft(): Movement
{
    return Movement::factory()->state([
        'status' => 'draft',
        'type' => 'initial_load',
        'destination_warehouse_id' => test()->warehouse->id,
        'created_by' => test()->user->id,
    ])->create();
}

it('step 1 validates required fields', function () {
    $movement = initialLoadDraft();

    Livewire::test('pages::movements.show', ['movement' => $movement])
        ->call('openWizard')
        ->call('wizardNext')
        ->assertHasErrors(['wizName' => 'required', 'wizFamilyId' => 'required']);
});

it('advances to step 2 after valid step 1', function () {
    $family = Family::factory()->create();
    $movement = initialLoadDraft();

    Livewire::test('pages::movements.show', ['movement' => $movement])
        ->call('openWizard')
        ->set('wizName', 'Silla Milano')
        ->set('wizFamilyId', $family->id)
        ->call('wizardNext')
        ->assertSet('wizardStep', 2);
});

it('advances to step 3 with a single SKU when no variants', function () {
    $family = Family::factory()->create();
    $movement = initialLoadDraft();

    Livewire::test('pages::movements.show', ['movement' => $movement])
        ->call('openWizard')
        ->set('wizName', 'Silla Milano')
        ->set('wizFamilyId', $family->id)
        ->call('wizardNext')
        ->set('wizDraftSalePrice', '890')
        ->call('wizardNext')
        ->assertSet('wizardStep', 3)
        ->assertCount('wizSkus', 1);
});

it('creates multiple variants before advancing to step 3', function () {
    $family = Family::factory()->create();
    $movement = initialLoadDraft();

    $component = Livewire::test('pages::movements.show', ['movement' => $movement])
        ->call('openWizard')
        ->set('wizName', 'Silla Milano')
        ->set('wizFamilyId', $family->id)
        ->set('wizHasVariants', true)
        ->call('wizardNext')
        ->set('wizDraftVariantName', 'Negro / Cuero')
        ->call('wizardAddVariant')
        ->set('wizDraftVariantName', 'Gris / Tela')
        ->call('wizardAddVariant');

    expect($component->get('wizSkus'))->toHaveCount(2);

    $component->call('wizardNext')
        ->assertSet('wizardStep', 3);
});

it('step 3 requires quantity per SKU', function () {
    $family = Family::factory()->create();
    $movement = initialLoadDraft();

    Livewire::test('pages::movements.show', ['movement' => $movement])
        ->call('openWizard')
        ->set('wizName', 'Silla')
        ->set('wizFamilyId', $family->id)
        ->call('wizardNext')
        ->call('wizardNext')
        ->call('wizardSave')
        ->assertHasErrors('wizQuantities.0');
});

it('saves creates product, SKU and movement line with correct stock', function () {
    $family = Family::factory()->create();
    $movement = initialLoadDraft();

    Livewire::test('pages::movements.show', ['movement' => $movement])
        ->call('openWizard')
        ->set('wizName', 'Silla Milano Wizard')
        ->set('wizFamilyId', $family->id)
        ->call('wizardNext')
        ->set('wizDraftSalePrice', '890')
        ->set('wizDraftPurchasePrice', '520')
        ->call('wizardNext')
        ->set('wizQuantities.0', '7')
        ->call('wizardSave')
        ->assertHasNoErrors();

    $product = Product::where('name', 'Silla Milano Wizard')->first();
    expect($product)->not->toBeNull()
        ->and($product->skus)->toHaveCount(1);

    $sku = $product->skus->first();
    expect(MovementLine::where('movement_id', $movement->id)->where('sku_id', $sku->id)->count())->toBe(1);

    // Confirmar y verificar impacto en stock
    $movement->update(['status' => 'confirmed']);
    expect($sku->stockAt($this->warehouse->id))->toBe(7.0);
});

it('saves creates N movement lines for N variants', function () {
    $family = Family::factory()->create();
    $movement = initialLoadDraft();

    Livewire::test('pages::movements.show', ['movement' => $movement])
        ->call('openWizard')
        ->set('wizName', 'Silla Milano Var')
        ->set('wizFamilyId', $family->id)
        ->set('wizHasVariants', true)
        ->call('wizardNext')
        ->set('wizDraftVariantName', 'Negro')
        ->call('wizardAddVariant')
        ->set('wizDraftVariantName', 'Gris')
        ->call('wizardAddVariant')
        ->call('wizardNext')
        ->set('wizQuantities.0', '3')
        ->set('wizQuantities.1', '5')
        ->call('wizardSave')
        ->assertHasNoErrors();

    $product = Product::where('name', 'Silla Milano Var')->first();
    expect($product->skus)->toHaveCount(2);

    $lines = MovementLine::where('movement_id', $movement->id)->get();
    expect($lines)->toHaveCount(2)
        ->and($lines->sum('quantity'))->toBe(8.0);
});

it('backStep decreases wizard step', function () {
    $family = Family::factory()->create();
    $movement = initialLoadDraft();

    Livewire::test('pages::movements.show', ['movement' => $movement])
        ->call('openWizard')
        ->set('wizName', 'X')
        ->set('wizFamilyId', $family->id)
        ->call('wizardNext')
        ->assertSet('wizardStep', 2)
        ->call('wizardBack')
        ->assertSet('wizardStep', 1);
});

it('does not create product when movement is not draft', function () {
    $family = Family::factory()->create();
    $movement = Movement::factory()->state([
        'status' => 'confirmed',
        'type' => 'initial_load',
        'destination_warehouse_id' => $this->warehouse->id,
    ])->create();

    try {
        Livewire::test('pages::movements.show', ['movement' => $movement])
            ->set('wizName', 'NoDebeCrearse')
            ->set('wizFamilyId', $family->id)
            ->set('wizardStep', 3)
            ->set('wizSkus', [['variant_name' => null, 'sale_price' => null, 'purchase_price' => null, 'attributes' => []]])
            ->set('wizQuantities', ['0' => '5'])
            ->call('wizardSave');
    } catch (Throwable) {
        // abort_unless puede lanzar o ser interceptado; lo relevante es que no se cree nada
    }

    expect(Product::where('name', 'NoDebeCrearse')->count())->toBe(0);
});

it('does not create product on movement types other than inbound or initial_load', function () {
    $family = Family::factory()->create();
    $movement = Movement::factory()->state([
        'status' => 'draft',
        'type' => 'outbound',
        'origin_warehouse_id' => $this->warehouse->id,
    ])->create();

    try {
        Livewire::test('pages::movements.show', ['movement' => $movement])
            ->set('wizName', 'NoDebeCrearseOut')
            ->set('wizFamilyId', $family->id)
            ->set('wizardStep', 3)
            ->set('wizSkus', [['variant_name' => null, 'sale_price' => null, 'purchase_price' => null, 'attributes' => []]])
            ->set('wizQuantities', ['0' => '5'])
            ->call('wizardSave');
    } catch (Throwable) {
        // abort_unless puede lanzar o ser interceptado
    }

    expect(Product::where('name', 'NoDebeCrearseOut')->count())->toBe(0);
});
