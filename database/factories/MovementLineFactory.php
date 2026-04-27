<?php

namespace Database\Factories;

use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Sku;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MovementLine>
 */
class MovementLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'movement_id' => Movement::factory(),
            'sku_id' => Sku::factory(),
            'warehouse_id' => Warehouse::factory(),
            'direction' => fake()->randomElement(['in', 'out']),
            'quantity' => fake()->randomFloat(2, 1, 100),
            'unit_cost' => null,
            'notes' => null,
        ];
    }

    public function inbound(): static
    {
        return $this->state(fn () => [
            'direction' => 'in',
            'unit_cost' => fake()->randomFloat(2, 5, 500),
        ]);
    }

    public function outbound(): static
    {
        return $this->state(fn () => ['direction' => 'out']);
    }
}
