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
    $this->actingAs(User::factory()->create(['email_verified_at' => now()]));
});

test('guests are redirected from products index', function () {
    auth()->logout();

    $this->get(route('products.index'))->assertRedirect(route('login'));
});

test('authenticated users can see the products index', function () {
    $this->get(route('products.index'))
        ->assertOk()
        ->assertSeeLivewire('pages::products.index');
});

test('the index lists existing products', function () {
    $family = Family::factory()->create(['name' => 'Sillas']);
    Product::factory()->create([
        'internal_code' => 'ZZ-PROD-001',
        'name' => 'Silla Ejecutiva',
        'family_id' => $family->id,
    ]);

    Livewire::test('pages::products.index')
        ->assertSee('ZZ-PROD-001')
        ->assertSee('Silla Ejecutiva')
        ->assertSee('Sillas');
});

test('search filters products by code, name or brand', function () {
    $family = Family::factory()->create();
    Product::factory()->create([
        'internal_code' => 'ZZ-ALPHA',
        'name' => 'Alpha Chair',
        'family_id' => $family->id,
    ]);
    Product::factory()->create([
        'internal_code' => 'ZZ-BETA',
        'name' => 'Beta Table',
        'family_id' => $family->id,
    ]);

    Livewire::test('pages::products.index')
        ->set('search', 'ALPHA')
        ->assertSee('ZZ-ALPHA')
        ->assertDontSee('ZZ-BETA');
});

test('family filter limits results', function () {
    $sillas = Family::factory()->create(['name' => 'ZZ Sillas']);
    $mesas = Family::factory()->create(['name' => 'ZZ Mesas']);
    Product::factory()->create(['internal_code' => 'ZZ-SIL', 'family_id' => $sillas->id]);
    Product::factory()->create(['internal_code' => 'ZZ-MES', 'family_id' => $mesas->id]);

    Livewire::test('pages::products.index')
        ->set('familyFilter', $sillas->id)
        ->assertSee('ZZ-SIL')
        ->assertDontSee('ZZ-MES');
});

test('wizard creates a product with a single SKU when there are no variants', function () {
    $family = Family::factory()->create();

    Livewire::test('pages::products.create')
        ->set('newName', 'Silla Milano')
        ->set('newFamilyId', $family->id)
        ->set('newUnitOfMeasure', 'unit')
        ->set('newStatus', 'active')
        ->call('nextStep')
        ->set('draftSalePrice', '890')
        ->set('draftPurchasePrice', '520')
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::where('name', 'Silla Milano')->first();
    expect($product)->not->toBeNull()
        ->and($product->skus)->toHaveCount(1)
        ->and($product->internal_code)->toStartWith('PROD-')
        ->and($product->status)->toBe('active');

    $sku = $product->skus->first();
    expect($sku->internal_code)->toStartWith('SKU-')
        ->and((float) $sku->sale_price)->toBe(890.0)
        ->and((float) $sku->purchase_price)->toBe(520.0);
});

test('wizard creates multiple variants when switch is on', function () {
    $family = Family::factory()->create();

    Livewire::test('pages::products.create')
        ->set('newName', 'Silla Milano')
        ->set('newFamilyId', $family->id)
        ->set('newHasVariants', true)
        ->call('nextStep')
        ->set('draftVariantName', 'Negro / Cuero')
        ->set('draftSalePrice', '890')
        ->call('addVariant')
        ->set('draftVariantName', 'Gris / Tela')
        ->set('draftSalePrice', '790')
        ->call('addVariant')
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::where('name', 'Silla Milano')->first();
    expect($product->skus)->toHaveCount(2);

    $names = $product->skus->pluck('variant_name')->all();
    expect($names)->toContain('Negro / Cuero', 'Gris / Tela');
});

test('wizard step 1 requires name and family', function () {
    Livewire::test('pages::products.create')
        ->call('nextStep')
        ->assertHasErrors(['newName' => 'required', 'newFamilyId' => 'required']);
});

test('changing family in wizard resets subfamily', function () {
    $family1 = Family::factory()->create();
    $family2 = Family::factory()->create();
    $sub1 = Subfamily::factory()->create(['family_id' => $family1->id]);

    Livewire::test('pages::products.create')
        ->set('newFamilyId', $family1->id)
        ->set('newSubfamilyId', $sub1->id)
        ->set('newFamilyId', $family2->id)
        ->assertSet('newSubfamilyId', null);
});

