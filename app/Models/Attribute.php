<?php

namespace App\Models;

use Database\Factories\AttributeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'type', 'unit', 'options'])]
class Attribute extends Model
{
    /** @use HasFactory<AttributeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
        ];
    }

    public function families(): BelongsToMany
    {
        return $this->belongsToMany(Family::class, 'family_attributes')
            ->using(FamilyAttribute::class)
            ->withPivot(['is_required', 'is_key', 'sort_order'])
            ->withTimestamps();
    }

    public function skuValues(): HasMany
    {
        return $this->hasMany(SkuAttribute::class);
    }
}
