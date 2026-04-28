<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WarehouseResource;
use App\Models\Movement;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Pantalla principal de la app móvil: tiendas activas + ventas
     * confirmadas que el usuario actual hizo hoy.
     */
    public function index(Request $request): JsonResponse
    {
        $stores = Warehouse::query()
            ->where('type', 'store')
            ->active()
            ->orderBy('code')
            ->get();

        $salesToday = Movement::query()
            ->where('type', 'sale')
            ->where('status', 'confirmed')
            ->where('created_by', $request->user()->id)
            ->whereDate('occurred_at', today())
            ->count();

        return response()->json([
            'data' => [
                'sales_today' => $salesToday,
                'stores' => WarehouseResource::collection($stores),
            ],
        ]);
    }
}
