<?php

namespace App\Http\Resources;

use App\Models\DamageReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DamageReport
 */
class DamageReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => (float) $this->quantity,
            'reason_code' => $this->reason_code?->value,
            'reason_label' => $this->reason_code?->label(),
            'reason_notes' => $this->reason_notes,
            'reported_at' => $this->reported_at?->toIso8601String(),
            'is_pending' => $this->isPending(),
            'sku' => $this->whenLoaded('sku', fn () => new SkuResource($this->sku)),
            'warehouse' => $this->whenLoaded('warehouse', fn () => new WarehouseResource($this->warehouse)),
            'movement' => $this->whenLoaded('movement', fn () => [
                'id' => $this->movement->id,
                'number' => $this->movement->number,
            ]),
        ];
    }
}
