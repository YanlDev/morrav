<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Family;
use App\Models\Product;
use App\Models\Sku;
use App\Models\SkuAttribute;
use App\Models\Subfamily;
use Illuminate\Database\Seeder;

/**
 * Catálogo demo: ~15 productos con 22 SKUs distribuidos en las 4 familias.
 * Cada producto recibe una foto de respaldo según su familia (las imágenes
 * de /lineas/ ya están en el repo). Idempotente: usa firstOrCreate.
 */
class DemoProductSeeder extends Seeder
{
    /**
     * Mapeo de familia → foto pública. Sirve como placeholder hasta que
     * se cargue la galería real por SKU.
     */
    private const FAMILY_PHOTOS = [
        'OFICINA' => '/lineas/oficina.jpg',
        'HOGAR' => '/lineas/hogar.jpg',
        'PELUQUERIA' => '/lineas/salones.jpg',
        'INSTITUCIONES' => '/lineas/comercios.jpg',
    ];

    public function run(): void
    {
        foreach ($this->catalog() as $item) {
            $family = Family::where('code', $item['family'])->first();

            if (! $family) {
                continue;
            }

            $subfamily = Subfamily::where('family_id', $family->id)
                ->where('code', $item['subfamily'])
                ->first();

            $photo = self::FAMILY_PHOTOS[$item['family']] ?? null;

            $product = Product::firstOrCreate(
                ['name' => $item['product']['name'], 'family_id' => $family->id],
                [
                    'internal_code' => $this->nextProductCode(),
                    'description' => $item['product']['description'],
                    'subfamily_id' => $subfamily?->id,
                    'brand' => $item['product']['brand'],
                    'unit_of_measure' => 'unit',
                    'status' => 'active',
                    'fingerprint' => hash('sha256', mb_strtolower($item['product']['name']).'|'.$family->id),
                ],
            );

            foreach ($item['skus'] as $skuData) {
                $sku = Sku::firstOrCreate(
                    ['product_id' => $product->id, 'variant_name' => $skuData['variant_name']],
                    [
                        'internal_code' => $this->nextSkuCode(),
                        'sale_price' => $skuData['sale_price'],
                        'purchase_price' => $skuData['purchase_price'],
                        'photo' => $photo,
                        'status' => 'active',
                        'fingerprint' => hash('sha256', $product->id.'|'.json_encode($skuData['attrs'])),
                    ],
                );

                // Si el SKU ya existía sin foto, completarla.
                if ($sku->photo === null && $photo !== null) {
                    $sku->update(['photo' => $photo]);
                }

                foreach ($skuData['attrs'] as $attrCode => $value) {
                    $attribute = Attribute::where('code', $attrCode)->first();
                    if (! $attribute) {
                        continue;
                    }
                    SkuAttribute::updateOrCreate(
                        ['sku_id' => $sku->id, 'attribute_id' => $attribute->id],
                        ['value' => $value],
                    );
                }
            }
        }
    }

