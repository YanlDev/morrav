<?php

namespace Database\Factories;

use App\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attribute>
 */
class AttributeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('attr_???'),
            'name' => fake()->unique()->words(2, true),
            'type' => 'text',
            'unit' => null,
            'options' => null,
        ];
    }

    public function list(array $options = ['red', 'blue', 'green']): static
    {
        return $this->state(fn () => [
            'type' => 'list',
            'options' => $options,
        ]);
    }

    public function number(?string $unit = null): static
    {
        return $this->state(fn () => [
            'type' => 'number',
            'unit' => $unit,
        ]);
    }
}
