<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\confirm;

#[Signature('app:reset-data {--force : No pedir confirmación}')]
#[Description('Borra datos transaccionales (productos, SKUs, movimientos, órdenes, usuarios, sesiones) manteniendo el catálogo base: familias, subfamilias, atributos y almacenes. Útil para empezar un test desde cero.')]
class ResetData extends Command
{
    /**
     * Tablas a vaciar. Las FKs son resueltas por TRUNCATE ... CASCADE.
     * El orden no importa con CASCADE pero las dejamos agrupadas.
     */
    private const TABLES_TO_TRUNCATE = [
        // Inventario transaccional
        'movement_lines',
        'movements',
        'repair_order_lines',
        'repair_orders',
        // Catálogo (productos y SKUs son "datos", no estructura)
        'sku_attributes',
        'sku_external_codes',
        'skus',
        'products',
        // Auth y operacional
        'sessions',
        'password_reset_tokens',
        'jobs',
        'job_batches',
        'failed_jobs',
        'cache',
        'cache_locks',
        'users',
    ];

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->error('Este comando solo soporta Postgres. Para SQLite usa migrate:fresh.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->warn('Esto borra TODOS los productos, SKUs, movimientos, órdenes de reparación, usuarios y sesiones.');
            $this->line('Se mantienen: familias, subfamilias, atributos, family_attributes y almacenes.');

            if (! confirm(label: '¿Continuar?', default: false)) {
                $this->info('Cancelado.');

                return self::SUCCESS;
            }
        }

        $tables = implode(', ', self::TABLES_TO_TRUNCATE);
        DB::statement("TRUNCATE {$tables} RESTART IDENTITY CASCADE");

        $this->info('Datos transaccionales borrados. IDs reiniciados.');
        $this->newLine();
        $this->line('Próximos pasos:');
        $this->line('  1. Crear admin:        php artisan app:create-admin');
        $this->line('  2. (opcional) Demo:    php artisan db:seed --class=DemoProductSeeder --force');
        $this->line('                         php artisan db:seed --class=DemoStockSeeder --force');

        return self::SUCCESS;
    }
}
