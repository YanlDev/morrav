<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $warehouses = [
            ['code' => 'ALM', 'name' => 'Almacén Central', 'type' => 'central'],
            ['code' => 'TDA1', 'name' => 'Tienda 1', 'type' => 'store'],
            ['code' => 'TDA2', 'name' => 'Tienda 2', 'type' => 'store'],
            ['code' => 'TDA3', 'name' => 'Tienda 3', 'type' => 'store'],
            ['code' => 'TALLER', 'name' => 'Taller de reparación', 'type' => 'workshop'],
            ['code' => 'MERMA', 'name' => 'Merma', 'type' => 'scrap'],
        ];

        foreach ($warehouses as $data) {
            Warehouse::firstOrCreate(
                ['code' => $data['code']],
                [...$data, 'active' => true],
            );
        }
    }
}
