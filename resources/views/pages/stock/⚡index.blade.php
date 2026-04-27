<?php

use App\Models\Family;
use App\Models\Sku;
use App\Models\Subfamily;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Stock')] class extends Component {
    use WithPagination;

    public const STOCK_FILTERS = [
        '' => 'Todos',
        'with_stock' => 'Con stock',
        'zero' => 'En cero',
        'negative' => 'Negativo',
    ];

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'f')]
    public string $familyFilter = '';

    #[Url(as: 'sf')]
    public string $subfamilyFilter = '';

    #[Url(as: 's')]
    public string $stockFilter = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFamilyFilter(): void
    {
        $this->subfamilyFilter = '';
        $this->resetPage();
    }

    public function updatingSubfamilyFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStockFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function warehouses(): EloquentCollection
    {
        return Warehouse::query()->active()->orderBy('code')->get();
    }

    #[Computed]
    public function families(): EloquentCollection
    {
        return Family::query()->active()->orderBy('name')->get();
    }

    #[Computed]
    public function subfamilies(): EloquentCollection
    {
        if (! $this->familyFilter) {
            return new EloquentCollection;
        }

        return Subfamily::query()
            ->where('family_id', $this->familyFilter)
            ->active()
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function skus()
    {
        $filteredSkuIds = $this->stockFilter !== ''
            ? $this->skuIdsMatchingStockFilter()
            : null;

        return Sku::query()
            ->with([
                'product:id,internal_code,name,family_id,subfamily_id',
                'product.family:id,code,name',
                'product.subfamily:id,code,name',
            ])
            ->when($this->search !== '', function ($query) {
                $term = '%'.mb_strtolower($this->search).'%';
                $query->where(function ($inner) use ($term) {
                    $inner->whereRaw('LOWER(internal_code) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(variant_name) LIKE ?', [$term])
                        ->orWhereHas('product', fn ($p) => $p
                            ->whereRaw('LOWER(name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(internal_code) LIKE ?', [$term]));
                });
            })
            ->when($this->familyFilter !== '', fn ($query) => $query
                ->whereHas('product', fn ($p) => $p->where('family_id', $this->familyFilter)))
            ->when($this->subfamilyFilter !== '', fn ($query) => $query
                ->whereHas('product', fn ($p) => $p->where('subfamily_id', $this->subfamilyFilter)))
            ->when($filteredSkuIds !== null, fn ($query) => $query->whereIn('id', $filteredSkuIds))
            ->orderBy('internal_code')
            ->paginate(30);
    }

    /**
     * Matriz de stock: [sku_id][warehouse_id] => cantidad.
     * Una sola consulta agregada para todos los SKUs de la página actual.
     *
     * @return array<int, array<int, float>>
     */
    #[Computed]
    public function stockMatrix(): array
    {
        $skuIds = collect($this->skus->items())->pluck('id');

        if ($skuIds->isEmpty()) {
            return [];
        }

        $rows = DB::table('movement_lines as ml')
            ->join('movements as m', 'ml.movement_id', '=', 'm.id')
            ->where('m.status', 'confirmed')
            ->whereIn('ml.sku_id', $skuIds)
            ->select('ml.sku_id', 'ml.warehouse_id', DB::raw(
                "SUM(CASE WHEN ml.direction = 'in' THEN ml.quantity ELSE -ml.quantity END) as total"
            ))
            ->groupBy('ml.sku_id', 'ml.warehouse_id')
            ->get();

        $matrix = [];

        foreach ($rows as $row) {
            $matrix[(int) $row->sku_id][(int) $row->warehouse_id] = (float) $row->total;
        }

        return $matrix;
    }

    /**
     * Estadísticas globales del filtro actual (no solo la página).
     *
     * @return array{skus: int, with_stock: int, zero: int, negative: int}
     */
    #[Computed]
    public function totals(): array
    {
        $totalsBySku = $this->totalsBySku();

        $withStock = 0;
        $zero = 0;
        $negative = 0;

        foreach ($totalsBySku as $total) {
            if ($total > 0) {
                $withStock++;
            } elseif ($total < 0) {
                $negative++;
            } else {
                $zero++;
            }
        }

        return [
            'skus' => Sku::query()->count(),
            'with_stock' => $withStock,
            'zero' => $zero,
            'negative' => $negative,
        ];
    }

    /**
     * Stock total (todas las bodegas) por SKU.
     */
    private function totalsBySku(): Collection
    {
        return DB::table('movement_lines as ml')
            ->join('movements as m', 'ml.movement_id', '=', 'm.id')
            ->where('m.status', 'confirmed')
            ->select('ml.sku_id', DB::raw(
                "SUM(CASE WHEN ml.direction = 'in' THEN ml.quantity ELSE -ml.quantity END) as total"
            ))
            ->groupBy('ml.sku_id')
            ->pluck('total', 'sku_id');
    }

    /**
     * IDs de SKUs cuyo stock total cumple el filtro de estado.
     *
     * @return array<int, int>
     */
    private function skuIdsMatchingStockFilter(): array
    {
        $totals = $this->totalsBySku();

        $filtered = match ($this->stockFilter) {
            'with_stock' => $totals->filter(fn ($t) => $t > 0),
            'zero' => $totals->filter(fn ($t) => (float) $t === 0.0),
            'negative' => $totals->filter(fn ($t) => $t < 0),
            default => $totals,
        };

        $skuIds = $filtered->keys()->map(fn ($k) => (int) $k)->all();

        if ($this->stockFilter === 'zero') {
            $skuIdsWithAnyMovement = $totals->keys()->map(fn ($k) => (int) $k)->all();
            $skuIdsWithoutMovements = Sku::query()
                ->whereNotIn('id', $skuIdsWithAnyMovement)
                ->pluck('id')
                ->all();

            $skuIds = array_merge($skuIds, $skuIdsWithoutMovements);
        }

        return $skuIds;
    }
}; ?>

