<?php

use App\Enums\DamageReason;
use App\Models\Sku;
use App\Models\Warehouse;
use App\Services\Damage\DamageService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
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

    public ?string $reasonCode = null;

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
            'reasonCode' => ['nullable', 'string', 'in:'.implode(',', array_keys(DamageReason::options()))],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $sku = Sku::findOrFail($this->skuId);
        $warehouse = Warehouse::findOrFail($this->warehouseId);

        try {
            app(DamageService::class)->report(
                user: Auth::user(),
                sku: $sku,
                warehouse: $warehouse,
                quantity: (float) $this->quantity,
                reason: $this->reasonCode ? DamageReason::from($this->reasonCode) : null,
                notes: $this->notes !== '' ? $this->notes : null,
            );
        } catch (\RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: 'Reporte registrado. Stock movido al taller.');
        $this->reset(['scannedCode', 'skuId', 'quantity', 'reasonCode', 'notes']);
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
            <x-qr-scanner target="lookupSku" wireModel="scannedCode" />
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

            <flux:field>
                <flux:label>Tipo de daño</flux:label>
                <flux:select wire:model="reasonCode" placeholder="Selecciona una causa">
                    @foreach (DamageReason::options() as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:textarea wire:model="notes" label="Detalles" rows="2" placeholder="Pata rota, tela manchada..." maxlength="255" />

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
