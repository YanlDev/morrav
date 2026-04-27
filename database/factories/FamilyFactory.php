<?php

namespace Database\Factories;

use App\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Family>
 */
class FamilyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('FAM???')),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'active' => true,
        ];
    }
}
