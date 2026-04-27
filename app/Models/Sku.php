<?php

namespace App\Models;

use Database\Factories\SkuFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'product_id',
    'internal_code',
    'variant_name',
    'sale_price',
    'purchase_price',
    'photo',
    'status',
    'fingerprint',
])]
class Sku extends Model
{
    /** @use HasFactory<SkuFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'purchase_price' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(SkuAttribute::class);
    }

    public function externalCodes(): HasMany
    {
        return $this->hasMany(SkuExternalCode::class);
    }

    public function movementLines(): HasMany
    {
        return $this->hasMany(MovementLine::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Calcula el stock disponible en un almacén determinado.
     */
    public function stockAt(int $warehouseId): float
    {
        $inbound = (float) $this->movementLines()
            ->where('warehouse_id', $warehouseId)
            ->where('direction', 'in')
            ->whereHas('movement', fn (Builder $query) => $query->where('status', 'confirmed'))
            ->sum('quantity');

        $outbound = (float) $this->movementLines()
            ->where('warehouse_id', $warehouseId)
            ->where('direction', 'out')
            ->whereHas('movement', fn (Builder $query) => $query->where('status', 'confirmed'))
            ->sum('quantity');

        return $inbound - $outbound;
    }
}
