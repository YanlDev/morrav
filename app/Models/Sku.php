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
use Illuminate\Support\Facades\DB;

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

    public function damageReports(): HasMany
    {
        return $this->hasMany(DamageReport::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Calcula el stock disponible en un almacén determinado.
     * Una sola query con SUM(CASE) y JOIN explícito (más rápido que dos
     * queries con whereHas correlacionado).
     */
    public function stockAt(int $warehouseId): float
    {
        $total = DB::table('movement_lines as ml')
            ->join('movements as m', 'ml.movement_id', '=', 'm.id')
            ->where('ml.sku_id', $this->id)
            ->where('ml.warehouse_id', $warehouseId)
            ->where('m.status', 'confirmed')
            ->selectRaw("SUM(CASE WHEN ml.direction = 'in' THEN ml.quantity ELSE -ml.quantity END) as total")
            ->value('total');

        return (float) ($total ?? 0);
    }

    /**
     * Versión batch de stockAt: devuelve `[warehouse_id => qty]` para varios
     * almacenes en una sola query. Úsalo cuando iteres almacenes para evitar N queries.
     *
     * @param  array<int, int>  $warehouseIds
     * @return array<int, float>
     */
    public function stockAtMany(array $warehouseIds): array
    {
        if ($warehouseIds === []) {
            return [];
        }

        $rows = DB::table('movement_lines as ml')
            ->join('movements as m', 'ml.movement_id', '=', 'm.id')
            ->where('ml.sku_id', $this->id)
            ->whereIn('ml.warehouse_id', $warehouseIds)
            ->where('m.status', 'confirmed')
            ->groupBy('ml.warehouse_id')
            ->select('ml.warehouse_id')
            ->selectRaw("SUM(CASE WHEN ml.direction = 'in' THEN ml.quantity ELSE -ml.quantity END) as total")
            ->get();

        $result = array_fill_keys($warehouseIds, 0.0);
        foreach ($rows as $row) {
            $result[(int) $row->warehouse_id] = (float) $row->total;
        }

        return $result;
    }
}
