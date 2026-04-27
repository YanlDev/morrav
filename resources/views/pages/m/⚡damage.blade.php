<?php

use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Sku;
use App\Models\Warehouse;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.mobile')]
#[Title('Reportar dañado · Morrav')]
class extends Component {
    public string $scannedCode = '';

    public ?int $skuId = null;

    public ?int $warehouseId = null;

    public ?float $quantity = 1;

    public string $notes = '';

    public function mount(): void
    {
        $first = Warehouse::query()->where('type', 'store')->where('active', true)->orderBy('code')->first();
        $this->warehouseId = $first?->id;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Warehouse> */
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
        return $this->skuId ? Sku::with('product:id,name')->find($this->skuId) : null;
    }

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
        if (str_contains($code, '/')) {
            $code = trim(basename(rtrim($code, '/')));
        }

        $sku = Sku::where('internal_code', $code)->first();

        if (! $sku) {
            Flux::toast(variant: 'danger', text: "SKU «{$code}» no encontrado.");
            $this->reset(['scannedCode']);

            return;
        }

        $this->skuId = $sku->id;
    }

    public function report(): void
    {
        $this->validate([
            'warehouseId' => ['required', 'integer', 'exists:warehouses,id'],
            'skuId' => ['required', 'integer', 'exists:skus,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        if ((float) $this->quantity > $this->availableStock) {
            Flux::toast(variant: 'danger', text: "Solo hay {$this->availableStock} unidades disponibles.");

            return;
        }

        $workshop = Warehouse::query()->where('type', 'workshop')->where('active', true)->first();

        if (! $workshop) {
            Flux::toast(variant: 'danger', text: 'No hay taller configurado.');

            return;
        }

        DB::transaction(function () use ($workshop) {
            $note = $this->notes !== '' ? $this->notes : null;
            $reason = 'Reportado dañado'.($note ? ': '.$note : '');

            $movement = Movement::create([
                'number' => 'MOV-'.str_pad((string) ((Movement::max('id') ?? 0) + 1), 6, '0', STR_PAD_LEFT),
                'type' => 'transfer',
                'occurred_at' => now(),
                'reason' => $reason,
                'origin_warehouse_id' => $this->warehouseId,
                'destination_warehouse_id' => $workshop->id,
                'status' => 'confirmed',
                'created_by' => Auth::id(),
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ]);

            MovementLine::create([
                'movement_id' => $movement->id,
                'sku_id' => $this->skuId,
                'warehouse_id' => $this->warehouseId,
                'direction' => 'out',
                'quantity' => $this->quantity,
                'notes' => $note,
            ]);
            MovementLine::create([
                'movement_id' => $movement->id,
                'sku_id' => $this->skuId,
                'warehouse_id' => $workshop->id,
                'direction' => 'in',
                'quantity' => $this->quantity,
                'notes' => $note,
            ]);
        });

        Flux::toast(variant: 'success', text: 'Reporte registrado. Stock movido al taller.');
        $this->reset(['scannedCode', 'skuId', 'quantity', 'notes']);
        $this->quantity = 1;
    }
}; ?>

<div class="flex flex-col flex-1">
    <header class="flex items-center gap-3 px-4 py-4 border-b border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900">
        <a href="{{ route('m.index') }}" class="size-10 rounded-full flex items-center justify-center hover:bg-zinc-100 dark:hover:bg-zinc-800 active:scale-95 transition">
            <flux:icon.arrow-left class="size-5" />
        </a>
        <div class="flex-1">
            <flux:heading>Reportar dañado</flux:heading>
            <flux:text size="sm" class="text-zinc-500">Mueve unidades al taller</flux:text>
        </div>
    </header>

    <div class="flex-1 flex flex-col p-4 gap-4">
        <flux:field>
            <flux:label>Tienda</flux:label>
            <flux:select wire:model.live="warehouseId">
                @foreach ($this->stores as $store)
                    <flux:select.option :value="$store->id">{{ $store->code }} — {{ $store->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        @if (! $this->sku)
            <div class="flex-1 flex flex-col items-center justify-center gap-6 p-6">
                <div id="qr-reader" class="w-full max-w-xs aspect-square rounded-2xl overflow-hidden bg-zinc-900 hidden"></div>
                <div id="qr-placeholder" class="w-full max-w-xs aspect-square rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex flex-col items-center justify-center gap-3 border-2 border-dashed border-zinc-300 dark:border-zinc-700">
                    <flux:icon.qr-code class="size-16 text-zinc-400" />
                    <flux:text size="sm" class="text-zinc-500">Toca para escanear</flux:text>
                </div>
                <flux:button variant="primary" id="start-scan-btn" class="w-full max-w-xs" icon="camera">
                    Activar cámara
                </flux:button>
                <details class="w-full max-w-xs">
                    <summary class="text-sm text-zinc-500 cursor-pointer text-center">¿No funciona la cámara?</summary>
                    <div class="mt-3 flex gap-2">
                        <flux:input wire:model="scannedCode" placeholder="SKU-000001" wire:keydown.enter="lookupSku" />
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
                    if (!startBtn) return;
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
        @else
            <div class="rounded-2xl bg-white dark:bg-zinc-900 p-5 shadow-sm border border-zinc-200 dark:border-zinc-800">
                <flux:heading>{{ $this->sku->product?->name }}</flux:heading>
                @if ($this->sku->variant_name)
                    <flux:text class="text-zinc-500">{{ $this->sku->variant_name }}</flux:text>
                @endif
                <div class="mt-2 flex items-center gap-2">
                    <code class="text-xs font-mono text-zinc-500">{{ $this->sku->internal_code }}</code>
                    <flux:badge size="sm" :color="$this->availableStock > 0 ? 'green' : 'red'" inset="top bottom">
                        {{ rtrim(rtrim(number_format($this->availableStock, 2), '0'), '.') }} en stock
                    </flux:badge>
                </div>
            </div>

            <flux:input wire:model="quantity" type="number" step="1" min="1" :max="$this->availableStock" label="Cantidad dañada" inputmode="numeric" />

            <flux:textarea wire:model="notes" label="Motivo" rows="2" placeholder="Pata rota, tela manchada..." maxlength="255" />

            <div class="mt-auto flex flex-col gap-2">
                <flux:button variant="danger" wire:click="report" class="h-14 text-base" icon="exclamation-triangle">
                    Reportar al taller
                </flux:button>
                <flux:button variant="ghost" wire:click="$set('skuId', null)">
                    Cancelar
                </flux:button>
            </div>
        @endif
    </div>
</div>
