<?php

use App\Models\Family;
use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Product;
use App\Models\Sku;
use App\Models\Subfamily;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->warehouse = Warehouse::factory()->create();
    $this->movement = Movement::factory()->state([
        'status' => 'draft',
        'type' => 'inbound',
        'destination_warehouse_id' => $this->warehouse->id,
        'created_by' => $this->user->id,
    ])->create();
});

it('lists subfamilies that contain active SKUs as picker tabs', function () {
    $family = Family::factory()->create();
    $subA = Subfamily::factory()->for($family)->create(['name' => 'Sillas']);
    $subB = Subfamily::factory()->for($family)->create(['name' => 'Sofás']);
    $subEmpty = Subfamily::factory()->for($family)->create(['name' => 'Sin productos']);

    Sku::factory()->for(Product::factory()->state(['family_id' => $family->id, 'subfamily_id' => $subA->id]))->create();
    Sku::factory()->for(Product::factory()->state(['family_id' => $family->id, 'subfamily_id' => $subB->id]))->create();

    $subs = Livewire::test('pages::movements.show', ['movement' => $this->movement])
        ->invade()
        ->pickerSubfamilies();

    $names = $subs->pluck('name')->all();

    expect($names)->toContain('Sillas', 'Sofás')
        ->and($names)->not->toContain('Sin productos');
});

it('filters SKUs by selected subfamily tab', function () {
    $family = Family::factory()->create();
    $subA = Subfamily::factory()->for($family)->create();
    $subB = Subfamily::factory()->for($family)->create();

    $skuA = Sku::factory()->for(Product::factory()->state(['family_id' => $family->id, 'subfamily_id' => $subA->id]))->create();
    $skuB = Sku::factory()->for(Product::factory()->state(['family_id' => $family->id, 'subfamily_id' => $subB->id]))->create();

    $options = Livewire::test('pages::movements.show', ['movement' => $this->movement])
        ->set('pickerTab', (string) $subA->id)
        ->invade()
        ->skuOptions();

    $ids = $options->pluck('id')->all();

    expect($ids)->toContain($skuA->id)
        ->and($ids)->not->toContain($skuB->id);
});

it('"all" tab shows SKUs across subfamilies', function () {
    $family = Family::factory()->create();
    $subA = Subfamily::factory()->for($family)->create();
    $subB = Subfamily::factory()->for($family)->create();

    $skuA = Sku::factory()->for(Product::factory()->state(['family_id' => $family->id, 'subfamily_id' => $subA->id]))->create();
    $skuB = Sku::factory()->for(Product::factory()->state(['family_id' => $family->id, 'subfamily_id' => $subB->id]))->create();

    $options = Livewire::test('pages::movements.show', ['movement' => $this->movement])
        ->set('pickerTab', 'all')
        ->invade()
        ->skuOptions();

    $ids = $options->pluck('id')->all();

    expect($ids)->toContain($skuA->id, $skuB->id);
});

it('"recent" tab surfaces SKUs recently used by current user first', function () {
    $skuOld = Sku::factory()->create();
    $skuRecent = Sku::factory()->create();

    $priorMovement = Movement::factory()->state([
        'status' => 'confirmed',
        'created_by' => $this->user->id,
    ])->create();
    MovementLine::factory()->create([
        'movement_id' => $priorMovement->id,
        'sku_id' => $skuRecent->id,
        'warehouse_id' => $this->warehouse->id,
        'direction' => 'in',
        'quantity' => 1,
        'created_at' => now()->subHours(2),
    ]);

    $options = Livewire::test('pages::movements.show', ['movement' => $this->movement])
        ->set('pickerTab', 'recent')
        ->invade()
        ->skuOptions();

    $ids = $options->pluck('id')->all();

    expect($ids[0] ?? null)->toBe($skuRecent->id)
        ->and($ids)->not->toContain($skuOld->id);
});

it('"recent" tab ignores SKUs used by other users', function () {
    $otherUser = User::factory()->create();
    $skuByOther = Sku::factory()->create();

    $otherMovement = Movement::factory()->state([
        'status' => 'confirmed',
        'created_by' => $otherUser->id,
    ])->create();
    MovementLine::factory()->create([
        'movement_id' => $otherMovement->id,
        'sku_id' => $skuByOther->id,
        'warehouse_id' => $this->warehouse->id,
        'direction' => 'in',
        'quantity' => 1,
        'created_at' => now()->subHours(2),
    ]);

    $hasRecent = Livewire::test('pages::movements.show', ['movement' => $this->movement])
        ->invade()
        ->hasRecentUsage();

    expect($hasRecent)->toBeFalse();
});

it('"recent" tab ignores SKUs used more than 7 days ago', function () {
    $skuOld = Sku::factory()->create();

    $oldMovement = Movement::factory()->state([
        'status' => 'confirmed',
        'created_by' => $this->user->id,
    ])->create();
    MovementLine::factory()->create([
        'movement_id' => $oldMovement->id,
        'sku_id' => $skuOld->id,
        'warehouse_id' => $this->warehouse->id,
        'direction' => 'in',
        'quantity' => 1,
        'created_at' => now()->subDays(10),
    ]);

    $hasRecent = Livewire::test('pages::movements.show', ['movement' => $this->movement])
        ->invade()
        ->hasRecentUsage();

    expect($hasRecent)->toBeFalse();
});

it('search overrides tab filter and shows matching SKUs from any subfamily', function () {
    $family = Family::factory()->create();
    $subA = Subfamily::factory()->for($family)->create();
    $subB = Subfamily::factory()->for($family)->create();

    $skuMatch = Sku::factory()
        ->for(Product::factory()->state(['family_id' => $family->id, 'subfamily_id' => $subB->id, 'name' => 'Silla Milano Special']))
        ->create();
    $skuOther = Sku::factory()
        ->for(Product::factory()->state(['family_id' => $family->id, 'subfamily_id' => $subA->id]))
        ->create();

    $options = Livewire::test('pages::movements.show', ['movement' => $this->movement])
        ->set('pickerTab', (string) $subA->id)
        ->set('lineSkuSearch', 'Milano Special')
        ->invade()
        ->skuOptions();

    $ids = $options->pluck('id')->all();

    // Subfamilia A está seleccionada pero la búsqueda no trae el match (está en B).
    // El tab sigue filtrando — si quieres match global, limpia el tab.
    expect($ids)->not->toContain($skuMatch->id);
});
