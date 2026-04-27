<?php

use App\Models\Movement;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.mobile')]
#[Title('Mis ventas · Morrav')]
class extends Component {
    /**
     * Movimientos de venta del usuario actual del día de hoy, con sus líneas.
     */
    #[Computed]
    public function sales()
    {
        return Movement::query()
            ->where('type', 'sale')
            ->where('status', 'confirmed')
            ->where('created_by', Auth::id())
            ->whereDate('occurred_at', today())
            ->with(['lines.sku.product:id,name', 'lines.warehouse:id,code'])
            ->orderByDesc('occurred_at')
            ->get();
    }

    #[Computed]
    public function totalUnits(): float
    {
        $total = 0.0;
        foreach ($this->sales as $sale) {
            $total += (float) $sale->lines->sum('quantity');
        }

        return $total;
    }

    #[Computed]
    public function totalRevenue(): float
    {
        $total = 0.0;
        foreach ($this->sales as $sale) {
            foreach ($sale->lines as $line) {
                $price = $line->sku?->sale_price;
                if ($price !== null) {
                    $total += (float) $price * (float) $line->quantity;
                }
            }
        }

        return $total;
    }
}; ?>

<div class="flex flex-col flex-1">
    <header class="flex items-center gap-3 px-4 py-4 border-b border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900">
        <a href="{{ route('m.index') }}" class="size-10 rounded-full flex items-center justify-center hover:bg-zinc-100 dark:hover:bg-zinc-800 active:scale-95 transition">
            <flux:icon.arrow-left class="size-5" />
        </a>
        <div class="flex-1">
            <flux:heading>Mis ventas de hoy</flux:heading>
            <flux:text size="sm" class="text-zinc-500">{{ now()->translatedFormat('l j \\d\\e F') }}</flux:text>
        </div>
    </header>

    <div class="flex-1 flex flex-col p-4 gap-4">
        {{-- Resumen --}}
        <div class="grid grid-cols-2 gap-3">
            <div class="rounded-2xl bg-white dark:bg-zinc-900 p-4 border border-zinc-200 dark:border-zinc-800">
                <flux:text size="xs" class="text-zinc-500">Ventas</flux:text>
                <div class="text-3xl font-bold">{{ $this->sales->count() }}</div>
            </div>
            <div class="rounded-2xl bg-white dark:bg-zinc-900 p-4 border border-zinc-200 dark:border-zinc-800">
                <flux:text size="xs" class="text-zinc-500">Unidades</flux:text>
                <div class="text-3xl font-bold">{{ rtrim(rtrim(number_format($this->totalUnits, 2), '0'), '.') ?: '0' }}</div>
            </div>
        </div>

        @if ($this->totalRevenue > 0)
            <div class="rounded-2xl bg-gradient-to-br from-emerald-600 to-emerald-700 text-white p-5 shadow-lg">
                <flux:text size="sm" class="text-white/80">Total estimado</flux:text>
                <div class="mt-1 text-3xl font-bold">S/ {{ number_format($this->totalRevenue, 2) }}</div>
                <flux:text size="xs" class="text-white/70 mt-1">Calculado con precio de venta del SKU</flux:text>
            </div>
        @endif

        {{-- Lista --}}
        @if ($this->sales->isEmpty())
            <div class="flex-1 flex flex-col items-center justify-center gap-3 text-center text-zinc-500 p-6">
                <flux:icon.shopping-cart class="size-12 text-zinc-400" />
                <flux:text>Aún no registraste ventas hoy.</flux:text>
                <flux:button variant="primary" :href="route('m.sell')" icon="plus" class="mt-3">
                    Hacer una venta
                </flux:button>
            </div>
        @else
            <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($this->sales as $sale)
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <code class="text-xs font-mono text-zinc-500">{{ $sale->number }}</code>
                            <flux:text size="xs" class="text-zinc-500">{{ $sale->occurred_at?->format('H:i') }}</flux:text>
                        </div>
                        @foreach ($sale->lines as $line)
                            <div class="mt-2 flex items-center justify-between">
                                <div>
                                    <flux:text class="font-medium">{{ $line->sku?->product?->name }}</flux:text>
                                    @if ($line->sku?->variant_name)
                                        <flux:text size="xs" class="text-zinc-500">{{ $line->sku->variant_name }}</flux:text>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <div class="font-semibold">{{ rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.') }} u</div>
                                    <flux:text size="xs" class="text-zinc-500">{{ $line->warehouse?->code }}</flux:text>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
