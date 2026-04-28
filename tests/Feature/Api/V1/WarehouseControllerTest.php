<?php

use App\Models\User;
use App\Models\Warehouse;
use Laravel\Sanctum\Sanctum;

it('lists active warehouses ordered by code', function () {
    Warehouse::factory()->create(['code' => 'TDA2', 'type' => 'store', 'active' => true]);
    Warehouse::factory()->create(['code' => 'TDA1', 'type' => 'store', 'active' => true]);
    Warehouse::factory()->create(['code' => 'OFF', 'type' => 'store', 'active' => false]);

    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/v1/warehouses');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.code', 'TDA1')
        ->assertJsonPath('data.1.code', 'TDA2')
        ->assertJsonStructure([
            'data' => [['id', 'code', 'name', 'type', 'active']],
        ]);
});

it('rejects warehouse list without a token', function () {
    $this->getJson('/api/v1/warehouses')->assertUnauthorized();
});
