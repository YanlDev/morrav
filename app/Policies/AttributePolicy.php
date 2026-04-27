<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Attribute;
use App\Models\User;

class AttributePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Owner, UserRole::Seller, UserRole::Warehouse);
    }

    public function view(User $user, Attribute $attribute): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Owner);
    }

    public function update(User $user, Attribute $attribute): bool
    {
        return $user->hasRole(UserRole::Owner);
    }

    public function delete(User $user, Attribute $attribute): bool
    {
        return $user->hasRole(UserRole::Owner);
    }

    public function restore(User $user, Attribute $attribute): bool
    {
        return $user->hasRole(UserRole::Owner);
    }

    public function forceDelete(User $user, Attribute $attribute): bool
    {
        return false;
    }
}
