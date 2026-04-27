<?php

namespace App\Models;

use Database\Factories\FamilyAttributeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['family_id', 'attribute_id', 'is_required', 'is_key', 'sort_order'])]
class FamilyAttribute extends Pivot
{
    /** @use HasFactory<FamilyAttributeFactory> */
    use HasFactory;

    protected $table = 'family_attributes';

    public $incrementing = true;

    public $timestamps = true;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_key' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
