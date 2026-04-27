<?php

use App\Enums\UserRole;
use App\Models\Movement;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

it('blocks non-admin users from accessing the users page', function () {
    $owner = User::factory()->owner()->create();
    $this->actingAs($owner);

    Livewire::test('pages::users.index')
        ->assertForbidden();
});

it('lets admin open the users page', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test('pages::users.index')
        ->assertOk();
});

it('admin can create a user and the system shows an invite link', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test('pages::users.index')
        ->call('openCreate')
        ->set('name', 'Juan Pérez')
        ->set('email', 'JUAN@example.com')
        ->set('role', 'seller')
        ->call('save')
        ->assertSet('inviteUserId', fn ($id) => is_int($id))
        ->assertSet('inviteLink', fn ($link) => str_contains($link, 'reset-password'));

    $user = User::where('email', 'juan@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Juan Pérez')
        ->and($user->role)->toBe(UserRole::Seller)
        ->and($user->disabled_at)->toBeNull();
});

it('admin can edit name, email and role of an existing user', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->seller()->create();
    $this->actingAs($admin);

    Livewire::test('pages::users.index')
        ->call('openEdit', $other->id)
        ->set('name', 'Nuevo Nombre')
        ->set('role', 'warehouse')
        ->call('save');

    $other->refresh();

    expect($other->name)->toBe('Nuevo Nombre')
        ->and($other->role)->toBe(UserRole::Warehouse);
});

it('admin can disable a user and the user cannot log in afterwards', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->seller()->create();
    $this->actingAs($admin);

    Livewire::test('pages::users.index')
        ->call('confirmDisable', $other->id)
        ->call('toggleDisabled');

    $other->refresh();

    expect($other->disabled_at)->not->toBeNull()
        ->and($other->isDisabled())->toBeTrue();
});

it('admin cannot disable themselves', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test('pages::users.index')
        ->call('confirmDisable', $admin->id)
        ->assertForbidden();
});

it('admin can re-enable a disabled user', function () {
    $admin = User::factory()->admin()->create();
    $disabled = User::factory()->seller()->create(['disabled_at' => now()]);
    $this->actingAs($admin);

    Livewire::test('pages::users.index')
        ->call('confirmDisable', $disabled->id)
        ->call('toggleDisabled');

    $disabled->refresh();

    expect($disabled->disabled_at)->toBeNull()
        ->and($disabled->isEnabled())->toBeTrue();
});

it('admin can delete a clean user with no related records', function () {
    $admin = User::factory()->admin()->create();
    $orphan = User::factory()->seller()->create();
    $this->actingAs($admin);

    Livewire::test('pages::users.index')
        ->call('confirmDelete', $orphan->id)
        ->call('delete');

    expect(User::find($orphan->id))->toBeNull();
});

it('admin cannot delete a user that has movements (FK protection)', function () {
    $admin = User::factory()->admin()->create();
    $worker = User::factory()->warehouse()->create();
    $warehouse = Warehouse::factory()->create();

    Movement::factory()->create([
        'created_by' => $worker->id,
        'destination_warehouse_id' => $warehouse->id,
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::users.index')
        ->call('confirmDelete', $worker->id)
        ->call('delete');

    // Survives because the FK rejected the delete; component caught the exception.
    expect(User::find($worker->id))->not->toBeNull();
});

it('admin cannot delete themselves', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test('pages::users.index')
        ->call('confirmDelete', $admin->id)
        ->assertForbidden();
});

it('admin can regenerate an invite link for an existing user', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->seller()->create();
    $this->actingAs($admin);

    Livewire::test('pages::users.index')
        ->call('regenerateInvite', $other->id)
        ->assertSet('inviteUserId', $other->id)
        ->assertSet('inviteLink', fn ($link) => str_contains($link, 'reset-password'));
});

it('disabled users cannot log in', function () {
    $user = User::factory()->seller()->create([
        'email' => 'blocked@example.com',
        'password' => bcrypt('correct-password'),
        'disabled_at' => now(),
    ]);

    $response = $this->post('/login', [
        'email' => 'blocked@example.com',
        'password' => 'correct-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('enabled users can still log in normally', function () {
    User::factory()->seller()->create([
        'email' => 'ok@example.com',
        'password' => bcrypt('correct-password'),
    ]);

    $response = $this->post('/login', [
        'email' => 'ok@example.com',
        'password' => 'correct-password',
    ]);

    $this->assertAuthenticated();
});

it('rejects creating a user with a duplicate email', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->seller()->create(['email' => 'taken@example.com']);
    $this->actingAs($admin);

    Livewire::test('pages::users.index')
        ->call('openCreate')
        ->set('name', 'Otro')
        ->set('email', 'taken@example.com')
        ->set('role', 'seller')
        ->call('save')
        ->assertHasErrors(['email']);
});
