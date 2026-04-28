<?php

namespace App\Http\Resources;

use App\Models\MovementLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MovementLine
 */
class MovementLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'direction' => $this->direction,
            'quantity' => (float) $this->quantity,
            'notes' => $this->notes,
            'sku' => $this->whenLoaded('sku', fn () => new SkuResource($this->sku)),
            'warehouse' => $this->whenLoaded('warehouse', fn () => new WarehouseResource($this->warehouse)),
        ];
    }
}
