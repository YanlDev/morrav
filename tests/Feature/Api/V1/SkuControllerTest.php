<?php

use App\Enums\UserRole;
use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Product;
use App\Models\Sku;
use App\Models\SkuExternalCode;
use App\Models\User;
use App\Models\Warehouse;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => UserRole::Seller]);
    $this->store1 = Warehouse::factory()->create(['code' => 'TDA1', 'type' => 'store']);
    $this->store2 = Warehouse::factory()->create(['code' => 'TDA2', 'type' => 'store']);
    $this->product = Product::factory()->create(['name' => 'Silla Ejecutiva', 'internal_code' => 'PROD-001']);
    $this->sku = Sku::factory()->create([
        'product_id' => $this->product->id,
        'internal_code' => 'SKU-000001',
        'variant_name' => 'Negro',
        'sale_price' => 199.00,
        'purchase_price' => 90.00,
    ]);
});

/** Mete N unidades del SKU en un almacén con un inbound confirmado. */
function seedStockForLookup(Sku $sku, Warehouse $warehouse, float $qty, User $user): void
{
    $movement = Movement::factory()->confirmed()->create([
        'type' => 'inbound',
        'destination_warehouse_id' => $warehouse->id,
        'created_by' => $user->id,
        'confirmed_by' => $user->id,
    ]);

    MovementLine::create([
        'movement_id' => $movement->id,
        'sku_id' => $sku->id,
        'warehouse_id' => $warehouse->id,
        'direction' => 'in',
        'quantity' => $qty,
    ]);
}

it('looks up a SKU by internal_code and returns stock per warehouse', function () {
    seedStockForLookup($this->sku, $this->store1, 10, $this->user);
    seedStockForLookup($this->sku, $this->store2, 4, $this->user);

    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/skus/lookup?code=SKU-000001');

    $response->assertOk()
        ->assertJsonPath('data.internal_code', 'SKU-000001')
        ->assertJsonPath('data.product.name', 'Silla Ejecutiva')
        ->assertJsonPath('data.sale_price', 199)
        ->assertJsonCount(2, 'data.stock_by_warehouse')
        ->assertJsonPath('data.stock_by_warehouse.0.warehouse.code', 'TDA1')
        ->assertJsonPath('data.stock_by_warehouse.0.qty', 10)
        ->assertJsonPath('data.stock_by_warehouse.1.warehouse.code', 'TDA2')
        ->assertJsonPath('data.stock_by_warehouse.1.qty', 4);
});

it('looks up a SKU by external code', function () {
    SkuExternalCode::create([
        'sku_id' => $this->sku->id,
        'code' => 'EAN-7501031234567',
        'type' => 'barcode',
    ]);

    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/skus/lookup?code=EAN-7501031234567');

    $response->assertOk()->assertJsonPath('data.internal_code', 'SKU-000001');
});

it('strips a URL prefix when looking up (QR with full URL)', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/skus/lookup?code='.urlencode('https://example.com/products/by-sku/SKU-000001'));

    $response->assertOk()->assertJsonPath('data.internal_code', 'SKU-000001');
});

it('returns 422 with a clear message when the code is unknown', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/skus/lookup?code=NOPE-999');

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['code' => 'no encontrado']);
});

it('hides purchase_price from sellers and shows it to admins', function () {
    seedStockForLookup($this->sku, $this->store1, 1, $this->user);

    Sanctum::actingAs($this->user); // seller
    $sellerResponse = $this->getJson('/api/v1/skus/lookup?code=SKU-000001');
    $sellerResponse->assertOk();
    expect($sellerResponse->json('data'))->not->toHaveKey('purchase_price');

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    Sanctum::actingAs($admin);
    $adminResponse = $this->getJson('/api/v1/skus/lookup?code=SKU-000001');
    $adminResponse->assertOk()->assertJsonPath('data.purchase_price', 90);
});

it('returns sku detail by id via show endpoint', function () {
    seedStockForLookup($this->sku, $this->store1, 7, $this->user);

    Sanctum::actingAs($this->user);

    $response = $this->getJson("/api/v1/skus/{$this->sku->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $this->sku->id)
        ->assertJsonPath('data.stock_by_warehouse.0.qty', 7);
});

it('returns 404 for a non-existent sku id', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/skus/99999')->assertNotFound();
});

it('rejects lookup without a token', function () {
    $this->getJson('/api/v1/skus/lookup?code=SKU-000001')->assertUnauthorized();
});

it('validates the code parameter is required', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/skus/lookup')->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});