test('product created via wizard falls back to PENDIENTE subfamily when none is selected', function () {
    $family = Family::factory()->create(['code' => 'OFICINA_TEST']);
    $pending = Subfamily::factory()->create(['family_id' => $family->id, 'code' => 'PENDIENTE']);

    Livewire::test('pages::products.create')
        ->set('newName', 'Producto sin subfamilia')
        ->set('newFamilyId', $family->id)
        ->call('nextStep')
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::where('name', 'Producto sin subfamilia')->first();
    expect($product->subfamily_id)->toBe($pending->id);
});

test('fingerprint is generated when wizard saves', function () {
    $family = Family::factory()->create();

    Livewire::test('pages::products.create')
        ->set('newName', 'Silla Azul')
        ->set('newFamilyId', $family->id)
        ->call('nextStep')
        ->call('save');

    expect(Product::where('name', 'Silla Azul')->first()->fingerprint)
        ->not->toBeNull()
        ->toHaveLength(64);
});

test('created_by is populated when wizard saves', function () {
    $family = Family::factory()->create();

    Livewire::test('pages::products.create')
        ->set('newName', 'Producto con creador')
        ->set('newFamilyId', $family->id)
        ->call('nextStep')
        ->call('save');

    expect(Product::where('name', 'Producto con creador')->first()->created_by)
        ->toBe(auth()->id());
});

test('users can edit a product via simple edit modal', function () {
    $family = Family::factory()->create();
    $product = Product::factory()->create(['name' => 'Viejo nombre', 'family_id' => $family->id]);

    Livewire::test('pages::products.index')
        ->call('openEdit', $product->id)
        ->set('editName', 'Nuevo nombre')
        ->call('saveEdit')
        ->assertHasNoErrors();

    expect($product->refresh()->name)->toBe('Nuevo nombre');
});

test('openEdit loads existing SKUs into editSkus', function () {
    $family = Family::factory()->create();
    $product = Product::factory()->create(['family_id' => $family->id]);
    $skuA = Sku::factory()->create(['product_id' => $product->id, 'variant_name' => 'Negro']);
    $skuB = Sku::factory()->create(['product_id' => $product->id, 'variant_name' => 'Gris']);

    $component = Livewire::test('pages::products.index')
        ->call('openEdit', $product->id);

    $loaded = $component->get('editSkus');

    expect($loaded)->toHaveCount(2);

    $variantNames = array_column($loaded, 'variant_name');
    expect($variantNames)->toContain('Negro', 'Gris');
});

test('saveEdit updates existing SKUs inline', function () {
    $family = Family::factory()->create();
    $product = Product::factory()->create(['family_id' => $family->id]);
    $sku = Sku::factory()->create([
        'product_id' => $product->id,
        'variant_name' => 'Viejo',
        'sale_price' => 100,
    ]);

    $component = Livewire::test('pages::products.index')
        ->call('openEdit', $product->id);

    $editSkus = $component->get('editSkus');
    $editSkus[0]['variant_name'] = 'Nuevo nombre variante';
    $editSkus[0]['sale_price'] = '250.50';

    $component->set('editSkus', $editSkus)
        ->call('saveEdit')
        ->assertHasNoErrors();

    $sku->refresh();
    expect($sku->variant_name)->toBe('Nuevo nombre variante')
        ->and((float) $sku->sale_price)->toBe(250.5);
});

test('addEditVariant appends a new row without persisting', function () {
    $family = Family::factory()->create();
    $product = Product::factory()->create(['family_id' => $family->id]);

    $component = Livewire::test('pages::products.index')
        ->call('openEdit', $product->id)
        ->call('addEditVariant');

    expect($component->get('editSkus'))->toHaveCount(1);

    // No se persiste hasta que se llame saveEdit
    expect($product->skus()->count())->toBe(0);
});

test('saveEdit persists new inline variants', function () {
    $family = Family::factory()->create();
    $product = Product::factory()->create(['family_id' => $family->id]);

    $component = Livewire::test('pages::products.index')
        ->call('openEdit', $product->id)
        ->call('addEditVariant');

    $editSkus = $component->get('editSkus');
    $editSkus[0]['variant_name'] = 'Nueva variante';
    $editSkus[0]['sale_price'] = '500';

    $component->set('editSkus', $editSkus)
        ->call('saveEdit')
        ->assertHasNoErrors();

    expect($product->skus()->count())->toBe(1);
    $newSku = $product->skus()->first();
    expect($newSku->variant_name)->toBe('Nueva variante')
        ->and($newSku->internal_code)->toStartWith('SKU-');
});

