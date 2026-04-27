<?php

namespace Database\Factories;

use App\Models\Family;
use App\Models\Subfamily;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subfamily>
 */
class SubfamilyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'code' => Str::upper(fake()->unique()->lexify('SUB???')),
            'name' => fake()->unique()->words(2, true),
            'active' => true,
        ];
    }
}
