<?php

use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Product;
use App\Models\Sku;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('opens the ficha on the overview tab by default', function () {
    $product = Product::factory()->create();

    Livewire::test('pages::products.skus', ['product' => $product])
        ->assertSet('tab', 'overview')
        ->assertSeeHtml('data-test="tab-content-overview"');
});

it('switches between tabs via setTab', function () {
    $product = Product::factory()->create();

    Livewire::test('pages::products.skus', ['product' => $product])
        ->call('setTab', 'stock')
        ->assertSet('tab', 'stock')
        ->assertSeeHtml('data-test="tab-content-stock"')
        ->call('setTab', 'movements')
        ->assertSet('tab', 'movements')
        ->assertSeeHtml('data-test="tab-content-movements"');
});

it('rejects unknown tab names silently and stays on current tab', function () {
    $product = Product::factory()->create();

    Livewire::test('pages::products.skus', ['product' => $product])
        ->call('setTab', 'hacker-tab')
        ->assertSet('tab', 'overview');
});

it('falls back to overview if URL contains an unknown tab', function () {
    $product = Product::factory()->create();

    Livewire::withQueryParams(['tab' => 'nonsense'])
        ->test('pages::products.skus', ['product' => $product])
        ->assertSet('tab', 'overview');
});

describe('totalStock computed', function () {
    test('returns 0 when product has no skus', function () {
        $product = Product::factory()->create();

        expect((float) Livewire::test('pages::products.skus', ['product' => $product])->get('totalStock'))
            ->toBe(0.0);
    });

    test('sums confirmed inbound minus outbound across all skus and warehouses', function () {
        $product = Product::factory()->create();
        $sku1 = Sku::factory()->create(['product_id' => $product->id]);
        $sku2 = Sku::factory()->create(['product_id' => $product->id]);
        $warehouse = Warehouse::factory()->create();

        $movement = Movement::factory()->confirmed()->create();

        // sku1: +10 -3 = 7
        MovementLine::factory()->create([
            'movement_id' => $movement->id,
            'sku_id' => $sku1->id,
            'warehouse_id' => $warehouse->id,
            'direction' => 'in',
            'quantity' => 10,
        ]);
        MovementLine::factory()->create([
            'movement_id' => $movement->id,
            'sku_id' => $sku1->id,
            'warehouse_id' => $warehouse->id,
            'direction' => 'out',
            'quantity' => 3,
        ]);
        // sku2: +5
        MovementLine::factory()->create([
            'movement_id' => $movement->id,
            'sku_id' => $sku2->id,
            'warehouse_id' => $warehouse->id,
            'direction' => 'in',
            'quantity' => 5,
        ]);

        // Draft movement should NOT be counted.
        $draft = Movement::factory()->create(['status' => 'draft']);
        MovementLine::factory()->create([
            'movement_id' => $draft->id,
            'sku_id' => $sku1->id,
            'warehouse_id' => $warehouse->id,
            'direction' => 'in',
            'quantity' => 999,
        ]);

        expect((float) Livewire::test('pages::products.skus', ['product' => $product])->get('totalStock'))
            ->toBe(12.0);
    });
});

describe('stockMatrix computed', function () {
    test('returns no rows when product has no active skus', function () {
        $product = Product::factory()->create();
        Warehouse::factory()->create();

        $matrix = Livewire::test('pages::products.skus', ['product' => $product])->get('stockMatrix');

        expect($matrix)->toHaveKeys(['warehouses', 'rows'])
            ->and($matrix['rows'])->toBe([]);
    });

    test('builds a SKU x warehouse matrix with totals per row', function () {
        $product = Product::factory()->create();
        $sku = Sku::factory()->create(['product_id' => $product->id, 'status' => 'active']);
        $whA = Warehouse::factory()->create(['code' => 'ALM-A', 'active' => true]);
        $whB = Warehouse::factory()->create(['code' => 'ALM-B', 'active' => true]);

        $movement = Movement::factory()->confirmed()->create();
        MovementLine::factory()->create([
            'movement_id' => $movement->id,
            'sku_id' => $sku->id,
            'warehouse_id' => $whA->id,
            'direction' => 'in',
            'quantity' => 10,
        ]);
        MovementLine::factory()->create([
            'movement_id' => $movement->id,
            'sku_id' => $sku->id,
            'warehouse_id' => $whB->id,
            'direction' => 'in',
            'quantity' => 4,
        ]);

        $matrix = Livewire::test('pages::products.skus', ['product' => $product])->get('stockMatrix');

        expect($matrix['rows'])->toHaveCount(1);
        expect($matrix['rows'][0]['qty']['ALM-A'])->toBe(10.0);
        expect($matrix['rows'][0]['qty']['ALM-B'])->toBe(4.0);
        expect($matrix['rows'][0]['total'])->toBe(14.0);
    });

    test('discontinued skus are excluded from the matrix', function () {
        $product = Product::factory()->create();
        Sku::factory()->create(['product_id' => $product->id, 'status' => 'active']);
        Sku::factory()->create(['product_id' => $product->id, 'status' => 'discontinued']);
        Warehouse::factory()->create(['active' => true]);

        $matrix = Livewire::test('pages::products.skus', ['product' => $product])->get('stockMatrix');

        expect($matrix['rows'])->toHaveCount(1);
    });
});

