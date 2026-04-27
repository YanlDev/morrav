<?php

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\Family;
use App\Models\FamilyAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FamilyAttribute>
 */
class FamilyAttributeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'attribute_id' => Attribute::factory(),
            'is_required' => false,
            'is_key' => false,
            'sort_order' => 0,
        ];
    }
}
