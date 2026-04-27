<?php

use App\Models\Sku;
use App\Models\Warehouse;
use App\Services\Sales\SaleService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.mobile')]
#[Title('Vender · Morrav')]
class extends Component {
    public ?int $warehouseId = null;

    public string $scannedCode = '';

    public ?int $skuId = null;

    public ?float $quantity = 1;

    public string $notes = '';

    /**
     * Estados: 'idle' (esperando escaneo), 'confirm' (mostrando datos del SKU),
     * 'done' (venta registrada).
     */
    public string $step = 'idle';

    public ?string $lastMovementNumber = null;

    public function mount(): void
    {
        $first = Warehouse::query()->where('type', 'store')->where('active', true)->orderBy('code')->first();
        $this->warehouseId = $first?->id;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Warehouse>
     */
    #[Computed]
    public function stores()
    {
        return Warehouse::query()
            ->where('type', 'store')
            ->where('active', true)
            ->orderBy('code')
            ->get();
    }

    #[Computed]
    public function sku(): ?Sku
    {
        return $this->skuId
            ? Sku::with('product:id,name,internal_code')->find($this->skuId)
            : null;
    }

    /**
     * Stock disponible del SKU seleccionado en el almacén elegido.
     */
    #[Computed]
    public function availableStock(): float
    {
        if (! $this->warehouseId || ! $this->sku) {
            return 0.0;
        }

        return $this->sku->stockAt($this->warehouseId);
    }

    public function lookupSku(): void
    {
        $code = trim($this->scannedCode);

        if ($code === '') {
            return;
        }

        // El QR codifica una URL del tipo .../products/by-sku/SKU-XXXXXX.
        // Si pegan o escanean la URL completa, extraemos el último segmento.
        if (str_contains($code, '/')) {
            $code = trim(basename(rtrim($code, '/')));
        }

        $sku = Sku::with('product:id,name,internal_code')
            ->where('internal_code', $code)
            ->first();

        if (! $sku) {
            Flux::toast(variant: 'danger', text: "SKU «{$code}» no encontrado.");
            $this->reset(['scannedCode']);

            return;
        }

        if ($sku->status !== 'active') {
            Flux::toast(variant: 'danger', text: 'Esta variante está descontinuada o en borrador.');
            $this->reset(['scannedCode']);

            return;
        }

        $this->skuId = $sku->id;
        $this->step = 'confirm';
        $this->quantity = 1;
        unset($this->availableStock);
    }

    public function confirm(): void
    {
        if (! $this->skuId || ! $this->warehouseId) {
            return;
        }

        $this->validate([
            'quantity' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $sku = Sku::findOrFail($this->skuId);
        $warehouse = Warehouse::findOrFail($this->warehouseId);

        try {
            $movement = app(SaleService::class)->sell(
                user: Auth::user(),
                sku: $sku,
                warehouse: $warehouse,
                quantity: (float) $this->quantity,
                notes: $this->notes !== '' ? $this->notes : null,
            );
        } catch (\RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->lastMovementNumber = $movement->number;
        $this->step = 'done';
        Flux::toast(variant: 'success', text: 'Venta registrada.');
    }

    public function newSale(): void
    {
        $this->reset(['scannedCode', 'skuId', 'quantity', 'notes', 'lastMovementNumber']);
        $this->step = 'idle';
        $this->quantity = 1;
    }
}; ?>

<div class="flex flex-col flex-1">
    {{-- Header con back y título --}}
    <header class="flex items-center gap-3 px-4 py-4 border-b border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900">
        <a href="{{ route('m.index') }}" class="size-10 rounded-full flex items-center justify-center hover:bg-zinc-100 dark:hover:bg-zinc-800 active:scale-95 transition">
            <flux:icon.arrow-left class="size-5" />
        </a>
        <div class="flex-1">
            <flux:heading>Vender</flux:heading>
            <flux:text size="sm" class="text-zinc-500">Escanea el QR del producto</flux:text>
        </div>
    </header>

    <div class="flex-1 flex flex-col p-4 gap-4">
        {{-- Selector de tienda --}}
        <flux:field>
            <flux:label>Tienda donde estás vendiendo</flux:label>
            <flux:select wire:model.live="warehouseId">
                @foreach ($this->stores as $store)
                    <flux:select.option :value="$store->id">{{ $store->code }} — {{ $store->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        {{-- ESTADO: idle (esperando escaneo) --}}
        @if ($step === 'idle')
            <div class="flex-1 flex flex-col items-center justify-center gap-6 p-6">
                <div id="qr-reader" class="w-full max-w-xs aspect-square rounded-2xl overflow-hidden bg-zinc-900 hidden"></div>

                <div id="qr-placeholder" class="w-full max-w-xs aspect-square rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex flex-col items-center justify-center gap-3 border-2 border-dashed border-zinc-300 dark:border-zinc-700">
                    <flux:icon.qr-code class="size-16 text-zinc-400" />
                    <flux:text size="sm" class="text-zinc-500">Toca para escanear</flux:text>
                </div>

                <flux:button variant="primary" id="start-scan-btn" class="w-full max-w-xs" icon="camera">
                    Activar cámara
                </flux:button>

                {{-- Fallback manual --}}
                <details class="w-full max-w-xs">
                    <summary class="text-sm text-zinc-500 cursor-pointer text-center">¿No funciona la cámara? Escribe el código</summary>
                    <div class="mt-3 flex gap-2">
                        <flux:input
                            wire:model="scannedCode"
                            placeholder="SKU-000001"
                            wire:keydown.enter="lookupSku"
                        />
                        <flux:button variant="primary" wire:click="lookupSku" icon="arrow-right" />
                    </div>
                </details>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
            <script>
                (function () {
                    const startBtn = document.getElementById('start-scan-btn');
                    const reader = document.getElementById('qr-reader');
                    const placeholder = document.getElementById('qr-placeholder');
                    let html5QrCode = null;

                    if (! startBtn) return;

                    startBtn.addEventListener('click', async () => {
                        if (html5QrCode) return;

                        placeholder.classList.add('hidden');
                        reader.classList.remove('hidden');
                        startBtn.style.display = 'none';

                        html5QrCode = new Html5Qrcode('qr-reader');
                        try {
                            await html5QrCode.start(
                                { facingMode: 'environment' },
                                { fps: 10, qrbox: { width: 220, height: 220 } },
                                async (decoded) => {
                                    await html5QrCode.stop();
                                    html5QrCode.clear();
                                    @this.set('scannedCode', decoded);
                                    @this.lookupSku();
                                },
                                () => {}
                            );
                        } catch (err) {
                            placeholder.classList.remove('hidden');
                            reader.classList.add('hidden');
                            startBtn.style.display = '';
                            alert('No se pudo acceder a la cámara: ' + err);
                        }
                    });
                })();
            </script>
        @endif

        {{-- ESTADO: confirm (SKU encontrado, pedir cantidad) --}}
        @if ($step === 'confirm' && $this->sku)
            <div class="rounded-2xl bg-white dark:bg-zinc-900 p-5 shadow-sm border border-zinc-200 dark:border-zinc-800">
                <div class="flex gap-4">
                    @if ($this->sku->photo)
                        <img
                            src="{{ $this->sku->photo }}"
                            alt="{{ $this->sku->product?->name }}"
                            class="size-24 rounded-xl object-cover bg-zinc-100 dark:bg-zinc-800 shrink-0"
                            loading="lazy"
                        >
                    @endif
                    <div class="flex-1 min-w-0">
                        <flux:text size="xs" class="text-zinc-500 uppercase tracking-wide">Producto</flux:text>
                        <flux:heading class="mt-1">{{ $this->sku->product?->name }}</flux:heading>
                        @if ($this->sku->variant_name)
                            <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">{{ $this->sku->variant_name }}</flux:text>
                        @endif
                        <div class="mt-3 flex items-center gap-2 flex-wrap">
                            <code class="text-xs font-mono">{{ $this->sku->internal_code }}</code>
                            <flux:badge size="sm" :color="$this->availableStock > 0 ? 'green' : 'red'" inset="top bottom">
                                {{ rtrim(rtrim(number_format($this->availableStock, 2), '0'), '.') }} en stock
                            </flux:badge>
                        </div>
                    </div>
                </div>

                @if ($this->sku->sale_price !== null)
                    <div class="mt-4 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                        <flux:text size="xs" class="text-zinc-500">Precio</flux:text>
                        <div class="text-2xl font-bold">S/ {{ number_format((float) $this->sku->sale_price, 2) }}</div>
                    </div>
                @endif
            </div>

            <flux:input
                wire:model="quantity"
                type="number"
                step="1"
                min="1"
                :max="$this->availableStock"
                label="Cantidad a vender"
                inputmode="numeric"
                class="text-2xl"
            />

            <flux:textarea
                wire:model="notes"
                label="Notas (opcional)"
                rows="2"
                placeholder="Cliente, comentario..."
                maxlength="255"
            />

            <div class="mt-auto flex flex-col gap-2">
                <flux:button variant="primary" wire:click="confirm" class="h-14 text-base" icon="check">
                    Confirmar venta
                </flux:button>
                <flux:button variant="ghost" wire:click="newSale">
                    Cancelar y escanear otro
                </flux:button>
            </div>
        @endif

        {{-- ESTADO: done (venta confirmada) --}}
        @if ($step === 'done')
            <div class="flex-1 flex flex-col items-center justify-center gap-6 p-6">
                <div class="size-20 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                    <flux:icon.check class="size-10 text-emerald-600" />
                </div>
                <div class="text-center">
                    <flux:heading size="lg">Venta registrada</flux:heading>
                    <flux:text class="mt-2 text-zinc-500">
                        Movimiento <code class="font-mono text-xs">{{ $lastMovementNumber }}</code>
                    </flux:text>
                </div>

                <div class="w-full max-w-xs flex flex-col gap-2 mt-auto">
                    <flux:button variant="primary" wire:click="newSale" class="h-14 text-base" icon="plus">
                        Registrar otra venta
                    </flux:button>
                    <flux:button variant="ghost" :href="route('m.index')">
                        Volver al menú
                    </flux:button>
                </div>
            </div>
        @endif
    </div>
</div>
