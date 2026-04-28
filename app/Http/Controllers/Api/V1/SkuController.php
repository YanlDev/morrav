<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SkuResource;
use App\Models\Sku;
use App\Models\SkuExternalCode;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SkuController extends Controller
{
    /**
     * Busca un SKU por su `internal_code` o por cualquier `code` registrado
     * en `sku_external_codes` (códigos de proveedor, EAN, etc.). Devuelve
     * el SKU + stock por almacén activo. Espejo del flujo "Consultar" móvil.
     */
    public function lookup(Request $request): SkuResource
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:120'],
        ]);

        $code = $this->normalizeCode($data['code']);

        $sku = Sku::query()
            ->with('product:id,name,internal_code')
            ->where('internal_code', $code)
            ->first();

        if (! $sku) {
            $external = SkuExternalCode::query()
                ->where('code', $code)
                ->with('sku.product:id,name,internal_code')
                ->first();

            $sku = $external?->sku;
        }

        if (! $sku) {
            throw ValidationException::withMessages([
                'code' => "SKU «{$code}» no encontrado.",
            ]);
        }

        return $this->withStock($sku);
    }

    /**
     * Detalle del SKU + stock por almacén. La binding implícita resuelve
     * el modelo y dispara 404 automático si no existe.
     */
    public function show(Sku $sku): SkuResource
    {
        $sku->load('product:id,name,internal_code');

        return $this->withStock($sku);
    }

    private function withStock(Sku $sku): SkuResource
    {
        $warehouses = Warehouse::query()->active()->orderBy('code')->get();
        $stockMap = $sku->stockAtMany($warehouses->pluck('id')->all());

        $rows = $warehouses->map(fn (Warehouse $wh) => [
            'warehouse' => $wh,
            'qty' => $stockMap[$wh->id] ?? 0.0,
        ])->all();

        return (new SkuResource($sku))->withStock($rows);
    }

    /**
     * Limpia el código entrante: si llega como URL (caso de QR codificado
     * con `https://.../products/by-sku/SKU-XXXXXX`), extrae el último
     * segmento. Espeja la lógica del Livewire móvil.
     */
    private function normalizeCode(string $raw): string
    {
        $code = trim($raw);

        if (str_contains($code, '/')) {
            $code = trim(basename(rtrim($code, '/')));
        }

        return $code;
    }
}