<section class="w-full p-6">
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <flux:heading size="xl">Stock</flux:heading>
            <flux:text>
                Stock actual por SKU y almacén. Calculado desde movimientos confirmados — sin campo mutable.
            </flux:text>
        </div>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="text-xs text-zinc-500">SKUs totales</div>
                <div class="text-2xl font-semibold">{{ $this->totals['skus'] }}</div>
            </div>
            <div class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-900/50 dark:bg-green-950/20">
                <div class="text-xs text-green-700 dark:text-green-400">Con stock</div>
                <div class="text-2xl font-semibold text-green-800 dark:text-green-300">{{ $this->totals['with_stock'] }}</div>
            </div>
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="text-xs text-zinc-500">En cero</div>
                <div class="text-2xl font-semibold">{{ $this->totals['zero'] }}</div>
            </div>
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900/50 dark:bg-red-950/20">
                <div class="text-xs text-red-700 dark:text-red-400">Negativo</div>
                <div class="text-2xl font-semibold text-red-800 dark:text-red-300">{{ $this->totals['negative'] }}</div>
            </div>
        </div>

        <div class="flex flex-col gap-3 md:flex-row">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Buscar por código SKU, nombre de producto o variante..."
                class="flex-1"
            />

            <flux:select wire:model.live="familyFilter" placeholder="Todas las familias" class="md:w-48">
                <flux:select.option value="">Todas las familias</flux:select.option>
                @foreach ($this->families as $family)
                    <flux:select.option :value="$family->id">{{ $family->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select
                wire:model.live="subfamilyFilter"
                :disabled="! $familyFilter"
                class="md:w-48"
            >
                <flux:select.option value="">Todas las subfamilias</flux:select.option>
                @foreach ($this->subfamilies as $subfamily)
                    <flux:select.option :value="$subfamily->id">{{ $subfamily->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="stockFilter" class="md:w-40">
                @foreach (static::STOCK_FILTERS as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:table :paginate="$this->skus">
            <flux:table.columns>
                <flux:table.column>Código</flux:table.column>
                <flux:table.column>Producto</flux:table.column>
                <flux:table.column>Familia</flux:table.column>
                @foreach ($this->warehouses as $warehouse)
                    <flux:table.column class="text-right">
                        <span title="{{ $warehouse->name }}">{{ $warehouse->code }}</span>
                    </flux:table.column>
                @endforeach
                <flux:table.column class="text-right">Total</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->skus as $sku)
                    @php
                        $skuStocks = $this->stockMatrix[$sku->id] ?? [];
                        $total = array_sum($skuStocks);
                    @endphp
                    <flux:table.row :key="$sku->id">
                        <flux:table.cell variant="strong">
                            <code class="text-xs font-mono">{{ $sku->internal_code }}</code>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span>{{ $sku->product?->name }}</span>
                                @if ($sku->variant_name)
                                    <span class="text-xs text-zinc-500">{{ $sku->variant_name }}</span>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500 text-sm">
                            <div class="flex flex-col">
                                <span>{{ $sku->product?->family?->name ?? '—' }}</span>
                                @if ($sku->product?->subfamily)
                                    <span class="text-xs">{{ $sku->product->subfamily->name }}</span>
                                @endif
                            </div>
                        </flux:table.cell>
                        @foreach ($this->warehouses as $warehouse)
                            @php
                                $qty = $skuStocks[$warehouse->id] ?? 0.0;
                                $colorClass = match (true) {
                                    $qty < 0 => 'text-red-600 dark:text-red-400 font-semibold',
                                    $qty > 0 => 'text-zinc-900 dark:text-zinc-100',
                                    default => 'text-zinc-400',
                                };
                            @endphp
                            <flux:table.cell class="text-right {{ $colorClass }}">
                                {{ rtrim(rtrim(number_format($qty, 2), '0'), '.') ?: '0' }}
                            </flux:table.cell>
                        @endforeach
                        <flux:table.cell class="text-right font-semibold">
                            <span class="{{ $total < 0 ? 'text-red-600 dark:text-red-400' : ($total == 0 ? 'text-zinc-400' : '') }}">
                                {{ rtrim(rtrim(number_format($total, 2), '0'), '.') ?: '0' }}
                            </span>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ 4 + $this->warehouses->count() }}" class="text-center text-zinc-500 py-8">
                            No se encontraron SKUs con los filtros actuales.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
