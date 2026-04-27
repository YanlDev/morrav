<?php

use App\Models\Attribute;
use App\Models\Family;
use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Product;
use App\Models\Sku;
use App\Models\SkuAttribute;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email_verified_at' => now()]));
});

test('guests are redirected from skus page', function () {
    $product = Product::factory()->create();
    auth()->logout();

    $this->get(route('products.skus', $product))->assertRedirect(route('login'));
});

test('authenticated users can see the skus page', function () {
    $product = Product::factory()->create();

    $this->get(route('products.skus', $product))
        ->assertOk()
        ->assertSeeLivewire('pages::products.skus');
});

test('page shows product name and family context', function () {
    $family = Family::factory()->create(['name' => 'ZZ Sillas']);
    $product = Product::factory()->create([
        'name' => 'Silla Ergonómica',
        'family_id' => $family->id,
    ]);

    $this->get(route('products.skus', $product))
        ->assertSee('Silla Ergonómica')
        ->assertSee('ZZ Sillas');
});

test('only lists skus from the current product', function () {
    $productA = Product::factory()->create();
    $productB = Product::factory()->create();

    Sku::factory()->create(['product_id' => $productA->id, 'internal_code' => 'SKU-A1']);
    Sku::factory()->create(['product_id' => $productB->id, 'internal_code' => 'SKU-B1']);

    Livewire::test('pages::products.skus', ['product' => $productA])
        ->call('setTab', 'variants')
        ->assertSee('SKU-A1')
        ->assertDontSee('SKU-B1');
});

test('users can create a basic SKU without attributes', function () {
    $product = Product::factory()->create();

    Livewire::test('pages::products.skus', ['product' => $product])
        ->call('openCreate')
        ->set('internalCode', 'SKU-TEST')
        ->set('variantName', 'Negro / Mesh')
        ->set('salePrice', 299.90)
        ->set('purchasePrice', 150)
        ->set('status', 'active')
        ->call('save')
        ->assertHasNoErrors();

    expect(Sku::where('internal_code', 'SKU-TEST')->first())
        ->not->toBeNull()
        ->product_id->toBe($product->id)
        ->variant_name->toBe('Negro / Mesh')
        ->status->toBe('active');
});

test('create auto-generates internal code', function () {
    $product = Product::factory()->create();

    $component = Livewire::test('pages::products.skus', ['product' => $product])
        ->call('openCreate');

    expect($component->get('internalCode'))->toStartWith('SKU-');
});

test('internal code must be unique', function () {
    $product = Product::factory()->create();
    Sku::factory()->create(['internal_code' => 'SKU-DUP']);

    Livewire::test('pages::products.skus', ['product' => $product])
        ->set('internalCode', 'SKU-DUP')
        ->call('save')
        ->assertHasErrors(['internalCode' => 'unique']);
});

test('sku uses family attributes form with required validation', function () {
    $family = Family::factory()->create();
    $color = Attribute::factory()->list(['negro', 'blanco'])->create(['code' => 'color']);
    $family->attributes()->attach($color, ['is_required' => true, 'is_key' => true, 'sort_order' => 0]);

    $product = Product::factory()->create(['family_id' => $family->id]);

    Livewire::test('pages::products.skus', ['product' => $product])
        ->call('openCreate')
        ->set('internalCode', 'SKU-A1')
        ->call('save')
        ->assertHasErrors(["attributeValues.{$color->id}" => 'required']);
});

test('sku persists attribute values on save', function () {
    $family = Family::factory()->create();
    $color = Attribute::factory()->list(['negro', 'blanco'])->create(['code' => 'color']);
    $material = Attribute::factory()->create(['code' => 'material', 'type' => 'text']);

    $family->attributes()->attach($color, ['is_required' => true, 'is_key' => true, 'sort_order' => 0]);
    $family->attributes()->attach($material, ['is_required' => false, 'is_key' => false, 'sort_order' => 1]);

    $product = Product::factory()->create(['family_id' => $family->id]);

    Livewire::test('pages::products.skus', ['product' => $product])
        ->call('openCreate')
        ->set('internalCode', 'SKU-A1')
        ->set('variantName', 'Negro / Madera')
        ->set('status', 'active')
        ->set("attributeValues.{$color->id}", 'negro')
        ->set("attributeValues.{$material->id}", 'madera maciza')
        ->call('save')
        ->assertHasNoErrors();

    $sku = Sku::where('internal_code', 'SKU-A1')->first();

    expect($sku->attributeValues)->toHaveCount(2);
    expect($sku->attributeValues->where('attribute_id', $color->id)->first()->value)->toBe('negro');
    expect($sku->attributeValues->where('attribute_id', $material->id)->first()->value)->toBe('madera maciza');
});

