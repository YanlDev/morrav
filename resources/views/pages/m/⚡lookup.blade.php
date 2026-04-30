<?php

use App\Models\Sku;
use App\Models\Warehouse;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.mobile')]
#[Title('Consultar · Morrav')]
class extends Component {
    public string $scannedCode = '';

    public ?int $skuId = null;

    public function lookupSku(): void
    {
        $code = trim($this->scannedCode);

        if ($code === '') {
            return;
        }

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

        $this->skuId = $sku->id;
    }

    #[Computed]
    public function sku(): ?Sku
    {
        return $this->skuId
            ? Sku::with('product:id,name,internal_code')->find($this->skuId)
            : null;
    }

    /**
     * Stock por almacén activo para el SKU seleccionado.
     *
     * @return array<int, array{warehouse: Warehouse, qty: float}>
     */
    #[Computed]
    public function stockByWarehouse(): array
    {
        if (! $this->skuId) {
            return [];
        }

        $warehouses = Warehouse::query()->active()->orderBy('code')->get();

        $totals = DB::table('movement_lines as ml')
            ->join('movements as m', 'ml.movement_id', '=', 'm.id')
            ->where('ml.sku_id', $this->skuId)
            ->whereIn('ml.warehouse_id', $warehouses->pluck('id'))
            ->where('m.status', 'confirmed')
            ->groupBy('ml.warehouse_id')
            ->select('ml.warehouse_id')
            ->selectRaw("SUM(CASE WHEN ml.direction = 'in' THEN ml.quantity ELSE -ml.quantity END) as qty")
            ->get();

        $result = [];
        foreach ($warehouses as $wh) {
            $row = $totals->firstWhere('warehouse_id', $wh->id);
            $result[] = ['warehouse' => $wh, 'qty' => $row ? (float) $row->qty : 0.0];
        }

        return $result;
    }

    public function reset_(): void
    {
        $this->reset(['scannedCode', 'skuId']);
    }
}; ?>

<div class="flex flex-col flex-1">
    <header class="flex items-center gap-3 px-4 py-4 border-b border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900">
        <a href="{{ route('m.index') }}" class="size-10 rounded-full flex items-center justify-center hover:bg-zinc-100 dark:hover:bg-zinc-800 active:scale-95 transition">
            <flux:icon.arrow-left class="size-5" />
        </a>
        <div class="flex-1">
            <flux:heading>Consultar stock</flux:heading>
            <flux:text size="sm" class="text-zinc-500">Escanea para ver disponibilidad</flux:text>
        </div>
    </header>

    <div class="flex-1 flex flex-col p-4 gap-4">
        @if (! $this->sku)
            <x-qr-scanner target="lookupSku" wireModel="scannedCode" />
        @else
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
                        <code class="text-xs font-mono text-zinc-500 mt-2 inline-block">{{ $this->sku->internal_code }}</code>
                    </div>
                </div>
                @if ($this->sku->sale_price !== null)
                    <div class="mt-3 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                        <flux:text size="xs" class="text-zinc-500">Precio</flux:text>
                        <div class="text-2xl font-bold">S/ {{ number_format((float) $this->sku->sale_price, 2) }}</div>
                    </div>
                @endif
            </div>

            <div class="rounded-2xl bg-white dark:bg-zinc-900 p-5 shadow-sm border border-zinc-200 dark:border-zinc-800">
                <flux:heading size="sm">Stock por almacén</flux:heading>
                <div class="mt-3 divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->stockByWarehouse as $row)
                        <div class="flex items-center justify-between py-3">
                            <div>
                                <code class="text-sm font-mono">{{ $row['warehouse']->code }}</code>
                                <flux:text size="xs" class="text-zinc-500">{{ $row['warehouse']->name }}</flux:text>
                            </div>
                            <div class="text-xl font-bold {{ $row['qty'] > 0 ? 'text-emerald-600' : 'text-zinc-400' }}">
                                {{ rtrim(rtrim(number_format($row['qty'], 2), '0'), '.') ?: '0' }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-auto">
                <flux:button variant="primary" wire:click="reset_" class="w-full h-14 text-base" icon="qr-code">
                    Escanear otro
                </flux:button>
            </div>
        @endif
    </div>
</div>