    private function nextProductCode(): string
    {
        $next = (Product::withTrashed()->max('id') ?? 0) + 1;

        return 'PROD-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function nextSkuCode(): string
    {
        $next = (Sku::withTrashed()->max('id') ?? 0) + 1;

        return 'SKU-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<int, array{family: string, subfamily: string, product: array{name: string, description: string, brand: string}, skus: array<int, array{variant_name: string, sale_price: float, purchase_price: float, attrs: array<string, string>}>}>
     */
    private function catalog(): array
    {
        return [
            // ===== OFICINA =====
            [
                'family' => 'OFICINA',
                'subfamily' => 'SILLAS',
                'product' => [
                    'name' => 'Silla gerencial Milano',
                    'description' => 'Silla gerencial con respaldo alto, apoyabrazos ajustables y base cromada.',
                    'brand' => 'ErgoMax',
                ],
                'skus' => [
                    ['variant_name' => 'Negro / Cuero', 'sale_price' => 890.00, 'purchase_price' => 520.00,
                        'attrs' => ['color' => 'negro', 'material' => 'cuero', 'modelo' => 'Milano']],
                    ['variant_name' => 'Gris / Tela', 'sale_price' => 790.00, 'purchase_price' => 460.00,
                        'attrs' => ['color' => 'gris', 'material' => 'tela', 'modelo' => 'Milano']],
                ],
            ],
            [
                'family' => 'OFICINA',
                'subfamily' => 'SILLAS',
                'product' => [
                    'name' => 'Silla operativa Sigma',
                    'description' => 'Silla operativa con malla ergonómica y soporte lumbar regulable.',
                    'brand' => 'ErgoMax',
                ],
                'skus' => [
                    ['variant_name' => 'Negro / Mesh', 'sale_price' => 460.00, 'purchase_price' => 270.00,
                        'attrs' => ['color' => 'negro', 'material' => 'mesh', 'modelo' => 'Sigma']],
                    ['variant_name' => 'Gris / Mesh', 'sale_price' => 460.00, 'purchase_price' => 270.00,
                        'attrs' => ['color' => 'gris', 'material' => 'mesh', 'modelo' => 'Sigma']],
                    ['variant_name' => 'Azul / Mesh', 'sale_price' => 480.00, 'purchase_price' => 285.00,
                        'attrs' => ['color' => 'azul', 'material' => 'mesh', 'modelo' => 'Sigma']],
                ],
            ],
            [
                'family' => 'OFICINA',
                'subfamily' => 'ESCRITORIOS',
                'product' => [
                    'name' => 'Escritorio gerencial 1.80m',
                    'description' => 'Escritorio gerencial en melamina con cajonería integrada.',
                    'brand' => 'WoodLine',
                ],
                'skus' => [
                    ['variant_name' => 'Natural', 'sale_price' => 1850.00, 'purchase_price' => 1050.00,
                        'attrs' => ['color' => 'natural', 'material' => 'melamina', 'medida' => '1.80m']],
                    ['variant_name' => 'Wengue', 'sale_price' => 1850.00, 'purchase_price' => 1050.00,
                        'attrs' => ['color' => 'wengue', 'material' => 'melamina', 'medida' => '1.80m']],
                ],
            ],
            [
                'family' => 'OFICINA',
                'subfamily' => 'ESTANTES',
                'product' => [
                    'name' => 'Archivador metálico 4 cajones',
                    'description' => 'Archivador vertical metálico con cerradura central y rieles deslizantes.',
                    'brand' => 'SteelOffice',
                ],
                'skus' => [
                    ['variant_name' => 'Beige', 'sale_price' => 950.00, 'purchase_price' => 560.00,
                        'attrs' => ['color' => 'beige', 'material' => 'metal']],
                ],
            ],
            [
                'family' => 'OFICINA',
                'subfamily' => 'MESAS_REUNION',
                'product' => [
                    'name' => 'Mesa de reunión 2.40m',
                    'description' => 'Mesa de reunión rectangular para 8 personas con pasacables.',
                    'brand' => 'WoodLine',
                ],
                'skus' => [
                    ['variant_name' => 'Natural', 'sale_price' => 2100.00, 'purchase_price' => 1250.00,
                        'attrs' => ['color' => 'natural', 'material' => 'melamina', 'medida' => '2.40m']],
                ],
            ],

            // ===== HOGAR =====
            [
                'family' => 'HOGAR',
                'subfamily' => 'SOFAS',
                'product' => [
                    'name' => 'Sofá 3 cuerpos Florencia',
                    'description' => 'Sofá de sala de 3 cuerpos con estructura reforzada y cojines desenfundables.',
                    'brand' => 'Florencia',
                ],
                'skus' => [
                    ['variant_name' => 'Gris / Tela', 'sale_price' => 2890.00, 'purchase_price' => 1700.00,
                        'attrs' => ['color' => 'gris', 'material' => 'tela', 'medida' => '2.00m']],
                    ['variant_name' => 'Beige / Tela', 'sale_price' => 2890.00, 'purchase_price' => 1700.00,
                        'attrs' => ['color' => 'beige', 'material' => 'tela', 'medida' => '2.00m']],
                ],
            ],
            [
                'family' => 'HOGAR',
                'subfamily' => 'COMEDOR',
                'product' => [
                    'name' => 'Comedor Toscana 6 sillas',
                    'description' => 'Juego de comedor con mesa rectangular y 6 sillas tapizadas.',
                    'brand' => 'Toscana',
                ],
                'skus' => [
                    ['variant_name' => 'Natural', 'sale_price' => 3490.00, 'purchase_price' => 2100.00,
                        'attrs' => ['color' => 'natural', 'material' => 'madera']],
                ],
            ],
            [
                'family' => 'HOGAR',
                'subfamily' => 'DORMITORIO',
                'product' => [
                    'name' => 'Cama 2 plazas Verona',
                    'description' => 'Cama de dos plazas con cabecera capitoneada.',
                    'brand' => 'Verona',
                ],
                'skus' => [
                    ['variant_name' => 'Capitoneada gris', 'sale_price' => 1890.00, 'purchase_price' => 1100.00,
                        'attrs' => ['color' => 'gris', 'material' => 'tela']],
                    ['variant_name' => 'Capitoneada beige', 'sale_price' => 1890.00, 'purchase_price' => 1100.00,
                        'attrs' => ['color' => 'beige', 'material' => 'tela']],
                ],
            ],
            [
                'family' => 'HOGAR',
                'subfamily' => 'AMBIENTES',
                'product' => [
                    'name' => 'Mueble de TV 1.60m',
                    'description' => 'Mueble de TV con dos puertas y estante central.',
                    'brand' => 'WoodLine',
                ],
                'skus' => [
                    ['variant_name' => 'Wengue', 'sale_price' => 690.00, 'purchase_price' => 410.00,
                        'attrs' => ['color' => 'wengue', 'material' => 'melamina', 'medida' => '1.60m']],
                ],
            ],

            // ===== PELUQUERIA =====
            [
                'family' => 'PELUQUERIA',
                'subfamily' => 'SILLONES',
                'product' => [
                    'name' => 'Sillón corte hidráulico Bella',
                    'description' => 'Sillón de corte con base hidráulica y reposacabezas regulable.',
                    'brand' => 'BellaSalon',
                ],
                'skus' => [
                    ['variant_name' => 'Negro', 'sale_price' => 1490.00, 'purchase_price' => 880.00,
                        'attrs' => ['color' => 'negro', 'material' => 'cuero']],
                    ['variant_name' => 'Rojo', 'sale_price' => 1490.00, 'purchase_price' => 880.00,
                        'attrs' => ['color' => 'rojo', 'material' => 'cuero']],
                ],
            ],
            [
                'family' => 'PELUQUERIA',
                'subfamily' => 'MODULOS',
                'product' => [
                    'name' => 'Lavacabezas Premium con sillón',
                    'description' => 'Lavacabezas con cuba de cerámica y sillón reclinable integrado.',
                    'brand' => 'BellaSalon',
                ],
                'skus' => [
                    ['variant_name' => 'Negro', 'sale_price' => 1990.00, 'purchase_price' => 1200.00,
                        'attrs' => ['color' => 'negro', 'material' => 'cuero']],
                ],
            ],
            [
                'family' => 'PELUQUERIA',
                'subfamily' => 'BARBERIA',
                'product' => [
                    'name' => 'Espejo de barbería marco metal',
                    'description' => 'Espejo rectangular con marco metálico cromado para barbería.',
                    'brand' => 'BellaSalon',
                ],
                'skus' => [
                    ['variant_name' => 'Plata', 'sale_price' => 540.00, 'purchase_price' => 320.00,
                        'attrs' => ['color' => 'plata', 'material' => 'metal']],
                ],
            ],

            // ===== INSTITUCIONES =====
            [
                'family' => 'INSTITUCIONES',
                'subfamily' => 'CARPETAS',
                'product' => [
                    'name' => 'Mesa colegial individual',
                    'description' => 'Mesa individual para aula con tablero antirrayas y patas reforzadas.',
                    'brand' => 'EducaPlus',
                ],
                'skus' => [
                    ['variant_name' => 'Verde', 'sale_price' => 220.00, 'purchase_price' => 130.00,
                        'attrs' => ['color' => 'verde', 'material' => 'metal']],
                ],
            ],
            [
                'family' => 'INSTITUCIONES',
                'subfamily' => 'BANCAS',
                'product' => [
                    'name' => 'Silla universitaria con paleta',
                    'description' => 'Silla universitaria con paleta de escritura abatible.',
                    'brand' => 'EducaPlus',
                ],
                'skus' => [
                    ['variant_name' => 'Negro', 'sale_price' => 290.00, 'purchase_price' => 175.00,
                        'attrs' => ['color' => 'negro', 'material' => 'metal']],
                    ['variant_name' => 'Gris', 'sale_price' => 290.00, 'purchase_price' => 175.00,
                        'attrs' => ['color' => 'gris', 'material' => 'metal']],
                ],
            ],
            [
                'family' => 'INSTITUCIONES',
                'subfamily' => 'BIBLIOTECA',
                'product' => [
                    'name' => 'Estantería biblioteca 5 niveles',
                    'description' => 'Estantería abierta de 5 niveles para biblioteca o salón de clases.',
                    'brand' => 'EducaPlus',
                ],
                'skus' => [
                    ['variant_name' => 'Wengue', 'sale_price' => 780.00, 'purchase_price' => 460.00,
                        'attrs' => ['color' => 'wengue', 'material' => 'melamina']],
                ],
            ],
        ];
    }
}
