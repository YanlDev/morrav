<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DamageController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SkuController;
use App\Http\Controllers\Api\V1\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
| El prefijo `api/v1` se aplica desde bootstrap/app.php (apiPrefix).
| Aquí solo definimos rutas relativas.
*/

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('warehouses', [WarehouseController::class, 'index']);

    // Importante: lookup va antes que el binding {sku} para que no
    // se interprete "lookup" como un id de SKU.
    Route::get('skus/lookup', [SkuController::class, 'lookup']);
    Route::get('skus/{sku}', [SkuController::class, 'show']);

    // Mismo razonamiento: `sales/mine` antes de cualquier binding futuro.
    Route::get('sales/mine', [SaleController::class, 'mine']);
    Route::post('sales', [SaleController::class, 'store']);

    Route::post('damages', [DamageController::class, 'store']);
});
