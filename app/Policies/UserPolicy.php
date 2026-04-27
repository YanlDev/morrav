<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Admin pasa para casi todas las abilities salvo `delete` y `disable`,
     * que requieren chequeo extra (no permitir auto-eliminación / auto-desactivación).
     */
    public function before(User $user, string $ability): ?bool
    {
        if (in_array($ability, ['delete', 'disable'], true)) {
            return null;
        }

        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }

    /**
     * Eliminar definitivamente. Solo admin y nunca a sí mismo. Si el usuario tiene
     * registros relacionados (movements, etc.) la FK lo rechazará — el componente
     * captura el error y sugiere deshabilitarlo en su lugar.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->id !== $model->id;
    }

    /**
     * Habilitar / deshabilitar. Solo admin y nunca a sí mismo (evita lockout).
     */
    public function disable(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->id !== $model->id;
    }

    public function restore(User $user, User $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
