<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Movement;
use App\Models\User;

class MovementPolicy
{
    /**
     * Admin always passes. Returning null defers to the per-method check.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Owner, UserRole::Seller, UserRole::Warehouse);
    }

    public function view(User $user, Movement $movement): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Owner, UserRole::Seller, UserRole::Warehouse);
    }

    public function update(User $user, Movement $movement): bool
    {
        if ($movement->status !== 'draft') {
            return false;
        }

        return $user->hasRole(UserRole::Owner, UserRole::Warehouse)
            || ($user->hasRole(UserRole::Seller) && $movement->type === 'sale');
    }

    /**
     * Confirming a movement applies its lines to the stock ledger.
     * Sellers can confirm only their own sale movements.
     */
    public function confirm(User $user, Movement $movement): bool
    {
        if ($movement->status !== 'draft') {
            return false;
        }

        if ($user->hasRole(UserRole::Owner, UserRole::Warehouse)) {
            return true;
        }

        return $user->hasRole(UserRole::Seller)
            && $movement->type === 'sale'
            && $movement->created_by === $user->id;
    }

    /**
     * Voiding reverses an already confirmed movement. Restricted to Owner
     * (and Admin via before).
     */
    public function void(User $user, Movement $movement): bool
    {
        return $movement->status === 'confirmed'
            && $user->hasRole(UserRole::Owner);
    }

    public function delete(User $user, Movement $movement): bool
    {
        return $movement->status === 'draft'
            && ($user->hasRole(UserRole::Owner, UserRole::Warehouse)
                || ($user->hasRole(UserRole::Seller) && $movement->created_by === $user->id));
    }

    public function restore(User $user, Movement $movement): bool
    {
        return false;
    }

    public function forceDelete(User $user, Movement $movement): bool
    {
        return false;
    }
}
