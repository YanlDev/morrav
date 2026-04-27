<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * En Postgres `enum()` se traduce a varchar + CHECK constraint. Esta migración extiende
 * el CHECK existente para aceptar 'sale'. En SQLite no hace nada porque la migración
 * original ya se actualizó para incluir 'sale' al crear la tabla (los tests siempre
 * corren migrate:fresh).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE movements DROP CONSTRAINT IF EXISTS movements_type_check');
        DB::statement("ALTER TABLE movements ADD CONSTRAINT movements_type_check CHECK (type IN ('inbound', 'outbound', 'transfer', 'adjustment', 'initial_load', 'sale'))");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE movements DROP CONSTRAINT IF EXISTS movements_type_check');
        DB::statement("ALTER TABLE movements ADD CONSTRAINT movements_type_check CHECK (type IN ('inbound', 'outbound', 'transfer', 'adjustment', 'initial_load'))");
    }
};
