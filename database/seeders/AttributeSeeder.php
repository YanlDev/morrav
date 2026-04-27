<?php

namespace Database\Seeders;

use App\Models\Attribute;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    /**
     * Catálogo mínimo de atributos. La mayoría de productos del negocio
     * se describe suficientemente con color, material y una medida libre;
     * `modelo` queda como texto libre para variantes. Todos se asignan
     * como opcionales a las familias — el alta exprés nunca se bloquea.
     */
    public function run(): void
    {
        $attributes = [
            [
                'code' => 'color',
                'name' => 'Color',
                'type' => 'list',
                'options' => ['negro', 'blanco', 'gris', 'marrón', 'beige', 'natural', 'otro'],
            ],
            [
                'code' => 'material',
                'name' => 'Material',
                'type' => 'list',
                'options' => ['melamina', 'madera', 'metal', 'vidrio', 'cuero', 'tela', 'plástico', 'mixto'],
            ],
            [
                'code' => 'medida',
                'name' => 'Medida',
                'type' => 'text',
            ],
            [
                'code' => 'modelo',
                'name' => 'Modelo',
                'type' => 'text',
            ],
        ];

        foreach ($attributes as $data) {
            Attribute::firstOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'unit' => $data['unit'] ?? null,
                    'options' => $data['options'] ?? null,
                ],
            );
        }
    }
}
