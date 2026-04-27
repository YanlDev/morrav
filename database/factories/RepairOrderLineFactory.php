<?php

namespace Database\Factories;

use App\Models\RepairOrder;
use App\Models\RepairOrderLine;
use App\Models\Sku;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepairOrderLine>
 */
class RepairOrderLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'repair_order_id' => RepairOrder::factory(),
            'sku_id' => Sku::factory(),
            'quantity_claimed' => fake()->randomFloat(2, 1, 5),
            'quantity_repaired' => null,
            'quantity_scrapped' => null,
            'destination_warehouse_id' => null,
            'notes' => null,
        ];
    }
}
