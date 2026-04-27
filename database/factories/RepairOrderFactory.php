<?php

namespace Database\Factories;

use App\Models\RepairOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepairOrder>
 */
class RepairOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'REP-'.fake()->unique()->bothify('######'),
            'status' => 'open',
            'outcome' => null,
            'notes' => fake()->optional()->sentence(6),
            'opened_by' => User::factory(),
            'closed_by' => null,
            'closed_at' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => 'closed',
            'outcome' => 'completed',
            'closed_by' => User::factory(),
            'closed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => 'closed',
            'outcome' => 'cancelled',
            'closed_by' => User::factory(),
            'closed_at' => now(),
        ]);
    }
}
