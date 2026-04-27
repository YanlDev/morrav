<?php

namespace App\Services\Repair;

use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\RepairOrder;
use App\Models\RepairOrderLine;
use App\Models\Sku;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Lógica de negocio de órdenes de reparación: apertura con verificación
 * de stock disponible en taller, cierre con generación automática de
 * movimientos (transfer TALLER → destino o TALLER → MERMA) y
 * cancelación. Todas las operaciones que tocan stock pasan por aquí.
 */
class RepairOrderService
{
    /**
     * Stock en taller disponible para abrir una nueva orden de reparación.
     * Se descuenta lo que ya está reclamado por órdenes abiertas para evitar
     * que dos personas reclamen las mismas unidades físicas.
     */
    public function availableForRepair(Sku $sku, Warehouse $workshop): float
    {
        $inWorkshop = $sku->stockAt($workshop->id);

        $claimed = (float) RepairOrderLine::query()
            ->where('sku_id', $sku->id)
            ->whereHas('repairOrder', fn ($q) => $q->where('status', 'open'))
            ->sum('quantity_claimed');

        return max(0.0, $inWorkshop - $claimed);
    }

    /**
     * Abre una nueva orden de reparación con sus líneas. Cada entrada de
     * `$linesData` debe ser `['sku_id' => int, 'quantity_claimed' => float]`.
     *
     * @param  array<int, array{sku_id: int, quantity_claimed: float, notes?: string|null}>  $linesData
     */
    public function open(User $user, array $linesData, ?string $notes = null): RepairOrder
    {
        if ($linesData === []) {
            throw new RuntimeException('La orden de reparación debe tener al menos una línea.');
        }

        $workshop = $this->workshopWarehouse();

        return DB::transaction(function () use ($user, $linesData, $notes, $workshop) {
            $order = RepairOrder::create([
                'code' => $this->nextCode(),
                'status' => 'open',
                'notes' => $notes,
                'opened_by' => $user->id,
            ]);

            foreach ($linesData as $line) {
                $sku = Sku::lockForUpdate()->findOrFail($line['sku_id']);
                $available = $this->availableForRepair($sku, $workshop);
                $requested = (float) $line['quantity_claimed'];

                if ($requested <= 0) {
                    throw new RuntimeException("Cantidad inválida para SKU {$sku->internal_code}.");
                }

                if ($requested > $available) {
                    throw new RuntimeException(
                        "Solo hay {$available} unidades disponibles para reparar de {$sku->internal_code}."
                    );
                }

                RepairOrderLine::create([
                    'repair_order_id' => $order->id,
                    'sku_id' => $sku->id,
                    'quantity_claimed' => $requested,
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            return $order->fresh(['lines.sku', 'opener']);
        });
    }

    /**
     * Cierra la orden con outcome `completed`, generando los movimientos
     * de transferencia desde el taller hacia los destinos indicados o
     * hacia merma. Cada entrada de `$closuresData` debe tener la forma:
     * `['line_id' => int, 'quantity_repaired' => float, 'quantity_scrapped' => float, 'destination_warehouse_id' => ?int]`.
     *
     * Reglas:
     * - `quantity_repaired + quantity_scrapped` debe igualar `quantity_claimed`.
     * - Si `quantity_repaired > 0`, se requiere `destination_warehouse_id`.
     *
     * @param  array<int, array{line_id: int, quantity_repaired: float, quantity_scrapped: float, destination_warehouse_id: ?int}>  $closuresData
     */
    public function close(RepairOrder $order, User $user, array $closuresData): RepairOrder
    {
        if (! $order->isOpen()) {
            throw new RuntimeException('La orden ya está cerrada.');
        }

        $workshop = $this->workshopWarehouse();
        $scrap = $this->scrapWarehouse();

        return DB::transaction(function () use ($order, $user, $closuresData, $workshop, $scrap) {
            $lines = $order->lines()->lockForUpdate()->get()->keyBy('id');

            foreach ($closuresData as $closure) {
                /** @var RepairOrderLine|null $line */
                $line = $lines->get($closure['line_id']);

                if (! $line) {
                    throw new RuntimeException("Línea {$closure['line_id']} no pertenece a esta orden.");
                }

                $repaired = (float) ($closure['quantity_repaired'] ?? 0);
                $scrapped = (float) ($closure['quantity_scrapped'] ?? 0);
                $claimed = (float) $line->quantity_claimed;

                if ($repaired < 0 || $scrapped < 0) {
                    throw new RuntimeException("Cantidades negativas en línea {$line->id}.");
                }

                if (abs(($repaired + $scrapped) - $claimed) > 0.001) {
                    throw new RuntimeException(
                        "La suma de reparadas y merma debe igualar {$claimed} en línea {$line->id}."
                    );
                }

                $destinationId = isset($closure['destination_warehouse_id'])
                    ? (int) $closure['destination_warehouse_id']
                    : null;

                if ($repaired > 0 && ! $destinationId) {
                    throw new RuntimeException(
                        "Falta almacén destino para las {$repaired} unidades reparadas en línea {$line->id}."
                    );
                }

                if ($repaired > 0) {
                    $this->generateTransfer(
                        order: $order,
                        sku: $line->sku,
                        quantity: $repaired,
                        from: $workshop,
                        to: Warehouse::findOrFail($destinationId),
                        user: $user,
                        reason: "Reparación completada · orden {$order->code}",
                    );
                }

                if ($scrapped > 0) {
                    $this->generateTransfer(
                        order: $order,
                        sku: $line->sku,
                        quantity: $scrapped,
                        from: $workshop,
                        to: $scrap,
                        user: $user,
                        reason: "Descarte tras reparación · orden {$order->code}",
                    );
                }

                $line->update([
                    'quantity_repaired' => $repaired,
                    'quantity_scrapped' => $scrapped,
                    'destination_warehouse_id' => $destinationId,
                ]);
            }

            $order->update([
                'status' => 'closed',
                'outcome' => 'completed',
                'closed_by' => $user->id,
                'closed_at' => now(),
            ]);

            return $order->fresh(['lines.sku', 'lines.destinationWarehouse', 'opener', 'closer']);
        });
    }

    public function cancel(RepairOrder $order, User $user, ?string $reason = null): RepairOrder
    {
        if (! $order->isOpen()) {
            throw new RuntimeException('La orden ya está cerrada.');
        }

        $order->update([
            'status' => 'closed',
            'outcome' => 'cancelled',
            'closed_by' => $user->id,
            'closed_at' => now(),
            'notes' => $reason
                ? trim(($order->notes ? $order->notes."\n\n" : '')."Cancelada: {$reason}")
                : $order->notes,
        ]);

        return $order->fresh();
    }

    /**
     * Resumen de stock en taller que aún no está reclamado por ninguna
     * orden abierta. Sirve para alertar en el listado que hay trabajo pendiente.
     *
     * @return array{units: float, skus: int}
     */
    public function pendingInWorkshopStats(): array
    {
        $workshop = $this->workshopWarehouse();

        // Stock confirmado por SKU en TALLER.
        $stockRows = DB::table('movement_lines as ml')
            ->join('movements as m', 'ml.movement_id', '=', 'm.id')
            ->where('ml.warehouse_id', $workshop->id)
            ->where('m.status', 'confirmed')
            ->groupBy('ml.sku_id')
            ->select('ml.sku_id')
            ->selectRaw("SUM(CASE WHEN ml.direction = 'in' THEN ml.quantity ELSE -ml.quantity END) as qty")
            ->havingRaw("SUM(CASE WHEN ml.direction = 'in' THEN ml.quantity ELSE -ml.quantity END) > 0")
            ->get();

        if ($stockRows->isEmpty()) {
            return ['units' => 0.0, 'skus' => 0];
        }

        // Cantidad reclamada por órdenes abiertas, por SKU.
        $claimed = DB::table('repair_order_lines as rol')
            ->join('repair_orders as ro', 'rol.repair_order_id', '=', 'ro.id')
            ->where('ro.status', 'open')
            ->groupBy('rol.sku_id')
            ->select('rol.sku_id')
            ->selectRaw('SUM(rol.quantity_claimed) as claimed')
            ->get()
            ->keyBy('sku_id');

        $totalUnits = 0.0;
        $skuCount = 0;

        foreach ($stockRows as $row) {
            $available = (float) $row->qty - (float) ($claimed->get($row->sku_id)->claimed ?? 0);

            if ($available > 0) {
                $totalUnits += $available;
                $skuCount++;
            }
        }

        return ['units' => $totalUnits, 'skus' => $skuCount];
    }

    /**
     * SKUs con stock disponible para reparar (en taller, sin reclamar
     * por otra orden abierta).
     *
     * @return EloquentCollection<int, Sku>
     */
    public function repairableSkus(): EloquentCollection
    {
        $workshop = $this->workshopWarehouse();

        $skus = Sku::query()
            ->whereHas('movementLines', fn ($q) => $q
                ->where('warehouse_id', $workshop->id)
                ->whereHas('movement', fn ($m) => $m->where('status', 'confirmed'))
            )
            ->with('product:id,name,internal_code')
            ->orderBy('internal_code')
            ->get();

        return $skus->filter(fn (Sku $sku) => $this->availableForRepair($sku, $workshop) > 0)->values();
    }

    private function generateTransfer(
        RepairOrder $order,
        Sku $sku,
        float $quantity,
        Warehouse $from,
        Warehouse $to,
        User $user,
        string $reason,
    ): Movement {
        $movement = Movement::create([
            'number' => $this->nextMovementNumber(),
            'type' => 'transfer',
            'occurred_at' => now(),
            'reason' => $reason,
            'reference_type' => 'repair_order',
            'reference_id' => $order->id,
            'origin_warehouse_id' => $from->id,
            'destination_warehouse_id' => $to->id,
            'status' => 'confirmed',
            'created_by' => $user->id,
            'confirmed_by' => $user->id,
            'confirmed_at' => now(),
        ]);

        MovementLine::create([
            'movement_id' => $movement->id,
            'sku_id' => $sku->id,
            'warehouse_id' => $from->id,
            'direction' => 'out',
            'quantity' => $quantity,
        ]);

        MovementLine::create([
            'movement_id' => $movement->id,
            'sku_id' => $sku->id,
            'warehouse_id' => $to->id,
            'direction' => 'in',
            'quantity' => $quantity,
        ]);

        return $movement;
    }

    private function workshopWarehouse(): Warehouse
    {
        $wh = Warehouse::query()->where('type', 'workshop')->where('active', true)->first();

        if (! $wh) {
            throw new RuntimeException('No hay almacén de taller (workshop) configurado.');
        }

        return $wh;
    }

    private function scrapWarehouse(): Warehouse
    {
        $wh = Warehouse::query()->where('type', 'scrap')->where('active', true)->first();

        if (! $wh) {
            throw new RuntimeException('No hay almacén de merma (scrap) configurado.');
        }

        return $wh;
    }

    private function nextCode(): string
    {
        $lastId = RepairOrder::max('id') ?? 0;

        return 'REP-'.str_pad((string) ($lastId + 1), 6, '0', STR_PAD_LEFT);
    }

    private function nextMovementNumber(): string
    {
        $lastId = Movement::max('id') ?? 0;

        return 'MOV-'.str_pad((string) ($lastId + 1), 6, '0', STR_PAD_LEFT);
    }
}
