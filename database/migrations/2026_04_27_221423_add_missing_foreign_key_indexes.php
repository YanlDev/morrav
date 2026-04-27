<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega índices de cobertura para foreign keys que `foreignId()->constrained()`
 * no crea automáticamente. Detectados por el linter de Supabase.
 *
 * El más crítico es movement_lines.movement_id: sin él, todo JOIN entre
 * movement_lines y movements hace sequential scan. Es la causa principal
 * de lentitud reportada en el flujo de venta y consultas de stock.
 *
 * `CREATE INDEX IF NOT EXISTS` funciona en Postgres (Supabase) y SQLite (tests).
 */
return new class extends Migration
{
    /**
     * @var array<int, array{table: string, column: string}>
     */
    private array $indexes = [
        ['table' => 'movement_lines', 'column' => 'movement_id'],
        ['table' => 'movements', 'column' => 'created_by'],
        ['table' => 'movements', 'column' => 'confirmed_by'],
        ['table' => 'movements', 'column' => 'voided_by'],
        ['table' => 'movements', 'column' => 'origin_warehouse_id'],
        ['table' => 'movements', 'column' => 'destination_warehouse_id'],
        ['table' => 'skus', 'column' => 'product_id'],
        ['table' => 'products', 'column' => 'family_id'],
        ['table' => 'products', 'column' => 'subfamily_id'],
        ['table' => 'products', 'column' => 'created_by'],
        ['table' => 'family_attributes', 'column' => 'attribute_id'],
        ['table' => 'sku_external_codes', 'column' => 'sku_id'],
        ['table' => 'repair_orders', 'column' => 'opened_by'],
        ['table' => 'repair_orders', 'column' => 'closed_by'],
        ['table' => 'repair_order_lines', 'column' => 'destination_warehouse_id'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $idx) {
            $name = "{$idx['table']}_{$idx['column']}_index";
            DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON {$idx['table']} ({$idx['column']})");
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $idx) {
            $name = "{$idx['table']}_{$idx['column']}_index";
            DB::statement("DROP INDEX IF EXISTS {$name}");
        }
    }
};
