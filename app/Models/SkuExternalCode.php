<?php

namespace App\Models;

use Database\Factories\SkuExternalCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sku_id', 'code', 'type', 'supplier'])]
class SkuExternalCode extends Model
{
    /** @use HasFactory<SkuExternalCodeFactory> */
    use HasFactory;

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }
}
