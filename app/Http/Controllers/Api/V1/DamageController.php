<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DamageReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDamageRequest;
use App\Http\Resources\DamageReportResource;
use App\Models\Sku;
use App\Models\Warehouse;
use App\Services\Damage\DamageService;
use Illuminate\Validation\ValidationException;

class DamageController extends Controller
{
    /**
     * Registra una unidad dañada vía DamageService::report. El movimiento
     * confirmado y el DamageReport se crean en una transacción; los errores
     * de negocio (sin stock, sin taller) regresan como 422 con campo `quantity`.
     */
    public function store(StoreDamageRequest $request, DamageService $service): DamageReportResource
    {
        $data = $request->validated();

        $sku = Sku::findOrFail($data['sku_id']);
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        $reason = isset($data['reason_code']) ? DamageReason::from($data['reason_code']) : null;

        try {
            $report = $service->report(
                user: $request->user(),
                sku: $sku,
                warehouse: $warehouse,
                quantity: (float) $data['quantity'],
                reason: $reason,
                notes: $data['notes'] ?? null,
            );
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['quantity' => $e->getMessage()]);
        }

        return new DamageReportResource($report);
    }
}
