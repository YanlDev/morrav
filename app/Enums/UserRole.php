<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Owner = 'owner';
    case Seller = 'seller';
    case Warehouse = 'warehouse';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador del sistema',
            self::Owner => 'Propietario',
            self::Seller => 'Vendedor',
            self::Warehouse => 'Almacenero',
        };
    }

    /**
     * Whether this role is allowed to see purchase costs, margins
     * and financial reports.
     */
    public function canSeeFinancials(): bool
    {
        return in_array($this, [self::Admin, self::Owner], true);
    }

    /**
     * Whether this role is allowed to manage users and system-level settings.
     */
    public function canManageSystem(): bool
    {
        return $this === self::Admin;
    }
}
