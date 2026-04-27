<?php

namespace App\Services\Sales;

use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Sku;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Crea ventas: un movimiento de tipo `sale` confirmado con una sola línea
 * de salida desde el almacén (la tienda) que el vendedor selecciona. Usado
 * por el flujo móvil "Vender" tras escanear un QR.
 */
class SaleService
{
    /**
     * Registra una venta atómicamente. Verifica que el SKU tenga stock disponible
     * en el almacén origen y rechaza si no alcanza.
     */
    public function sell(
        User $user,
        Sku $sku,
        Warehouse $warehouse,
        float $quantity,
        ?string $notes = null,
    ): Movement {
        if ($quantity <= 0) {
            throw new RuntimeException('La cantidad debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($user, $sku, $warehouse, $quantity, $notes) {
            $sku = Sku::lockForUpdate()->findOrFail($sku->id);
            $available = $sku->stockAt($warehouse->id);

            if ($quantity > $available) {
                throw new RuntimeException(
                    "Stock insuficiente: solo hay {$available} unidades de {$sku->internal_code} en {$warehouse->code}."
                );
            }

            $movement = Movement::create([
                'number' => $this->nextMovementNumber(),
                'type' => 'sale',
                'occurred_at' => now(),
                'reason' => $notes,
                'origin_warehouse_id' => $warehouse->id,
                'destination_warehouse_id' => null,
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
                'unit_cost' => null,
                'notes' => $notes,
            ]);

            return $movement;
        });
    }

    private function nextMovementNumber(): string
    {
        $lastId = Movement::max('id') ?? 0;

        return 'MOV-'.str_pad((string) ($lastId + 1), 6, '0', STR_PAD_LEFT);
    }
}
