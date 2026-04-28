<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WarehouseController extends Controller
{
    /**
     * Lista todos los almacenes activos. Útil para que la app móvil
     * popule selects de tiendas/talleres y muestre el listado en pantallas
     * de consulta.
     */
    public function index(): AnonymousResourceCollection
    {
        $warehouses = Warehouse::query()
            ->active()
            ->orderBy('code')
            ->get();

        return WarehouseResource::collection($warehouses);
    }
}
