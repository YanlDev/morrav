<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeders seguros para correr en producción. Todos son idempotentes y no
 * dependen de fakerphp/faker (que vive en require-dev y no está disponible
 * en builds de producción).
 *
 * Los usuarios se crean por separado con `php artisan app:create-admin`,
 * no acá.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            WarehouseSeeder::class,
            FamilySeeder::class,
            AttributeSeeder::class,
            FamilyAttributeSeeder::class,
            DemoProductSeeder::class,
            DemoStockSeeder::class,
        ]);
    }
}
