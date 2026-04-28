<?php

namespace App\Services\Damage;

use App\Enums\DamageReason;
use App\Models\DamageReport;
use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Sku;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Registra unidades dañadas: crea un movement transfer confirmado de la
 * tienda al taller (origen → workshop) y un DamageReport ligado a ese
 * movement para preservar la trazabilidad (quién, cuándo, por qué). Tras
 * confirmarse, el stock queda físicamente en el taller hasta que una
 * RepairOrder lo reclame.
 */
class DamageService
{
    public function report(
        User $user,
        Sku $sku,
        Warehouse $warehouse,
        float $quantity,
        ?DamageReason $reason = null,
        ?string $notes = null,
    ): DamageReport {
        if ($quantity <= 0) {
            throw new RuntimeException('La cantidad debe ser mayor a cero.');
        }

        $workshop = $this->workshopWarehouse();

        return DB::transaction(function () use ($user, $sku, $warehouse, $quantity, $reason, $notes, $workshop) {
            $sku = Sku::lockForUpdate()->findOrFail($sku->id);
            $available = $sku->stockAt($warehouse->id);

            if ($quantity > $available) {
                throw new RuntimeException(
                    "Stock insuficiente: solo hay {$available} unidades de {$sku->internal_code} en {$warehouse->code}."
                );
            }

            $movement = Movement::create([
                'number' => $this->nextMovementNumber(),
                'type' => 'transfer',
                'occurred_at' => now(),
                'reason' => $this->buildReason($reason, $notes),
                'reference_type' => 'damage_report',
                'origin_warehouse_id' => $warehouse->id,
                'destination_warehouse_id' => $workshop->id,
                'status' => 'confirmed',
                'created_by' => $user->id,
                'confirmed_by' => $user->id,
                'confirmed_at' => now(),
            ]);

            MovementLine::create([
                'movement_id' => $movement->id,
                'sku_id' => $sku->id,
                'warehouse_id' => $warehouse->id,
                'direction' => 'out',
                'quantity' => $quantity,
                'notes' => $notes,
            ]);

            MovementLine::create([
                'movement_id' => $movement->id,
                'sku_id' => $sku->id,
                'warehouse_id' => $workshop->id,
                'direction' => 'in',
                'quantity' => $quantity,
                'notes' => $notes,
            ]);

            $report = DamageReport::create([
                'sku_id' => $sku->id,
                'warehouse_id' => $warehouse->id,
                'quantity' => $quantity,
                'reason_code' => $reason?->value,
                'reason_notes' => $notes,
                'reported_by' => $user->id,
                'reported_at' => now(),
                'movement_id' => $movement->id,
            ]);

            $movement->update(['reference_id' => $report->id]);

            return $report->fresh(['sku', 'warehouse', 'reporter', 'movement']);
        });
    }

    private function workshopWarehouse(): Warehouse
    {
        $wh = Warehouse::query()->where('type', 'workshop')->where('active', true)->first();

        if (! $wh) {
            throw new RuntimeException('No hay almacén de taller (workshop) configurado.');
        }

        return $wh;
    }

    private function buildReason(?DamageReason $reason, ?string $notes): string
    {
        $base = $reason?->label() ?? 'Daño reportado';

        return $notes ? "{$base}: {$notes}" : $base;
    }

    private function nextMovementNumber(): string
    {
        $lastId = Movement::max('id') ?? 0;

        return 'MOV-'.str_pad((string) ($lastId + 1), 6, '0', STR_PAD_LEFT);
    }
}
