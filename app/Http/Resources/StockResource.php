<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Espera un array `['warehouse' => Warehouse, 'qty' => float]`.
 */
class StockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'qty' => (float) $this->resource['qty'],
            'warehouse' => new WarehouseResource($this->resource['warehouse']),
        ];
    }
}
