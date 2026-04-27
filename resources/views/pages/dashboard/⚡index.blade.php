<?php

use App\Models\Movement;
use App\Models\Sku;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    public const MOVEMENT_TYPES = [
        'inbound' => 'Ingreso',
        'outbound' => 'Salida',
        'transfer' => 'Traspaso',
        'adjustment' => 'Ajuste',
        'initial_load' => 'Carga inicial',
    ];

    #[Computed]
    public function totalSkus(): int
    {
        return Sku::query()->count();
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function skusByStatus(): array
    {
        return Sku::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    #[Computed]
    public function movementsTodayCount(): int
    {
        return Movement::query()->whereDate('occurred_at', today())->count();
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function movementsTodayByStatus(): array
    {
        return Movement::query()
            ->selectRaw('status, COUNT(*) as count')
            ->whereDate('occurred_at', today())
            ->groupBy('status')
            ->pluck('count', 'status')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Cuenta de pares (sku, almacén) con stock negativo.
     * Señal directa de datos corruptos o salidas sin ingreso previo.
     */
    #[Computed]
    public function negativeStockCount(): int
    {
        return DB::table('movement_lines as ml')
            ->join('movements as m', 'ml.movement_id', '=', 'm.id')
            ->where('m.status', 'confirmed')
            ->select('ml.sku_id', 'ml.warehouse_id')
            ->groupBy('ml.sku_id', 'ml.warehouse_id')
            ->havingRaw("SUM(CASE WHEN ml.direction = 'in' THEN ml.quantity ELSE -ml.quantity END) < 0")
            ->get()
            ->count();
    }

    #[Computed]
    public function recentMovements(): EloquentCollection
    {
        return Movement::query()
            ->with([
                'creator:id,name',
                'originWarehouse:id,code,name',
                'destinationWarehouse:id,code,name',
            ])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->take(5)
            ->get();
    }
}; ?>

<section class="w-full p-6">
    <div class="flex flex-col gap-6">
        <div>
            <flux:heading size="xl">Dashboard</flux:heading>
            <flux:text>Resumen operativo del inventario.</flux:text>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <a href="{{ route('products.index') }}" wire:navigate
                class="rounded-xl border border-zinc-200 bg-white p-5 transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600 dark:hover:bg-zinc-800">
                <div class="flex items-start justify-between">
                    <div class="text-sm text-zinc-500">SKUs totales</div>
                    <flux:icon.cube class="size-5 text-zinc-400" />
                </div>
                <div class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ number_format($this->totalSkus) }}
                </div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    @if (($this->skusByStatus['active'] ?? 0) > 0)
                        <flux:badge color="green" size="sm" inset="top bottom">
                            {{ $this->skusByStatus['active'] ?? 0 }} activos
                        </flux:badge>
                    @endif
                    @if (($this->skusByStatus['draft'] ?? 0) > 0)
                        <flux:badge color="amber" size="sm" inset="top bottom">
                            {{ $this->skusByStatus['draft'] ?? 0 }} borrador
                        </flux:badge>
                    @endif
                    @if (($this->skusByStatus['discontinued'] ?? 0) > 0)
                        <flux:badge color="zinc" size="sm" inset="top bottom">
                            {{ $this->skusByStatus['discontinued'] ?? 0 }} descontinuados
                        </flux:badge>
                    @endif
                </div>
            </a>

            <a href="{{ route('movements.index') }}" wire:navigate
                class="rounded-xl border border-zinc-200 bg-white p-5 transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600 dark:hover:bg-zinc-800">
                <div class="flex items-start justify-between">
                    <div class="text-sm text-zinc-500">Movimientos hoy</div>
                    <flux:icon.arrows-right-left class="size-5 text-zinc-400" />
                </div>
                <div class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ number_format($this->movementsTodayCount) }}
                </div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    @if (($this->movementsTodayByStatus['confirmed'] ?? 0) > 0)
                        <flux:badge color="green" size="sm" inset="top bottom">
                            {{ $this->movementsTodayByStatus['confirmed'] ?? 0 }} confirmados
                        </flux:badge>
                    @endif
                    @if (($this->movementsTodayByStatus['draft'] ?? 0) > 0)
                        <flux:badge color="amber" size="sm" inset="top bottom">
                            {{ $this->movementsTodayByStatus['draft'] ?? 0 }} borrador
                        </flux:badge>
                    @endif
                    @if (($this->movementsTodayByStatus['voided'] ?? 0) > 0)
                        <flux:badge color="red" size="sm" inset="top bottom">
                            {{ $this->movementsTodayByStatus['voided'] ?? 0 }} anulados
                        </flux:badge>
                    @endif
                </div>
            </a>

            <a href="{{ route('stock.index', ['s' => 'negative']) }}" wire:navigate
                class="rounded-xl border p-5 transition {{ $this->negativeStockCount > 0
                    ? 'border-red-200 bg-red-50 hover:border-red-300 hover:bg-red-100 dark:border-red-900/50 dark:bg-red-950/20 dark:hover:border-red-800 dark:hover:bg-red-950/40'
                    : 'border-zinc-200 bg-white hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600 dark:hover:bg-zinc-800' }}">
                <div class="flex items-start justify-between">
                    <div class="text-sm {{ $this->negativeStockCount > 0 ? 'text-red-700 dark:text-red-400' : 'text-zinc-500' }}">
                        Alertas de stock negativo
                    </div>
                    <flux:icon.exclamation-triangle class="size-5 {{ $this->negativeStockCount > 0 ? 'text-red-500' : 'text-zinc-400' }}" />
                </div>
                <div class="mt-2 text-3xl font-semibold {{ $this->negativeStockCount > 0 ? 'text-red-800 dark:text-red-300' : 'text-zinc-900 dark:text-zinc-100' }}">
                    {{ number_format($this->negativeStockCount) }}
                </div>
                <div class="mt-3 text-xs {{ $this->negativeStockCount > 0 ? 'text-red-700 dark:text-red-400' : 'text-zinc-500' }}">
                    @if ($this->negativeStockCount > 0)
                        Pares (SKU, almacén) con stock bajo cero. Revisar ingresos faltantes.
                    @else
                        Sin alertas de stock negativo.
                    @endif
                </div>
            </a>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading size="lg">Movimientos recientes</flux:heading>
                <flux:link :href="route('movements.index')" wire:navigate class="text-sm">Ver todos</flux:link>
            </div>

            @if ($this->recentMovements->isEmpty())
                <div class="p-8 text-center text-zinc-500">
                    Aún no hay movimientos registrados.
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Número</flux:table.column>
                        <flux:table.column>Tipo</flux:table.column>
                        <flux:table.column>Fecha</flux:table.column>
                        <flux:table.column>Almacén</flux:table.column>
                        <flux:table.column>Estado</flux:table.column>
                        <flux:table.column>Creado por</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->recentMovements as $movement)
                            <flux:table.row :key="$movement->id">
                                <flux:table.cell variant="strong">
                                    <a href="{{ route('movements.show', $movement) }}" wire:navigate
                                        class="font-mono text-xs underline decoration-dotted underline-offset-2">
                                        {{ $movement->number }}
                                    </a>
                                </flux:table.cell>
                                <flux:table.cell>
                                    {{ static::MOVEMENT_TYPES[$movement->type] ?? $movement->type }}
                                </flux:table.cell>
                                <flux:table.cell class="text-zinc-500 text-sm">
                                    {{ $movement->occurred_at?->format('d/m/Y H:i') }}
                                </flux:table.cell>
                                <flux:table.cell class="text-zinc-500 text-sm">
                                    @if ($movement->type === 'transfer')
                                        {{ $movement->originWarehouse?->code }} → {{ $movement->destinationWarehouse?->code }}
                                    @else
                                        {{ $movement->originWarehouse?->code ?? $movement->destinationWarehouse?->code ?? '—' }}
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($movement->status === 'confirmed')
                                        <flux:badge color="green" size="sm" inset="top bottom">Confirmado</flux:badge>
                                    @elseif ($movement->status === 'draft')
                                        <flux:badge color="amber" size="sm" inset="top bottom">Borrador</flux:badge>
                                    @else
                                        <flux:badge color="red" size="sm" inset="top bottom">Anulado</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="text-zinc-500 text-sm">
                                    {{ $movement->creator?->name ?? '—' }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    </div>
</section>
