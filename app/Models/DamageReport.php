<?php

namespace App\Models;

use App\Enums\DamageReason;
use Database\Factories\DamageReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sku_id',
    'warehouse_id',
    'quantity',
    'reason_code',
    'reason_notes',
    'reported_by',
    'reported_at',
    'movement_id',
    'repair_order_line_id',
])]
class DamageReport extends Model
{
    /** @use HasFactory<DamageReportFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'reason_code' => DamageReason::class,
            'reported_at' => 'datetime',
        ];
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(Movement::class);
    }

    public function repairOrderLine(): BelongsTo
    {
        return $this->belongsTo(RepairOrderLine::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('repair_order_line_id');
    }

    public function scopeClaimed(Builder $query): Builder
    {
        return $query->whereNotNull('repair_order_line_id');
    }

    public function isPending(): bool
    {
        return $this->repair_order_line_id === null;
    }
}
