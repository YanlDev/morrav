<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->bothify('W###')),
            'name' => fake()->company().' Warehouse',
            'type' => fake()->randomElement(['central', 'store', 'workshop', 'scrap', 'transit']),
            'address' => fake()->address(),
            'active' => true,
        ];
    }

    public function asStore(): static
    {
        return $this->state(fn () => ['type' => 'store']);
    }

    public function asCentral(): static
    {
        return $this->state(fn () => ['type' => 'central']);
    }

    public function asWorkshop(): static
    {
        return $this->state(fn () => ['type' => 'workshop']);
    }

    public function asScrap(): static
    {
        return $this->state(fn () => ['type' => 'scrap']);
    }
}
