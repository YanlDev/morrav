<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Family;
use Illuminate\Database\Seeder;

class FamilyAttributeSeeder extends Seeder
{
    /**
     * Las 4 familias Morrav comparten el mismo set simple de atributos,
     * todos opcionales. `color` y `material` quedan marcados como `key`
     * para que la lógica de fingerprint/duplicados los tenga en cuenta.
     */
    public function run(): void
    {
        $attributeRules = [
            'color' => ['required' => false, 'key' => true],
            'material' => ['required' => false, 'key' => true],
            'medida' => ['required' => false, 'key' => false],
            'modelo' => ['required' => false, 'key' => false],
        ];

        $familyCodes = ['OFICINA', 'PELUQUERIA', 'HOGAR', 'INSTITUCIONES'];

        foreach ($familyCodes as $familyCode) {
            $family = Family::where('code', $familyCode)->first();

            if (! $family) {
                continue;
            }

            $sync = [];
            $order = 0;

            foreach ($attributeRules as $attrCode => $rules) {
                $attribute = Attribute::where('code', $attrCode)->first();

                if (! $attribute) {
                    continue;
                }

                $sync[$attribute->id] = [
                    'is_required' => $rules['required'],
                    'is_key' => $rules['key'],
                    'sort_order' => $order++,
                ];
            }

            $family->attributes()->syncWithoutDetaching($sync);

            foreach ($sync as $attributeId => $pivot) {
                $family->attributes()->updateExistingPivot($attributeId, $pivot);
            }
        }
    }
}
