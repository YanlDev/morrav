<?php

use App\Models\Movement;
use App\Models\Sku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard.index')->name('dashboard');

    // Versión móvil de la app — pensada para el vendedor con celular en tienda.
    // Layout sin sidebar, optimizada para una sola mano.
    Route::prefix('m')->name('m.')->group(function () {
        Route::livewire('/', 'pages::m.index')->name('index');
        Route::livewire('vender', 'pages::m.sell')->name('sell');
        Route::livewire('consultar', 'pages::m.lookup')->name('lookup');
        Route::livewire('reportar-danado', 'pages::m.damage')->name('damage');
        Route::livewire('mis-ventas', 'pages::m.history')->name('history');
    });

    Route::livewire('users', 'pages::users.index')->name('users.index');
    Route::livewire('warehouses', 'pages::warehouses.index')->name('warehouses.index');
    Route::livewire('families', 'pages::families.index')->name('families.index');
    Route::livewire('attributes', 'pages::attributes.index')->name('attributes.index');
    Route::livewire('homepage', 'pages::homepage.index')->name('homepage.index');
    Route::livewire('products', 'pages::products.index')->name('products.index');
    Route::livewire('products/create', 'pages::products.create')->name('products.create');
    Route::livewire('products/{product}/skus', 'pages::products.skus')->name('products.skus');
    Route::livewire('movements', 'pages::movements.index')->name('movements.index');
    Route::livewire('movements/{movement}', 'pages::movements.show')->name('movements.show');
    Route::livewire('repair-orders', 'pages::repair-orders.index')->name('repair-orders.index');
    Route::livewire('repair-orders/{repairOrder}', 'pages::repair-orders.show')->name('repair-orders.show');
    Route::livewire('stock', 'pages::stock.index')->name('stock.index');
    Route::livewire('stickers', 'pages::stickers.index')->name('stickers.index');

    // Impresión de stickers: formato `?items=1x3,5x1` (sku_id x copias)
    // O bien `?movement={id}` para imprimir una etiqueta por cada unidad de un movimiento confirmado.
    Route::get('stickers/print', function (Request $request) {
        $movementId = $request->integer('movement');
        $itemsParam = (string) $request->query('items', '');

        $items = [];

        if ($movementId) {
            $movement = Movement::with('lines.sku.product')->find($movementId);

            if ($movement && $movement->status === 'confirmed') {
                foreach ($movement->lines as $line) {
                    if ($line->direction !== 'in') {
                        continue;
                    }
                    $items[$line->sku_id] = ($items[$line->sku_id] ?? 0) + (int) $line->quantity;
                }
            }
        }

        if ($itemsParam !== '') {
            foreach (explode(',', $itemsParam) as $chunk) {
                if (! preg_match('/^(\d+)x(\d+)$/', trim($chunk), $m)) {
                    continue;
                }
                $items[(int) $m[1]] = ($items[(int) $m[1]] ?? 0) + (int) $m[2];
            }
        }

        $skuIds = array_keys(array_filter($items, fn ($copies) => $copies > 0));

        $skus = Sku::with('product:id,name,internal_code')
            ->whereIn('id', $skuIds)
            ->get()
            ->keyBy('id');

        $stickers = [];
        foreach ($items as $skuId => $copies) {
            $sku = $skus->get($skuId);
            if (! $sku) {
                continue;
            }
            for ($i = 0; $i < $copies; $i++) {
                $stickers[] = $sku;
            }
        }

        return view('stickers.print', [
            'stickers' => $stickers,
            'baseUrl' => url('/'),
        ]);
    })->name('stickers.print');

    // Redirección del QR escaneado: /products/by-sku/SKU-000001 → detalle del producto
    Route::get('products/by-sku/{code}', function (string $code) {
        $sku = Sku::where('internal_code', $code)->first();

        abort_if(! $sku, 404, 'SKU no encontrado.');

        return redirect()->route('products.skus', $sku->product_id);
    })->name('products.by-sku');
});

require __DIR__.'/settings.php';
