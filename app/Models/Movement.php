<?php

namespace App\Models;

use Database\Factories\MovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number',
    'type',
    'occurred_at',
    'reason',
    'reference_type',
    'reference_id',
    'origin_warehouse_id',
    'destination_warehouse_id',
    'status',
    'created_by',
    'confirmed_by',
    'confirmed_at',
    'voided_by',
    'voided_at',
    'void_reason',
])]
class Movement extends Model
{
    /** @use HasFactory<MovementFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MovementLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function originWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'origin_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
