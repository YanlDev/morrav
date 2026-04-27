<?php

namespace App\Models;

use Database\Factories\SkuAttributeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sku_id', 'attribute_id', 'value'])]
class SkuAttribute extends Model
{
    /** @use HasFactory<SkuAttributeFactory> */
    use HasFactory;

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
