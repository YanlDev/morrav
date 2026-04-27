<?php

namespace Database\Seeders;

use App\Models\Family;
use App\Models\Subfamily;
use Illuminate\Database\Seeder;

class FamilySeeder extends Seeder
{
    /**
     * Taxonomía alineada con el catálogo Morrav. Cada familia incluye una
     * subfamilia `PENDIENTE` como fallback para el alta exprés desde PV.
     */
    public function run(): void
    {
        $catalog = [
            'OFICINA' => [
                'name' => 'Oficina',
                'description' => 'Mobiliario corporativo y de oficina.',
                'subfamilies' => [
                    'SILLAS' => 'Sillas',
                    'SILLONES' => 'Sillones',
                    'ESCRITORIOS' => 'Escritorios',
                    'ESTANTES' => 'Estantes y archivadores',
                    'MESAS_REUNION' => 'Mesas de reunión',
                    'BUTACAS' => 'Butacas de espera',
                    'COUNTER' => 'Counters y recepción',
                    'PENDIENTE' => 'Pendiente de categorizar',
                ],
            ],
            'PELUQUERIA' => [
                'name' => 'Peluquería y belleza',
                'description' => 'Muebles para salones de belleza, barbería y estética.',
                'subfamilies' => [
                    'SILLONES' => 'Sillones de corte',
                    'MODULOS' => 'Módulos de trabajo',
                    'BARBERIA' => 'Muebles de barbería',
                    'MANICURE' => 'Mesas y sillas de manicure',
                    'PENDIENTE' => 'Pendiente de categorizar',
                ],
            ],
            'HOGAR' => [
                'name' => 'Hogar',
                'description' => 'Muebles para sala, comedor, dormitorio y restaurante.',
                'subfamilies' => [
                    'COMEDOR' => 'Comedor (juegos, mesas, sillas)',
                    'SOFAS' => 'Sofás',
                    'DORMITORIO' => 'Dormitorio (camas, roperos, veladores)',
                    'AMBIENTES' => 'Ambientes de sala',
                    'REST_BAR' => 'Restaurante y bar',
                    'PENDIENTE' => 'Pendiente de categorizar',
                ],
            ],
            'INSTITUCIONES' => [
                'name' => 'Instituciones',
                'description' => 'Mobiliario institucional: auditorios, colegios, iglesias, bibliotecas.',
                'subfamilies' => [
                    'BANCAS' => 'Bancas',
                    'CARPETAS' => 'Carpetas escolares',
                    'AUDITORIO' => 'Butacas de auditorio',
                    'BIBLIOTECA' => 'Mobiliario de biblioteca',
                    'PENDIENTE' => 'Pendiente de categorizar',
                ],
            ],
        ];

        foreach ($catalog as $code => $data) {
            $family = Family::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'active' => true,
                ],
            );

            foreach ($data['subfamilies'] as $subCode => $subName) {
                Subfamily::firstOrCreate(
                    ['family_id' => $family->id, 'code' => $subCode],
                    ['name' => $subName, 'active' => true],
                );
            }
        }
    }
}
