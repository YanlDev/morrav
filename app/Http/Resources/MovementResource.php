<?php

namespace App\Http\Resources;

use App\Models\Movement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Movement
 */
class MovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'type' => $this->type,
            'status' => $this->status,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'reason' => $this->reason,
            'origin_warehouse' => $this->whenLoaded(
                'originWarehouse',
                fn () => $this->originWarehouse ? new WarehouseResource($this->originWarehouse) : null,
            ),
            'destination_warehouse' => $this->whenLoaded(
                'destinationWarehouse',
                fn () => $this->destinationWarehouse ? new WarehouseResource($this->destinationWarehouse) : null,
            ),
            'lines' => MovementLineResource::collection($this->whenLoaded('lines')),
        ];
    }
}
