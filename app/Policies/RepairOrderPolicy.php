<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\RepairOrder;
use App\Models\User;

class RepairOrderPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Owner, UserRole::Warehouse);
    }

    public function view(User $user, RepairOrder $order): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Owner, UserRole::Warehouse);
    }

    /**
     * Cierre y cancelación se permiten mientras la orden está abierta.
     */
    public function close(User $user, RepairOrder $order): bool
    {
        return $order->isOpen() && $user->hasRole(UserRole::Owner, UserRole::Warehouse);
    }

    public function cancel(User $user, RepairOrder $order): bool
    {
        return $this->close($user, $order);
    }
}