test('list attribute rejects values outside options', function () {
    $family = Family::factory()->create();
    $color = Attribute::factory()->list(['negro', 'blanco'])->create(['code' => 'color']);
    $family->attributes()->attach($color, ['is_required' => false, 'is_key' => false, 'sort_order' => 0]);

    $product = Product::factory()->create(['family_id' => $family->id]);

    Livewire::test('pages::products.skus', ['product' => $product])
        ->set('internalCode', 'SKU-X')
        ->set("attributeValues.{$color->id}", 'verde')
        ->call('save')
        ->assertHasErrors(["attributeValues.{$color->id}"]);
});

test('edit loads existing attribute values', function () {
    $family = Family::factory()->create();
    $color = Attribute::factory()->list(['negro', 'blanco'])->create(['code' => 'color']);
    $family->attributes()->attach($color, ['is_required' => false, 'is_key' => false, 'sort_order' => 0]);

    $product = Product::factory()->create(['family_id' => $family->id]);
    $sku = Sku::factory()->create(['product_id' => $product->id]);
    SkuAttribute::factory()->create([
        'sku_id' => $sku->id,
        'attribute_id' => $color->id,
        'value' => 'negro',
    ]);

    Livewire::test('pages::products.skus', ['product' => $product])
        ->call('openEdit', $sku->id)
        ->assertSet('internalCode', $sku->internal_code)
        ->assertSet("attributeValues.{$color->id}", 'negro');
});

test('removing an attribute value on update deletes the row', function () {
    $family = Family::factory()->create();
    $color = Attribute::factory()->create(['code' => 'color', 'type' => 'text']);
    $family->attributes()->attach($color, ['is_required' => false, 'is_key' => false, 'sort_order' => 0]);

    $product = Product::factory()->create(['family_id' => $family->id]);
    $sku = Sku::factory()->create(['product_id' => $product->id]);
    SkuAttribute::factory()->create([
        'sku_id' => $sku->id,
        'attribute_id' => $color->id,
        'value' => 'negro',
    ]);

    Livewire::test('pages::products.skus', ['product' => $product])
        ->call('openEdit', $sku->id)
        ->set("attributeValues.{$color->id}", '')
        ->call('save')
        ->assertHasNoErrors();

    expect(SkuAttribute::where('sku_id', $sku->id)->count())->toBe(0);
});

test('fingerprint differs when key attribute values differ', function () {
    $family = Family::factory()->create();
    $color = Attribute::factory()->list(['negro', 'blanco'])->create(['code' => 'color']);
    $family->attributes()->attach($color, ['is_required' => true, 'is_key' => true, 'sort_order' => 0]);

    $product = Product::factory()->create(['family_id' => $family->id]);

    Livewire::test('pages::products.skus', ['product' => $product])
        ->call('openCreate')
        ->set('internalCode', 'SKU-FP1')
        ->set("attributeValues.{$color->id}", 'negro')
        ->call('save');

    Livewire::test('pages::products.skus', ['product' => $product])
        ->call('openCreate')
        ->set('internalCode', 'SKU-FP2')
        ->set("attributeValues.{$color->id}", 'blanco')
        ->call('save');

    $fp1 = Sku::where('internal_code', 'SKU-FP1')->value('fingerprint');
    $fp2 = Sku::where('internal_code', 'SKU-FP2')->value('fingerprint');

    expect($fp1)->not->toBe($fp2);
});

test('users can delete a sku without movement lines', function () {
    $product = Product::factory()->create();
    $sku = Sku::factory()->create(['product_id' => $product->id]);

    Livewire::test('pages::products.skus', ['product' => $product])
        ->call('confirmDelete', $sku->id)
        ->call('delete');

    expect(Sku::find($sku->id))->toBeNull();
});

test('delete blocked when sku has movement lines', function () {
    $product = Product::factory()->create();
    $sku = Sku::factory()->create(['product_id' => $product->id]);
    $warehouse = Warehouse::factory()->create();
    $movement = Movement::factory()->confirmed()->create();
    MovementLine::factory()->create([
        'movement_id' => $movement->id,
        'sku_id' => $sku->id,
        'warehouse_id' => $warehouse->id,
    ]);

    Livewire::test('pages::products.skus', ['product' => $product])
        ->call('confirmDelete', $sku->id)
        ->call('delete');

    expect(Sku::find($sku->id))->not->toBeNull();
});
