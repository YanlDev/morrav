<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Owner, UserRole::Seller, UserRole::Warehouse);
    }

    public function view(User $user, Product $product): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Whether the user is allowed to see purchase costs and margins
     * (purchase price, last cost, margin reports, etc.).
     */
    public function viewCosts(User $user): bool
    {
        return $user->canSeeFinancials();
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Owner, UserRole::Warehouse);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasRole(UserRole::Owner, UserRole::Warehouse);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasRole(UserRole::Owner);
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->hasRole(UserRole::Owner);
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return false;
    }
}
