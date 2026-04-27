<?php

namespace Database\Factories;

use App\Models\Movement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Movement>
 */
class MovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'MOV-'.Str::upper(fake()->unique()->bothify('######')),
            'type' => fake()->randomElement(['inbound', 'outbound', 'transfer', 'adjustment', 'initial_load']),
            'occurred_at' => now(),
            'reason' => fake()->optional()->sentence(4),
            'reference_type' => null,
            'reference_id' => null,
            'status' => 'draft',
            'created_by' => User::factory(),
            'confirmed_by' => null,
            'confirmed_at' => null,
            'voided_by' => null,
            'voided_at' => null,
            'void_reason' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => 'confirmed',
            'confirmed_by' => User::factory(),
            'confirmed_at' => now(),
        ]);
    }

    public function voided(): static
    {
        return $this->state(fn () => [
            'status' => 'voided',
            'voided_by' => User::factory(),
            'voided_at' => now(),
            'void_reason' => fake()->sentence(),
        ]);
    }

    public function inbound(): static
    {
        return $this->state(fn () => ['type' => 'inbound']);
    }

    public function outbound(): static
    {
        return $this->state(fn () => ['type' => 'outbound']);
    }

    public function transfer(): static
    {
        return $this->state(fn () => ['type' => 'transfer']);
    }
}
