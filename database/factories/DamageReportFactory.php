<?php

namespace Database\Factories;

use App\Enums\DamageReason;
use App\Models\DamageReport;
use App\Models\RepairOrderLine;
use App\Models\Sku;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DamageReport>
 */
class DamageReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku_id' => Sku::factory(),
            'warehouse_id' => Warehouse::factory()->asStore(),
            'quantity' => fake()->randomFloat(2, 1, 5),
            'reason_code' => fake()->randomElement(DamageReason::cases())->value,
            'reason_notes' => fake()->optional()->sentence(6),
            'reported_by' => User::factory(),
            'reported_at' => now(),
            'movement_id' => null,
            'repair_order_line_id' => null,
        ];
    }

    public function claimed(): static
    {
        return $this->state(fn () => [
            'repair_order_line_id' => RepairOrderLine::factory(),
        ]);
    }
}
