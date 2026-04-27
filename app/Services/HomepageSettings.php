<?php

namespace App\Services;

/**
 * Configuración editable del landing público.
 *
 * Persistencia en JSON simple (storage/app/homepage.json) para evitar
 * una migración para cuatro campos. Los defaults reproducen el contenido
 * que tenía el landing antes de hacerse editable, así si el archivo no
 * existe el sitio luce idéntico.
 *
 * @phpstan-type StoreData array{name: string, badge: string, address: string, hours: string, phone: string, whatsapp: string, show_whatsapp: bool, lat: float, lng: float}
 * @phpstan-type Settings array{whatsapp_main: string, whatsapp_main_display: string, email_sales: string, email_contracts: string, stores: list<StoreData>}
 */
class HomepageSettings
{
    private const PATH = 'app/homepage.json';

    /**
     * @return Settings
     */
    public static function defaults(): array
    {
        return [
            'whatsapp_main' => '51999000111',
            'whatsapp_main_display' => '+51 999 000 111',
            'email_sales' => 'ventas@morravoffice.com',
            'email_contracts' => 'contratos@morravoffice.com',
            'stores' => [
                [
                    'name' => 'TIENDA CENTRAL',
                    'badge' => 'Principal',
                    'address' => 'Jr. San Román 845, Cercado',
                    'hours' => 'Lun – Sáb · 9:00 – 19:00',
                    'phone' => '051 32 1234',
                    'whatsapp' => '51999000111',
                    'show_whatsapp' => true,
                    'lat' => -15.4974,
                    'lng' => -70.1313,
                ],
                [
                    'name' => 'TIENDA MERCADO',
                    'badge' => 'Showroom',
                    'address' => 'Jr. Lima 1124, frente Mcdo. Túpac Amaru',
                    'hours' => 'Lun – Sáb · 9:00 – 18:30',
                    'phone' => '051 32 5678',
                    'whatsapp' => '51999000222',
                    'show_whatsapp' => true,
                    'lat' => -15.5028,
                    'lng' => -70.1267,
                ],
                [
                    'name' => 'TIENDA SALIDA CUSCO',
                    'badge' => 'Almacén',
                    'address' => 'Av. Huancané 2305, Salida Cusco',
                    'hours' => 'Lun – Sáb · 8:30 – 18:00',
                    'phone' => '051 32 9012',
                    'whatsapp' => '51999000333',
                    'show_whatsapp' => true,
                    'lat' => -15.4895,
                    'lng' => -70.1389,
                ],
            ],
        ];
    }

    /**
     * Lee del archivo o devuelve defaults. Hace merge con defaults para
     * tolerar archivos parciales (campos nuevos agregados después).
     *
     * @return Settings
     */
    public static function all(): array
    {
        $path = storage_path(self::PATH);

        if (! file_exists($path)) {
            return self::defaults();
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            return self::defaults();
        }

        // Merge top-level keys, pero NUNCA recursivo — para listas como 'stores'
        // necesitamos que el array guardado reemplace al default por completo,
        // no que se mezcle por índice (que dejaría los defaults sobrantes).
        return array_replace(self::defaults(), $data);
    }

    /**
     * @param  Settings  $data
     */
    public static function save(array $data): void
    {
        $path = storage_path(self::PATH);
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    public static function clear(): void
    {
        $path = storage_path(self::PATH);

        if (file_exists($path)) {
            @unlink($path);
        }
    }
}
