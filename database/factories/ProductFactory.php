<?php

namespace Database\Factories;

use App\Models\Family;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'internal_code' => 'PROD-'.Str::upper(fake()->unique()->bothify('######')),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'family_id' => Family::factory(),
            'subfamily_id' => null,
            'brand' => fake()->optional()->company(),
            'unit_of_measure' => fake()->randomElement(['unit', 'meter', 'kg', 'set', 'pair', 'box']),
            'is_temporary' => false,
            'temporary_end_date' => null,
            'status' => 'active',
            'main_photo' => null,
            'fingerprint' => fake()->sha256(),
            'created_by' => null,
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

    public function temporary(): static
    {
        return $this->state(fn () => [
            'is_temporary' => true,
            'temporary_end_date' => now()->addMonths(3),
        ]);
    }
}
