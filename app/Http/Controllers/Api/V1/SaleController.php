<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SalesHistoryRequest;
use App\Http\Requests\Api\V1\StoreSaleRequest;
use App\Http\Resources\MovementResource;
use App\Models\Movement;
use App\Models\Sku;
use App\Models\Warehouse;
use App\Services\Sales\SaleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    /**
     * Registra una venta atómica vía SaleService::sell. Reglas de negocio
     * (stock insuficiente, cantidad inválida) llegan como ValidationException
     * ya con el campo `quantity` mapeado, para que el cliente pueda mostrar
     * el error junto al input correspondiente.
     */
    public function store(StoreSaleRequest $request, SaleService $service): MovementResource
    {
        $data = $request->validated();

        $sku = Sku::findOrFail($data['sku_id']);
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);

        try {
            $movement = $service->sell(
                user: $request->user(),
                sku: $sku,
                warehouse: $warehouse,
                quantity: (float) $data['quantity'],
                notes: $data['notes'] ?? null,
            );
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['quantity' => $e->getMessage()]);
        }

        $movement->load([
            'lines.sku.product:id,name,internal_code',
            'lines.warehouse',
            'originWarehouse',
            'destinationWarehouse',
        ]);

        return new MovementResource($movement);
    }

    /**
     * Historial de ventas confirmadas del usuario actual. Sin filtros se
     * devuelven las del día de hoy. Acepta `from` y `to` (ISO date) para
     * rangos arbitrarios y entrega un resumen agregado de todo el rango.
     */
    public function mine(SalesHistoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->startOfDay();
        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay();

        $base = Movement::query()
            ->where('type', 'sale')
            ->where('status', 'confirmed')
            ->where('created_by', $request->user()->id)
            ->whereBetween('occurred_at', [$from, $to]);

        $sales = (clone $base)
            ->with([
                'lines.sku.product:id,name,internal_code',
                'lines.warehouse',
            ])
            ->orderByDesc('occurred_at')
            ->get();

        $summary = $this->summarize($sales);

        return response()->json([
            'data' => MovementResource::collection($sales),
            'meta' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'summary' => $summary,
            ],
        ]);
    }

    /**
     * @param  Collection<int, Movement>  $sales
     * @return array{count: int, total_units: float, total_revenue: float}
     */
    private function summarize($sales): array
    {
        $count = $sales->count();
        $totalUnits = 0.0;
        $totalRevenue = 0.0;

        foreach ($sales as $sale) {
            foreach ($sale->lines as $line) {
                $qty = (float) $line->quantity;
                $totalUnits += $qty;
                $price = $line->sku?->sale_price;
                if ($price !== null) {
                    $totalRevenue += $qty * (float) $price;
                }
            }
        }

        return [
            'count' => $count,
            'total_units' => $totalUnits,
            'total_revenue' => $totalRevenue,
        ];
    }
}
