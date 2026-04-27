<?php

namespace Database\Seeders;

use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Sku;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Carga stock inicial demo en TDA1 y ALM para que el flujo de "Vender" tenga
 * unidades disponibles. Idempotente: para cada SKU activo, asegura un
 * objetivo mínimo de stock en cada almacén; si ya tiene más, no hace nada.
 *
 * Crea un único movimiento `initial_load` con todas las líneas para no
 * ensuciar el ledger con N movimientos pequeños.
 */
class DemoStockSeeder extends Seeder
{
    /** Stock objetivo por SKU en cada almacén (TDA1, ALM). */
    private const TARGETS = [
        'TDA1' => 10,
        'ALM' => 25,
    ];

    public function run(): void
    {
        $admin = User::query()->where('role', 'admin')->first()
            ?? User::query()->first();

        if (! $admin) {
            $this->command?->warn('No hay usuarios. Crea al menos un admin antes de cargar stock.');

            return;
        }

        $warehouses = Warehouse::query()
            ->whereIn('code', array_keys(self::TARGETS))
            ->where('active', true)
            ->get()
            ->keyBy('code');

        if ($warehouses->isEmpty()) {
            $this->command?->warn('No se encontraron los almacenes TDA1 / ALM.');

            return;
        }

        $skus = Sku::query()->where('status', 'active')->get();

        if ($skus->isEmpty()) {
            $this->command?->warn('No hay SKUs activos. Corre DemoProductSeeder primero.');

            return;
        }

        $linesToInsert = [];

        foreach (self::TARGETS as $code => $target) {
            $warehouse = $warehouses->get($code);
            if (! $warehouse) {
                continue;
            }

            foreach ($skus as $sku) {
                $current = $sku->stockAt($warehouse->id);
                $missing = $target - $current;

                if ($missing > 0) {
                    $linesToInsert[] = [
                        'sku_id' => $sku->id,
                        'warehouse_id' => $warehouse->id,
                        'quantity' => $missing,
                    ];
                }
            }
        }

        if ($linesToInsert === []) {
            $this->command?->info('Stock ya alcanza los objetivos. Nada que hacer.');

            return;
        }

        DB::transaction(function () use ($admin, $linesToInsert) {
            $movement = Movement::create([
                'number' => 'MOV-'.str_pad((string) ((Movement::max('id') ?? 0) + 1), 6, '0', STR_PAD_LEFT),
                'type' => 'initial_load',
                'occurred_at' => now(),
                'reason' => 'Carga demo (DemoStockSeeder)',
                'destination_warehouse_id' => null,
                'status' => 'confirmed',
                'created_by' => $admin->id,
                'confirmed_by' => $admin->id,
                'confirmed_at' => now(),
            ]);

            foreach ($linesToInsert as $line) {
                MovementLine::create([
                    'movement_id' => $movement->id,
                    'sku_id' => $line['sku_id'],
                    'warehouse_id' => $line['warehouse_id'],
                    'direction' => 'in',
                    'quantity' => $line['quantity'],
                ]);
            }
        });

        $this->command?->info('Stock demo cargado: '.count($linesToInsert).' líneas.');
    }
}
