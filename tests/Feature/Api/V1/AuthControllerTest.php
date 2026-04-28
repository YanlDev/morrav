<?php

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'seller@example.com',
        'password' => bcrypt('correct-password'),
        'role' => UserRole::Seller,
    ]);
});

it('logs in with valid credentials and returns a sanctum token', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'seller@example.com',
        'password' => 'correct-password',
        'device_name' => 'iPhone 15',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email', 'role', 'role_label'],
        ])
        ->assertJsonPath('user.email', 'seller@example.com')
        ->assertJsonPath('user.role', 'seller');

    expect($this->user->tokens()->where('name', 'iPhone 15')->count())->toBe(1);
});

it('rejects invalid credentials with 422', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'seller@example.com',
        'password' => 'wrong',
        'device_name' => 'iPhone 15',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['email']);
});

it('rejects disabled accounts', function () {
    $this->user->update(['disabled_at' => now()]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'seller@example.com',
        'password' => 'correct-password',
        'device_name' => 'iPhone 15',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => 'deshabilitada']);
});

it('validates required fields on login', function () {
    $response = $this->postJson('/api/v1/auth/login', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password', 'device_name']);
});

it('returns the authenticated user from /me', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonPath('data.email', 'seller@example.com')
        ->assertJsonPath('data.role', 'seller');
});

it('rejects /me without a token', function () {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertUnauthorized();
});

it('logout deletes the current access token', function () {
    $token = $this->user->createToken('iPhone 15')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/logout');

    $response->assertOk()->assertJsonPath('message', 'Sesión cerrada.');
    expect($this->user->tokens()->count())->toBe(0);
});

it('rejects logout without a token', function () {
    $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
});

it('throttles login after 5 failed attempts', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'seller@example.com',
            'password' => 'wrong',
            'device_name' => 'iPhone 15',
        ]);
    }

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'seller@example.com',
        'password' => 'wrong',
        'device_name' => 'iPhone 15',
    ]);

    $response->assertStatus(429);
});
