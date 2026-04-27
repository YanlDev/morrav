<?php

use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Sku;
use App\Models\User;
use App\Models\Warehouse;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('prints stickers for a single SKU via items param', function () {
    $sku = Sku::factory()->create(['internal_code' => 'SKU-000001']);

    $this->get(route('stickers.print', ['items' => $sku->id.'x3']))
        ->assertOk()
        ->assertSee('SKU-000001')
        ->assertSeeText($sku->product->name);
});

it('generates exactly N stickers according to copies in items param', function () {
    $sku = Sku::factory()->create(['internal_code' => 'SKU-000077']);

    $html = $this->get(route('stickers.print', ['items' => $sku->id.'x5']))->getContent();

    // Cada sticker genera un <div class="sticker">.
    expect(substr_count($html, 'class="sticker"'))->toBe(5);
});

it('prints stickers for multiple SKUs in a single call', function () {
    $sku1 = Sku::factory()->create(['internal_code' => 'SKU-ALPHA']);
    $sku2 = Sku::factory()->create(['internal_code' => 'SKU-BETA']);

    $this->get(route('stickers.print', ['items' => $sku1->id.'x2,'.$sku2->id.'x1']))
        ->assertOk()
        ->assertSee('SKU-ALPHA')
        ->assertSee('SKU-BETA');
});

it('prints stickers for every inbound line of a confirmed movement', function () {
    $warehouse = Warehouse::factory()->create();
    $skuA = Sku::factory()->create(['internal_code' => 'SKU-MOV-A']);
    $skuB = Sku::factory()->create(['internal_code' => 'SKU-MOV-B']);

    $movement = Movement::factory()->state([
        'status' => 'confirmed',
        'type' => 'inbound',
        'destination_warehouse_id' => $warehouse->id,
    ])->create();

    MovementLine::factory()->create([
        'movement_id' => $movement->id,
        'sku_id' => $skuA->id,
        'warehouse_id' => $warehouse->id,
        'direction' => 'in',
        'quantity' => 4,
    ]);
    MovementLine::factory()->create([
        'movement_id' => $movement->id,
        'sku_id' => $skuB->id,
        'warehouse_id' => $warehouse->id,
        'direction' => 'in',
        'quantity' => 2,
    ]);

    $html = $this->get(route('stickers.print', ['movement' => $movement->id]))
        ->assertOk()
        ->getContent();

    // 4 + 2 = 6 etiquetas
    expect(substr_count($html, 'class="sticker"'))->toBe(6);
});

it('does not print stickers for non-confirmed movements', function () {
    $warehouse = Warehouse::factory()->create();
    $sku = Sku::factory()->create();

    $draft = Movement::factory()->state([
        'status' => 'draft',
        'type' => 'inbound',
        'destination_warehouse_id' => $warehouse->id,
    ])->create();

    MovementLine::factory()->create([
        'movement_id' => $draft->id,
        'sku_id' => $sku->id,
        'warehouse_id' => $warehouse->id,
        'direction' => 'in',
        'quantity' => 5,
    ]);

    $html = $this->get(route('stickers.print', ['movement' => $draft->id]))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'class="sticker"'))->toBe(0)
        ->and(str_contains($html, 'No hay etiquetas'))->toBeTrue();
});

it('ignores malformed items param entries', function () {
    $sku = Sku::factory()->create(['internal_code' => 'SKU-VALID']);

    $this->get(route('stickers.print', ['items' => 'garbage,'.$sku->id.'x2,xxx,999999x1']))
        ->assertOk()
        ->assertSee('SKU-VALID');
});

it('redirects /products/by-sku/{code} to the product skus page', function () {
    $sku = Sku::factory()->create(['internal_code' => 'SKU-SCAN-001']);

    $this->get(route('products.by-sku', ['code' => 'SKU-SCAN-001']))
        ->assertRedirect(route('products.skus', $sku->product_id));
});

it('returns 404 when scanning an unknown SKU code', function () {
    $this->get(route('products.by-sku', ['code' => 'SKU-DOES-NOT-EXIST']))
        ->assertNotFound();
});

it('requires authentication to print stickers', function () {
    auth()->logout();

    $sku = Sku::factory()->create();

    $this->get(route('stickers.print', ['items' => $sku->id.'x1']))
        ->assertRedirect(route('login'));
});

it('embeds the by-sku URL inside the QR data attribute so scans reach the app', function () {
    $sku = Sku::factory()->create(['internal_code' => 'SKU-QR-URL']);

    $html = $this->get(route('stickers.print', ['items' => $sku->id.'x1']))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('/products/by-sku/SKU-QR-URL');
});