test('removeEditSku hard deletes a SKU that has no movement lines', function () {
    $family = Family::factory()->create();
    $product = Product::factory()->create(['family_id' => $family->id]);
    $sku = Sku::factory()->create(['product_id' => $product->id]);

    Livewire::test('pages::products.index')
        ->call('openEdit', $product->id)
        ->call('removeEditSku', 0);

    // Hard delete: el SKU ya no existe ni con withTrashed
    expect(Sku::withTrashed()->find($sku->id))->toBeNull();
});

test('removeEditSku blocks deletion when SKU has movement lines', function () {
    $family = Family::factory()->create();
    $product = Product::factory()->create(['family_id' => $family->id]);
    $sku = Sku::factory()->create(['product_id' => $product->id]);
    $warehouse = Warehouse::factory()->create();
    $movement = Movement::factory()->state([
        'type' => 'inbound',
        'status' => 'confirmed',
        'destination_warehouse_id' => $warehouse->id,
    ])->create();
    MovementLine::factory()->create([
        'movement_id' => $movement->id,
        'sku_id' => $sku->id,
        'warehouse_id' => $warehouse->id,
        'direction' => 'in',
        'quantity' => 5,
    ]);

    Livewire::test('pages::products.index')
        ->call('openEdit', $product->id)
        ->call('removeEditSku', 0);

    // SKU sigue existiendo
    expect(Sku::find($sku->id))->not->toBeNull();
});

test('removing a new (unsaved) variant just drops it from the array', function () {
    $family = Family::factory()->create();
    $product = Product::factory()->create(['family_id' => $family->id]);

    Livewire::test('pages::products.index')
        ->call('openEdit', $product->id)
        ->call('addEditVariant')
        ->call('addEditVariant')
        ->call('removeEditSku', 0)
        ->assertCount('editSkus', 1);
});

test('users can delete a product without skus', function () {
    $product = Product::factory()->create();

    Livewire::test('pages::products.index')
        ->call('confirmDelete', $product->id)
        ->call('delete');

    expect(Product::find($product->id))->toBeNull();
});

test('delete blocked when product has skus', function () {
    $product = Product::factory()->create();
    Sku::factory()->create(['product_id' => $product->id]);

    Livewire::test('pages::products.index')
        ->call('confirmDelete', $product->id)
        ->call('delete');

    expect(Product::find($product->id))->not->toBeNull();
});

test('subfamily filter narrows by subfamily', function () {
    $family = Family::factory()->create();
    $subA = Subfamily::factory()->create(['family_id' => $family->id]);
    $subB = Subfamily::factory()->create(['family_id' => $family->id]);

    Product::factory()->create(['internal_code' => 'ZZ-SUB-A', 'family_id' => $family->id, 'subfamily_id' => $subA->id]);
    Product::factory()->create(['internal_code' => 'ZZ-SUB-B', 'family_id' => $family->id, 'subfamily_id' => $subB->id]);

    Livewire::test('pages::products.index')
        ->set('familyFilter', (string) $family->id)
        ->set('subfamilyFilter', (string) $subA->id)
        ->assertSee('ZZ-SUB-A')
        ->assertDontSee('ZZ-SUB-B');
});

test('creator filter narrows by user who created the product', function () {
    $creator1 = User::factory()->create();
    $creator2 = User::factory()->create();
    $family = Family::factory()->create();

    Product::factory()->create(['internal_code' => 'ZZ-U1', 'family_id' => $family->id, 'created_by' => $creator1->id]);
    Product::factory()->create(['internal_code' => 'ZZ-U2', 'family_id' => $family->id, 'created_by' => $creator2->id]);

    Livewire::test('pages::products.index')
        ->set('creatorFilter', (string) $creator1->id)
        ->assertSee('ZZ-U1')
        ->assertDontSee('ZZ-U2');
});

test('period filter narrows by created_at', function () {
    $family = Family::factory()->create();

    Product::factory()->create(['internal_code' => 'ZZ-OLD', 'family_id' => $family->id, 'created_at' => now()->subDays(60)]);
    Product::factory()->create(['internal_code' => 'ZZ-RECENT', 'family_id' => $family->id, 'created_at' => now()->subDay()]);

    Livewire::test('pages::products.index')
        ->set('periodFilter', '7d')
        ->assertSee('ZZ-RECENT')
        ->assertDontSee('ZZ-OLD');
});

test('changing family filter resets subfamily filter', function () {
    $family1 = Family::factory()->create();
    $family2 = Family::factory()->create();
    $sub = Subfamily::factory()->create(['family_id' => $family1->id]);

    Livewire::test('pages::products.index')
        ->set('familyFilter', (string) $family1->id)
        ->set('subfamilyFilter', (string) $sub->id)
        ->set('familyFilter', (string) $family2->id)
        ->assertSet('subfamilyFilter', '');
});
