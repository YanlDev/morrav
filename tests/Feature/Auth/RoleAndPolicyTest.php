<?php

use App\Enums\UserRole;
use App\Models\Attribute;
use App\Models\Family;
use App\Models\Movement;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

it('casts the role column to the UserRole enum', function () {
    $user = User::factory()->admin()->create();

    expect($user->role)->toBe(UserRole::Admin)
        ->and($user->isAdmin())->toBeTrue()
        ->and($user->canSeeFinancials())->toBeTrue()
        ->and($user->canManageSystem())->toBeTrue();
});

it('database column defaults to seller for users created without an explicit role', function () {
    // The factory default is Admin (test convenience); the column default is Seller.
    $id = DB::table('users')->insertGetId([
        'name' => 'No Role',
        'email' => 'norole@example.com',
        'password' => 'hashed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user = User::find($id);

    expect($user->role)->toBe(UserRole::Seller)
        ->and($user->canSeeFinancials())->toBeFalse()
        ->and($user->canManageSystem())->toBeFalse();
});

it('grants Owner full visibility but no system management', function () {
    $owner = User::factory()->owner()->create();

    expect($owner->canSeeFinancials())->toBeTrue()
        ->and($owner->canManageSystem())->toBeFalse();
});

it('exposes hasRole as a multi-arg check', function () {
    $seller = User::factory()->seller()->create();

    expect($seller->hasRole(UserRole::Owner, UserRole::Seller))->toBeTrue()
        ->and($seller->hasRole(UserRole::Admin))->toBeFalse();
});

describe('MovementPolicy', function () {
    test('admin can do everything (before hook)', function () {
        $admin = User::factory()->admin()->create();
        $movement = Movement::factory()->confirmed()->create();

        expect($admin->can('void', $movement))->toBeTrue()
            ->and($admin->can('delete', $movement))->toBeTrue();
    });

    test('only owner can void confirmed movements', function () {
        $movement = Movement::factory()->confirmed()->create();

        expect(User::factory()->owner()->create()->can('void', $movement))->toBeTrue()
            ->and(User::factory()->seller()->create()->can('void', $movement))->toBeFalse()
            ->and(User::factory()->warehouse()->create()->can('void', $movement))->toBeFalse();
    });

    test('seller can confirm only their own sale movements', function () {
        $seller = User::factory()->seller()->create();
        $other = User::factory()->seller()->create();

        $ownSale = Movement::factory()->create([
            'type' => 'sale',
            'status' => 'draft',
            'created_by' => $seller->id,
        ]);
        $othersSale = Movement::factory()->create([
            'type' => 'sale',
            'status' => 'draft',
            'created_by' => $other->id,
        ]);
        $inbound = Movement::factory()->create([
            'type' => 'inbound',
            'status' => 'draft',
            'created_by' => $seller->id,
        ]);

        expect($seller->can('confirm', $ownSale))->toBeTrue()
            ->and($seller->can('confirm', $othersSale))->toBeFalse()
            ->and($seller->can('confirm', $inbound))->toBeFalse();
    });

    test('warehouse user can confirm any draft movement', function () {
        $warehouseUser = User::factory()->warehouse()->create();
        $movement = Movement::factory()->create(['status' => 'draft', 'type' => 'inbound']);

        expect($warehouseUser->can('confirm', $movement))->toBeTrue();
    });

    test('confirmed movements cannot be re-confirmed or updated', function () {
        $owner = User::factory()->owner()->create();
        $confirmed = Movement::factory()->confirmed()->create();

        expect($owner->can('confirm', $confirmed))->toBeFalse()
            ->and($owner->can('update', $confirmed))->toBeFalse();
    });
});

describe('Catalog policies', function () {
    test('only owner (or admin) can delete products, warehouses, families and attributes', function () {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $family = Family::factory()->create();
        $attribute = Attribute::factory()->create();

        $owner = User::factory()->owner()->create();
        $seller = User::factory()->seller()->create();
        $warehouseUser = User::factory()->warehouse()->create();

        expect($owner->can('delete', $product))->toBeTrue()
            ->and($owner->can('delete', $warehouse))->toBeTrue()
            ->and($owner->can('delete', $family))->toBeTrue()
            ->and($owner->can('delete', $attribute))->toBeTrue();

        foreach ([$seller, $warehouseUser] as $nonOwner) {
            expect($nonOwner->can('delete', $product))->toBeFalse()
                ->and($nonOwner->can('delete', $warehouse))->toBeFalse()
                ->and($nonOwner->can('delete', $family))->toBeFalse()
                ->and($nonOwner->can('delete', $attribute))->toBeFalse();
        }
    });

    test('warehouse user can create and update products but not delete', function () {
        $warehouseUser = User::factory()->warehouse()->create();
        $product = Product::factory()->create();

        expect($warehouseUser->can('create', Product::class))->toBeTrue()
            ->and($warehouseUser->can('update', $product))->toBeTrue()
            ->and($warehouseUser->can('delete', $product))->toBeFalse();
    });

    test('seller can view products but not modify the catalog', function () {
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create();

        expect($seller->can('view', $product))->toBeTrue()
            ->and($seller->can('viewAny', Product::class))->toBeTrue()
            ->and($seller->can('create', Product::class))->toBeFalse()
            ->and($seller->can('update', $product))->toBeFalse();
    });

    test('only Admin and Owner can see purchase costs', function () {
        expect(User::factory()->admin()->create()->can('viewCosts', Product::class))->toBeTrue()
            ->and(User::factory()->owner()->create()->can('viewCosts', Product::class))->toBeTrue()
            ->and(User::factory()->seller()->create()->can('viewCosts', Product::class))->toBeFalse()
            ->and(User::factory()->warehouse()->create()->can('viewCosts', Product::class))->toBeFalse();
    });
});

describe('UserPolicy', function () {
    test('only admin can manage other users', function () {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->owner()->create();
        $other = User::factory()->seller()->create();

        expect($admin->can('viewAny', User::class))->toBeTrue()
            ->and($admin->can('create', User::class))->toBeTrue()
            ->and($admin->can('update', $other))->toBeTrue()
            ->and($admin->can('delete', $other))->toBeTrue()
            ->and($owner->can('viewAny', User::class))->toBeFalse()
            ->and($owner->can('create', User::class))->toBeFalse()
            ->and($owner->can('delete', $other))->toBeFalse();
    });

    test('any user can view and update their own profile', function () {
        $seller = User::factory()->seller()->create();

        expect($seller->can('view', $seller))->toBeTrue()
            ->and($seller->can('update', $seller))->toBeTrue();
    });
});

describe('Fortify registration is disabled', function () {
    test('register route does not exist', function () {
        expect(Route::has('register'))->toBeFalse();
    });

    test('GET /register returns 404', function () {
        $this->get('/register')->assertNotFound();
    });

    test('POST /register returns 404', function () {
        $this->post('/register', [
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();
    });
});
