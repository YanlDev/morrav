<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:generate-icons')]
#[Description('Genera los íconos PNG de la PWA (cuadrado wine + M cream) usando GD.')]
class GenerateIcons extends Command
{
    public function handle(): int
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->error('La extensión GD no está disponible.');

            return self::FAILURE;
        }

        $output = public_path('icons');
        if (! is_dir($output)) {
            mkdir($output, 0755, true);
        }

        $variants = [
            ['size' => 192, 'maskable' => false, 'file' => 'icon-192.png'],
            ['size' => 512, 'maskable' => false, 'file' => 'icon-512.png'],
            ['size' => 192, 'maskable' => true, 'file' => 'icon-192-maskable.png'],
            ['size' => 512, 'maskable' => true, 'file' => 'icon-512-maskable.png'],
        ];

        foreach ($variants as $variant) {
            $path = $output.DIRECTORY_SEPARATOR.$variant['file'];
            $this->generate($variant['size'], $variant['maskable'], $path);
            $this->info("Creado: public/icons/{$variant['file']}");
        }

        return self::SUCCESS;
    }

    /**
     * Dibuja un cuadrado wine con la "M" cream en el centro. La versión maskable
     * deja un margen extra para que el SO pueda recortar las esquinas en
     * dispositivos que aplican máscaras circulares o tipo squircle.
     */
    private function generate(int $size, bool $maskable, string $path): void
    {
        $image = imagecreatetruecolor($size, $size);

        $wine = imagecolorallocate($image, 0x8E, 0x1E, 0x3A);
        $cream = imagecolorallocate($image, 0xFA, 0xF8, 0xF3);

        imagefilledrectangle($image, 0, 0, $size, $size, $wine);

        // El "M" se dibuja con un path escalado al tamaño del ícono. Para maskable
        // dejamos 20% de margen en lugar del 10% normal.
        $margin = (int) ($size * ($maskable ? 0.22 : 0.12));
        $inner = $size - 2 * $margin;

        // Borde interior cream (1.5% del tamaño) tipo brand-mark del navbar.
        $borderWidth = max(2, (int) ($size * 0.015));
        for ($i = 0; $i < $borderWidth; $i++) {
            imagerectangle(
                $image,
                $margin + $i,
                $margin + $i,
                $size - $margin - 1 - $i,
                $size - $margin - 1 - $i,
                $cream
            );
        }

        // Letra "M" como polígono: replicamos el path SVG del logo
        // d="M10 10 H14 L20 22 L26 10 H30 V30 H26 V18 L22 26 H18 L14 18 V30 H10 Z"
        // (sobre un viewBox 40x40). Escalamos a nuestro recuadro interno.
        $padding = (int) ($inner * 0.18);
        $area = $inner - 2 * $padding;
        $ox = $margin + $padding;
        $oy = $margin + $padding;
        $scale = $area / 20.0;

        $points = [
            // Punto, x, y (en sistema 0..20)
            [0, 0], [4, 0], [10, 12], [16, 0], [20, 0], [20, 20],
            [16, 20], [16, 8], [12, 16], [8, 16], [4, 8], [4, 20], [0, 20],
        ];

        $polygon = [];
        foreach ($points as [$x, $y]) {
            $polygon[] = (int) ($ox + $x * $scale);
            $polygon[] = (int) ($oy + $y * $scale);
        }

        imagefilledpolygon($image, $polygon, $cream);

        imagepng($image, $path, 9);
        imagedestroy($image);
    }
}
