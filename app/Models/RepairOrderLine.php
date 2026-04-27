<?php

namespace App\Models;

use Database\Factories\RepairOrderLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'repair_order_id',
    'sku_id',
    'quantity_claimed',
    'quantity_repaired',
    'quantity_scrapped',
    'destination_warehouse_id',
    'notes',
])]
class RepairOrderLine extends Model
{
    /** @use HasFactory<RepairOrderLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_claimed' => 'decimal:2',
            'quantity_repaired' => 'decimal:2',
            'quantity_scrapped' => 'decimal:2',
        ];
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }
}