describe('recentMovements computed', function () {
    test('lists confirmed lines for product skus', function () {
        $product = Product::factory()->create();
        $sku = Sku::factory()->create(['product_id' => $product->id]);
        $warehouse = Warehouse::factory()->create();
        $confirmed = Movement::factory()->confirmed()->create();

        MovementLine::factory()->count(3)->create([
            'movement_id' => $confirmed->id,
            'sku_id' => $sku->id,
            'warehouse_id' => $warehouse->id,
            'direction' => 'in',
        ]);

        $recent = Livewire::test('pages::products.skus', ['product' => $product])->get('recentMovements');

        expect($recent)->toHaveCount(3);
    });

    test('excludes movements from other products', function () {
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();
        $skuA = Sku::factory()->create(['product_id' => $productA->id]);
        $skuB = Sku::factory()->create(['product_id' => $productB->id]);
        $warehouse = Warehouse::factory()->create();
        $movement = Movement::factory()->confirmed()->create();

        MovementLine::factory()->create([
            'movement_id' => $movement->id,
            'sku_id' => $skuA->id,
            'warehouse_id' => $warehouse->id,
        ]);
        MovementLine::factory()->create([
            'movement_id' => $movement->id,
            'sku_id' => $skuB->id,
            'warehouse_id' => $warehouse->id,
        ]);

        $recent = Livewire::test('pages::products.skus', ['product' => $productA])->get('recentMovements');

        expect($recent)->toHaveCount(1)
            ->and($recent->first()->sku_id)->toBe($skuA->id);
    });

    test('excludes draft movements', function () {
        $product = Product::factory()->create();
        $sku = Sku::factory()->create(['product_id' => $product->id]);
        $warehouse = Warehouse::factory()->create();
        $draft = Movement::factory()->create(['status' => 'draft']);

        MovementLine::factory()->create([
            'movement_id' => $draft->id,
            'sku_id' => $sku->id,
            'warehouse_id' => $warehouse->id,
        ]);

        $recent = Livewire::test('pages::products.skus', ['product' => $product])->get('recentMovements');

        expect($recent)->toBeEmpty();
    });
});

describe('lastMovementAt computed', function () {
    test('returns null when there are no confirmed movements for the product', function () {
        $product = Product::factory()->create();

        expect(Livewire::test('pages::products.skus', ['product' => $product])->get('lastMovementAt'))
            ->toBeNull();
    });

    test('returns the latest occurred_at among confirmed movements', function () {
        $product = Product::factory()->create();
        $sku = Sku::factory()->create(['product_id' => $product->id]);
        $warehouse = Warehouse::factory()->create();

        $older = Movement::factory()->confirmed()->create(['occurred_at' => now()->subDays(5)]);
        $newer = Movement::factory()->confirmed()->create(['occurred_at' => now()->subDay()]);

        MovementLine::factory()->create([
            'movement_id' => $older->id,
            'sku_id' => $sku->id,
            'warehouse_id' => $warehouse->id,
        ]);
        MovementLine::factory()->create([
            'movement_id' => $newer->id,
            'sku_id' => $sku->id,
            'warehouse_id' => $warehouse->id,
        ]);

        $last = Livewire::test('pages::products.skus', ['product' => $product])->get('lastMovementAt');

        expect($last->isSameDay($newer->occurred_at))->toBeTrue();
    });
});
