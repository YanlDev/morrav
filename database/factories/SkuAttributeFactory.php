<?php

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\Sku;
use App\Models\SkuAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SkuAttribute>
 */
class SkuAttributeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku_id' => Sku::factory(),
            'attribute_id' => Attribute::factory(),
            'value' => fake()->word(),
        ];
    }
}
