<?php

namespace Database\Factories;

use App\Models\Sku;
use App\Models\SkuExternalCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SkuExternalCode>
 */
class SkuExternalCodeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku_id' => Sku::factory(),
            'code' => fake()->unique()->ean13(),
            'type' => 'barcode',
            'supplier' => null,
        ];
    }

    public function supplier(?string $supplier = null): static
    {
        return $this->state(fn () => [
            'type' => 'supplier',
            'supplier' => $supplier ?? fake()->company(),
        ]);
    }
}
