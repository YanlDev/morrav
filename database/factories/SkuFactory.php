<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sku;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Sku>
 */
class SkuFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'internal_code' => 'SKU-'.Str::upper(fake()->unique()->bothify('######')),
            'variant_name' => fake()->optional()->words(2, true),
            'sale_price' => fake()->optional()->randomFloat(2, 10, 5000),
            'purchase_price' => fake()->optional()->randomFloat(2, 5, 2500),
            'photo' => null,
            'status' => 'active',
            'fingerprint' => fake()->sha256(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }

    public function discontinued(): static
    {
        return $this->state(fn () => ['status' => 'discontinued']);
    }
}
