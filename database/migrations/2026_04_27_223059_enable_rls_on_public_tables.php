<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Habilita Row Level Security en todas las tablas públicas. Sin policies
 * adjuntas, el efecto es que la API REST de Supabase (PostgREST con la
 * anon/authenticated key) deja de exponer datos a clientes externos.
 *
 * Laravel sigue funcionando porque conecta como superuser `postgres`,
 * y los superusers bypasean RLS en Postgres.
 *
 * Solo aplica a Postgres. SQLite (tests) no tiene RLS.
 */
return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $tables = [
        'migrations',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
        'password_reset_tokens',
        'users',
        'warehouses',
        'families',
        'subfamilies',
        'attributes',
        'family_attributes',
        'products',
        'skus',
        'sku_attributes',
        'sku_external_codes',
        'movements',
        'movement_lines',
        'repair_orders',
        'repair_order_lines',
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
