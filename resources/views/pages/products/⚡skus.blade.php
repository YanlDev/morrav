<?php

use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Product;
use App\Models\Sku;
use App\Models\SkuAttribute;
use App\Models\Warehouse;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Producto')] class extends Component {
    use WithPagination;

    public const STATUSES = [
        'draft' => 'Borrador',
        'active' => 'Activo',
        'discontinued' => 'Descontinuado',
    ];

    public const TABS = [
        'overview' => 'Resumen',
        'variants' => 'Variantes',
        'stock' => 'Stock por sucursal',
        'movements' => 'Movimientos',
    ];

    public const MOVEMENT_TYPE_LABELS = [
        'inbound' => 'Ingreso',
        'outbound' => 'Salida',
        'transfer' => 'Traspaso',
        'adjustment' => 'Ajuste',
        'initial_load' => 'Carga inicial',
        'sale' => 'Venta',
    ];

    public Product $product;

    #[Url(as: 'tab')]
    public string $tab = 'overview';

    #[Url(as: 'q')]
    public string $search = '';

    public ?int $editingId = null;

    public string $internalCode = '';

    public string $variantName = '';

    public ?float $salePrice = null;

    public ?float $purchasePrice = null;

    public string $status = 'draft';

    /**
     * @var array<int, string> attribute_id => value
     */
    public array $attributeValues = [];

    public ?int $deletingId = null;

    public ?int $damageSkuId = null;

    public ?int $damageOriginWarehouseId = null;

    public ?float $damageQuantity = null;

    public string $damageNotes = '';

    public function mount(Product $product): void
    {
        $this->authorize('view', $product);
        $this->product = $product->load(['family', 'subfamily']);

        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = 'overview';
        }
    }

    public function setTab(string $tab): void
    {
        if (array_key_exists($tab, self::TABS)) {
            $this->tab = $tab;
            $this->resetPage();
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function skus()
    {
        return Sku::query()
            ->where('product_id', $this->product->id)
            ->with('attributeValues.attribute:id,code,name,type,unit')
            ->when($this->search, function ($query) {
                $term = '%'.mb_strtolower($this->search).'%';
                $query->where(fn ($q) => $q
                    ->whereRaw('LOWER(internal_code) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(variant_name) LIKE ?', [$term]));
            })
            ->orderByDesc('id')
            ->paginate(20);
    }

    #[Computed]
    public function familyAttributes(): Collection
    {
        return $this->product->family
            ? $this->product->family
                ->attributes()
                ->orderBy('family_attributes.sort_order')
                ->orderBy('attributes.name')
                ->get()
            : collect();
    }

    /**
     * Total de unidades en stock sumando todas las variantes y todos los almacenes
     * (movimientos confirmados).
     */
    #[Computed]
    public function totalStock(): float
    {
        $skuIds = $this->product->skus()->pluck('id');

        if ($skuIds->isEmpty()) {
            return 0.0;
        }

        $total = DB::table('movement_lines as ml')
            ->join('movements as m', 'ml.movement_id', '=', 'm.id')
            ->whereIn('ml.sku_id', $skuIds)
            ->where('m.status', 'confirmed')
            ->selectRaw("SUM(CASE WHEN ml.direction = 'in' THEN ml.quantity ELSE -ml.quantity END) as total")
            ->value('total');

        return (float) ($total ?? 0);
    }

    /**
     * Matriz SKU × Almacén con cantidades. Devuelve almacenes activos como columnas
     * y un array indexado por sku_id con `code` => qty.
     *
     * @return array{warehouses: EloquentCollection<int, Warehouse>, rows: array<int, array{sku: Sku, qty: array<string, float>, total: float}>}
     */
    #[Computed]
    public function stockMatrix(): array
    {
        /** @var EloquentCollection<int, Warehouse> $warehouses */
        $warehouses = Warehouse::query()->active()->orderBy('code')->get();

        /** @var EloquentCollection<int, Sku> $skus */
        $skus = $this->product->skus()
            ->where('status', '!=', 'discontinued')
            ->orderBy('internal_code')
            ->get();

        if ($skus->isEmpty() || $warehouses->isEmpty()) {
            return ['warehouses' => $warehouses, 'rows' => []];
        }

        $totals = DB::table('movement_lines as ml')
            ->join('movements as m', 'ml.movement_id', '=', 'm.id')
            ->whereIn('ml.sku_id', $skus->pluck('id'))
            ->whereIn('ml.warehouse_id', $warehouses->pluck('id'))
            ->where('m.status', 'confirmed')
            ->groupBy('ml.sku_id', 'ml.warehouse_id')
            ->select('ml.sku_id', 'ml.warehouse_id')
            ->selectRaw("SUM(CASE WHEN ml.direction = 'in' THEN ml.quantity ELSE -ml.quantity END) as qty")
            ->get();

        $rows = [];

        foreach ($skus as $sku) {
            $perWarehouse = [];
            $rowTotal = 0.0;

            foreach ($warehouses as $wh) {
                $row = $totals->first(fn ($t) => (int) $t->sku_id === $sku->id && (int) $t->warehouse_id === $wh->id);
                $qty = $row ? (float) $row->qty : 0.0;
                $perWarehouse[$wh->code] = $qty;
                $rowTotal += $qty;
            }

            $rows[] = ['sku' => $sku, 'qty' => $perWarehouse, 'total' => $rowTotal];
        }

        return ['warehouses' => $warehouses, 'rows' => $rows];
    }

    /**
     * Últimas 50 líneas de movimiento que tocaron cualquier SKU del producto,
     * con su movimiento, almacén y creador.
     */
    #[Computed]
    public function recentMovements(): EloquentCollection
    {
        $skuIds = $this->product->skus()->pluck('id');

        if ($skuIds->isEmpty()) {
            return new EloquentCollection;
        }

        return MovementLine::query()
            ->whereIn('sku_id', $skuIds)
            ->with([
                'sku:id,internal_code,variant_name',
                'warehouse:id,code,name',
                'movement:id,number,type,status,occurred_at,created_by',
                'movement.creator:id,name',
            ])
            ->whereHas('movement', fn ($q) => $q->whereIn('status', ['confirmed', 'voided']))
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function lastMovementAt(): ?Carbon
    {
        $skuIds = $this->product->skus()->pluck('id');

        if ($skuIds->isEmpty()) {
            return null;
        }

        $value = DB::table('movement_lines as ml')
            ->join('movements as m', 'ml.movement_id', '=', 'm.id')
            ->whereIn('ml.sku_id', $skuIds)
            ->where('m.status', 'confirmed')
            ->max('m.occurred_at');

        return $value ? Carbon::parse($value) : null;
    }

    public function openCreate(): void
    {
        $this->authorize('update', $this->product);
        $this->resetForm();
        $this->internalCode = $this->generateInternalCode();
        Flux::modal('sku-form')->show();
    }

    public function openEdit(int $id): void
    {
        $this->authorize('update', $this->product);
        $sku = Sku::with('attributeValues')->findOrFail($id);

        $this->editingId = $sku->id;
        $this->internalCode = $sku->internal_code;
        $this->variantName = $sku->variant_name ?? '';
        $this->salePrice = $sku->sale_price !== null ? (float) $sku->sale_price : null;
        $this->purchasePrice = $sku->purchase_price !== null ? (float) $sku->purchase_price : null;
        $this->status = $sku->status;

        $this->attributeValues = $sku->attributeValues
            ->mapWithKeys(fn ($av) => [$av->attribute_id => $av->value])
            ->all();

        Flux::modal('sku-form')->show();
    }

    public function save(): void
    {
        $this->authorize('update', $this->product);

        $rules = [
            'internalCode' => ['required', 'string', 'max:20', Rule::unique('skus', 'internal_code')->ignore($this->editingId)],
            'variantName' => ['nullable', 'string', 'max:150'],
            'salePrice' => ['nullable', 'numeric', 'min:0'],
            'purchasePrice' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
        ];

        $messages = [];

        foreach ($this->familyAttributes as $attr) {
            $field = "attributeValues.{$attr->id}";
            $required = (bool) $attr->pivot->is_required;

            $rule = match ($attr->type) {
                'number' => ['numeric'],
                'boolean' => ['in:0,1,true,false'],
                'list' => [Rule::in($attr->options ?? [])],
                default => ['string', 'max:255'],
            };

            if ($required) {
                array_unshift($rule, 'required');
            } else {
                array_unshift($rule, 'nullable');
            }

            $rules[$field] = $rule;
            $messages["{$field}.required"] = "El atributo «{$attr->name}» es obligatorio.";
        }

        $validated = $this->validate($rules, $messages);

        $payload = [
            'product_id' => $this->product->id,
            'internal_code' => $validated['internalCode'],
            'variant_name' => $validated['variantName'] ?? null,
            'sale_price' => $validated['salePrice'] ?? null,
            'purchase_price' => $validated['purchasePrice'] ?? null,
            'status' => $validated['status'],
            'fingerprint' => $this->buildFingerprint(),
        ];

        if ($this->editingId) {
            $sku = Sku::findOrFail($this->editingId);
            $sku->update($payload);
            $message = 'SKU actualizado.';
        } else {
            $sku = Sku::create($payload);
            $message = 'SKU creado.';
        }

        $this->persistAttributeValues($sku);

        $this->resetForm();
        Flux::modal('sku-form')->close();
        Flux::toast(variant: 'success', text: $message);
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('delete', $this->product);
        $this->deletingId = $id;
        Flux::modal('sku-delete')->show();
    }

    #[Computed]
    public function deletingSku(): ?Sku
    {
        return $this->deletingId
            ? Sku::withCount('movementLines')->find($this->deletingId)
            : null;
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->product);

        $sku = Sku::withCount('movementLines')->findOrFail($this->deletingId);

        if ($sku->movement_lines_count > 0) {
            Flux::toast(
                variant: 'danger',
                text: "El SKU tiene {$sku->movement_lines_count} movimiento(s). Desactívalo en su lugar.",
            );

            return;
        }

        $code = $sku->internal_code;
        $sku->forceDelete();

        $this->deletingId = null;
        Flux::modal('sku-delete')->close();
        Flux::toast(variant: 'success', text: "SKU «{$code}» eliminado.");
    }

    public function openDamageReport(int $skuId): void
    {
        $this->authorize('update', $this->product);

        $sku = Sku::findOrFail($skuId);
        abort_unless($sku->product_id === $this->product->id, 403);

        $this->resetDamageForm();
        $this->damageSkuId = $skuId;
        Flux::modal('report-damage')->show();
    }

    /**
     * Almacenes operativos (excluye taller / merma / tránsito) en los que la variante
     * tiene stock disponible, junto con la cantidad. Estructura:
     * `[ ['warehouse' => Warehouse, 'qty' => float], ... ]`.
     *
     * @return array<int, array{warehouse: Warehouse, qty: float}>
     */
    #[Computed]
    public function damageEligibleWarehouses(): array
    {
        if (! $this->damageSkuId) {
            return [];
        }

        $sku = Sku::find($this->damageSkuId);

        if (! $sku) {
            return [];
        }

        $warehouses = Warehouse::query()
            ->active()
            ->whereNotIn('type', ['workshop', 'scrap', 'transit'])
            ->orderBy('code')
            ->get();

        $result = [];

        foreach ($warehouses as $wh) {
            $qty = $sku->stockAt($wh->id);

            if ($qty > 0) {
                $result[] = ['warehouse' => $wh, 'qty' => $qty];
            }
        }

        return $result;
    }

    #[Computed]
    public function damageMaxQuantity(): float
    {
        if (! $this->damageSkuId || ! $this->damageOriginWarehouseId) {
            return 0.0;
        }

        $sku = Sku::find($this->damageSkuId);

        return $sku ? $sku->stockAt($this->damageOriginWarehouseId) : 0.0;
    }

    public function reportDamage(): void
    {
        $this->authorize('update', $this->product);

        $validated = $this->validate([
            'damageSkuId' => ['required', 'integer', 'exists:skus,id'],
            'damageOriginWarehouseId' => ['required', 'integer', 'exists:warehouses,id'],
            'damageQuantity' => ['required', 'numeric', 'gt:0'],
            'damageNotes' => ['nullable', 'string', 'max:255'],
        ]);

        $sku = Sku::findOrFail($validated['damageSkuId']);
        abort_unless($sku->product_id === $this->product->id, 403);

        $available = $sku->stockAt($validated['damageOriginWarehouseId']);

        if ($validated['damageQuantity'] > $available) {
            $this->addError('damageQuantity', "Solo hay {$available} disponibles en ese almacén.");

            return;
        }

        $workshop = Warehouse::query()->where('type', 'workshop')->where('active', true)->first();

        if (! $workshop) {
            Flux::toast(variant: 'danger', text: 'No hay almacén de taller (workshop) configurado.');

            return;
        }

        if ($workshop->id === (int) $validated['damageOriginWarehouseId']) {
            $this->addError('damageOriginWarehouseId', 'El stock ya está en el taller.');

            return;
        }

        DB::transaction(function () use ($validated, $sku, $workshop) {
            $reasonNote = $validated['damageNotes'] ?? null;
            $movement = Movement::create([
                'number' => $this->generateMovementNumber(),
                'type' => 'transfer',
                'occurred_at' => now(),
                'reason' => $reasonNote ? 'Reportado dañado: '.$reasonNote : 'Reportado dañado',
                'origin_warehouse_id' => $validated['damageOriginWarehouseId'],
                'destination_warehouse_id' => $workshop->id,
                'status' => 'confirmed',
                'created_by' => Auth::id(),
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ]);

            MovementLine::create([
                'movement_id' => $movement->id,
                'sku_id' => $sku->id,
                'warehouse_id' => $validated['damageOriginWarehouseId'],
                'direction' => 'out',
                'quantity' => $validated['damageQuantity'],
                'notes' => $reasonNote,
            ]);

            MovementLine::create([
                'movement_id' => $movement->id,
                'sku_id' => $sku->id,
                'warehouse_id' => $workshop->id,
                'direction' => 'in',
                'quantity' => $validated['damageQuantity'],
                'notes' => $reasonNote,
            ]);
        });

        $this->resetDamageForm();
        Flux::modal('report-damage')->close();
        Flux::toast(variant: 'success', text: 'Reporte registrado. El stock se movió al taller.');

        unset($this->stockMatrix, $this->totalStock, $this->lastMovementAt, $this->recentMovements);
    }

    private function resetDamageForm(): void
    {
        $this->reset(['damageSkuId', 'damageOriginWarehouseId', 'damageQuantity', 'damageNotes']);
        $this->resetValidation();
    }

    private function generateMovementNumber(): string
    {
        $lastId = Movement::max('id') ?? 0;

        return 'MOV-'.str_pad((string) ($lastId + 1), 6, '0', STR_PAD_LEFT);
    }

    private function generateInternalCode(): string
    {
        $lastId = Sku::withTrashed()->max('id') ?? 0;

        return 'SKU-'.str_pad((string) ($lastId + 1), 6, '0', STR_PAD_LEFT);
    }

    private function buildFingerprint(): string
    {
        $keyValues = collect($this->familyAttributes)
            ->filter(fn ($attr) => (bool) $attr->pivot->is_key)
            ->sortBy('code')
            ->map(fn ($attr) => $attr->code.'='.($this->attributeValues[$attr->id] ?? ''))
            ->implode('|');

        return hash('sha256', $this->product->id.'|'.$keyValues);
    }

    private function persistAttributeValues(Sku $sku): void
    {
        $familyAttrIds = $this->familyAttributes->pluck('id');

        foreach ($familyAttrIds as $attrId) {
            $value = $this->attributeValues[$attrId] ?? null;

            if ($value === null || $value === '') {
                SkuAttribute::where('sku_id', $sku->id)->where('attribute_id', $attrId)->delete();

                continue;
            }

            SkuAttribute::updateOrCreate(
                ['sku_id' => $sku->id, 'attribute_id' => $attrId],
                ['value' => (string) $value],
            );
        }
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'internalCode', 'variantName', 'salePrice', 'purchasePrice', 'attributeValues']);
        $this->status = 'draft';
        $this->resetValidation();
    }
}; ?>

<section class="w-full p-6">
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2 text-sm text-zinc-500">
                <flux:link :href="route('products.index')" wire:navigate>Productos</flux:link>
                <span>/</span>
                <span>{{ $product->name }}</span>
            </div>

            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="xl">{{ $product->name }}</flux:heading>
                    <flux:text class="mt-1">
                        <code class="text-xs font-mono">{{ $product->internal_code }}</code>
                        · {{ $product->family?->name }}
                        @if ($product->subfamily)
                            / {{ $product->subfamily->name }}
                        @endif
                        @if ($product->brand)
                            · {{ $product->brand }}
                        @endif
                    </flux:text>
                </div>
            </div>
        </div>

        @php
            $canSeeCosts = auth()->user()->can('viewCosts', App\Models\Product::class);
        @endphp

        {{-- Tabs --}}
        <div class="border-b border-zinc-200 dark:border-zinc-700">
            <nav class="-mb-px flex flex-wrap gap-1" data-test="product-tabs">
                @foreach (static::TABS as $key => $label)
                    <button
                        type="button"
                        wire:click="setTab('{{ $key }}')"
                        data-test="tab-{{ $key }}"
                        @class([
                            'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition',
                            'border-zinc-900 text-zinc-900 dark:border-zinc-100 dark:text-zinc-100' => $tab === $key,
                            'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:hover:text-zinc-300' => $tab !== $key,
                        ])
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- ====== TAB: Resumen ====== --}}
        @if ($tab === 'overview')
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3" data-test="tab-content-overview">
                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-start justify-between">
                        <div class="text-sm text-zinc-500">Stock total</div>
                        <flux:icon.cube class="size-5 text-zinc-400" />
                    </div>
                    <div class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-zinc-100" data-test="overview-total-stock">
                        {{ rtrim(rtrim(number_format($this->totalStock, 2), '0'), '.') ?: '0' }}
                    </div>
                    <div class="mt-1 text-xs text-zinc-500">
                        Sumando todas las variantes y almacenes.
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-start justify-between">
                        <div class="text-sm text-zinc-500">Variantes activas</div>
                        <flux:icon.squares-2x2 class="size-5 text-zinc-400" />
                    </div>
                    <div class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ $product->skus()->where('status', '!=', 'discontinued')->count() }}
                    </div>
                    <div class="mt-1 text-xs text-zinc-500">
                        de {{ $product->skus()->count() }} totales.
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-start justify-between">
                        <div class="text-sm text-zinc-500">Último movimiento</div>
                        <flux:icon.clock class="size-5 text-zinc-400" />
                    </div>
                    <div class="mt-2 text-xl font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ $this->lastMovementAt?->diffForHumans() ?? '—' }}
                    </div>
                    <div class="mt-1 text-xs text-zinc-500">
                        {{ $this->lastMovementAt?->format('d/m/Y H:i') ?? 'Sin movimientos confirmados aún.' }}
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="md:col-span-2 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="sm">Datos generales</flux:heading>
                    <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-zinc-500">Familia</dt>
                            <dd>{{ $product->family?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500">Subfamilia</dt>
                            <dd>{{ $product->subfamily?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500">Marca</dt>
                            <dd>{{ $product->brand ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500">Unidad de medida</dt>
                            <dd>{{ $product->unit_of_measure }}</dd>
                        </div>
                        @if ($product->description)
                            <div class="col-span-2">
                                <dt class="text-zinc-500">Descripción</dt>
                                <dd>{{ $product->description }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-5 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:icon.photo class="mx-auto size-8 text-zinc-400" />
                    <div class="mt-2">Galería de imágenes</div>
                    <div class="mt-1 text-xs">Próximamente.</div>
                </div>
            </div>
        @endif

        {{-- ====== TAB: Variantes (SKUs) ====== --}}
        @if ($tab === 'variants')
        <div data-test="tab-content-variants" class="flex flex-col gap-4">
        <div class="flex items-center justify-end">
            @can('update', $product)
                <flux:button variant="primary" icon="plus" wire:click="openCreate" data-test="new-sku">
                    Nuevo SKU
                </flux:button>
            @endcan
        </div>

        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="Buscar por código o nombre de variante..."
        />

        <flux:table :paginate="$this->skus">
            <flux:table.columns>
                <flux:table.column>Código</flux:table.column>
                <flux:table.column>Variante</flux:table.column>
                <flux:table.column>Atributos</flux:table.column>
                <flux:table.column>P. venta</flux:table.column>
                <flux:table.column>P. compra</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column>Acciones</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->skus as $sku)
                    <flux:table.row :key="$sku->id">
                        <flux:table.cell variant="strong">
                            <code class="text-xs font-mono">{{ $sku->internal_code }}</code>
                        </flux:table.cell>
                        <flux:table.cell>{{ $sku->variant_name ?: '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($sku->attributeValues as $av)
                                    <flux:badge size="sm" inset="top bottom">
                                        {{ $av->attribute->name }}: {{ $av->value }}{{ $av->attribute->unit ? ' '.$av->attribute->unit : '' }}
                                    </flux:badge>
                                @endforeach
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500">
                            {{ $sku->sale_price !== null ? 'S/ '.number_format((float) $sku->sale_price, 2) : '—' }}
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500">
                            {{ $canSeeCosts ? ($sku->purchase_price !== null ? 'S/ '.number_format((float) $sku->purchase_price, 2) : '—') : '·' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($sku->status === 'active')
                                <flux:badge color="green" size="sm" inset="top bottom">Activo</flux:badge>
                            @elseif ($sku->status === 'draft')
                                <flux:badge color="amber" size="sm" inset="top bottom">Borrador</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm" inset="top bottom">Descontinuado</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-1">
                                <flux:tooltip content="Imprimir etiqueta">
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="printer"
                                        :href="route('stickers.print', ['items' => $sku->id.'x1'])"
                                        target="_blank"
                                        inset="top bottom"
                                    />
                                </flux:tooltip>
                                @can('update', $product)
                                    <flux:tooltip content="Reportar dañado">
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            icon="exclamation-triangle"
                                            wire:click="openDamageReport({{ $sku->id }})"
                                            inset="top bottom"
                                            class="text-amber-600 hover:text-amber-700"
                                            data-test="report-damage-{{ $sku->id }}"
                                        />
                                    </flux:tooltip>
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="pencil-square"
                                        wire:click="openEdit({{ $sku->id }})"
                                        inset="top bottom"
                                    />
                                @endcan
                                @can('delete', $product)
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="trash"
                                        wire:click="confirmDelete({{ $sku->id }})"
                                        inset="top bottom"
                                        class="text-red-600 hover:text-red-700"
                                    />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center text-zinc-500 py-8">
                            Aún no hay SKUs para este producto. Crea el primero.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        </div>
        @endif

        {{-- ====== TAB: Stock por sucursal ====== --}}
        @if ($tab === 'stock')
            <div data-test="tab-content-stock" class="space-y-4">
                @php
                    $matrix = $this->stockMatrix;
                    $warehouses = $matrix['warehouses'];
                    $rows = $matrix['rows'];
                @endphp

                @if ($warehouses->isEmpty())
                    <div class="rounded border border-dashed border-zinc-300 dark:border-zinc-700 p-8 text-center text-sm text-zinc-500">
                        No hay almacenes activos.
                    </div>
                @elseif (empty($rows))
                    <div class="rounded border border-dashed border-zinc-300 dark:border-zinc-700 p-8 text-center text-sm text-zinc-500">
                        Este producto aún no tiene variantes activas.
                    </div>
                @else
                    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <table class="w-full text-sm">
                            <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-900">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold">SKU</th>
                                    <th class="px-3 py-2 text-left font-semibold">Variante</th>
                                    @foreach ($warehouses as $wh)
                                        <th class="px-3 py-2 text-right font-semibold">
                                            <flux:tooltip :content="$wh->name">
                                                <span>{{ $wh->code }}</span>
                                            </flux:tooltip>
                                        </th>
                                    @endforeach
                                    <th class="px-3 py-2 text-right font-semibold bg-zinc-100 dark:bg-zinc-800">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach ($rows as $row)
                                    <tr wire:key="stock-row-{{ $row['sku']->id }}">
                                        <td class="px-3 py-2 font-mono text-xs">{{ $row['sku']->internal_code }}</td>
                                        <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">
                                            {{ $row['sku']->variant_name ?: '—' }}
                                        </td>
                                        @foreach ($warehouses as $wh)
                                            @php
                                                $qty = $row['qty'][$wh->code] ?? 0;
                                                $color = match (true) {
                                                    $qty > 0 => 'text-green-700 dark:text-green-400',
                                                    $qty < 0 => 'text-red-600 dark:text-red-400 font-semibold',
                                                    default => 'text-zinc-400',
                                                };
                                            @endphp
                                            <td class="px-3 py-2 text-right {{ $color }}">
                                                {{ rtrim(rtrim(number_format($qty, 2), '0'), '.') ?: '0' }}
                                            </td>
                                        @endforeach
                                        <td class="px-3 py-2 text-right font-semibold bg-zinc-50 dark:bg-zinc-900">
                                            {{ rtrim(rtrim(number_format($row['total'], 2), '0'), '.') ?: '0' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <flux:text class="text-xs text-zinc-500">
                        Solo se cuentan movimientos confirmados. Las celdas en rojo indican stock negativo (revisar movimientos).
                    </flux:text>
                @endif
            </div>
        @endif

        {{-- ====== TAB: Movimientos ====== --}}
        @if ($tab === 'movements')
            <div data-test="tab-content-movements" class="space-y-4">
                @if ($this->recentMovements->isEmpty())
                    <div class="rounded border border-dashed border-zinc-300 dark:border-zinc-700 p-8 text-center text-sm text-zinc-500">
                        Aún no hay movimientos registrados para este producto.
                    </div>
                @else
                    <flux:text class="text-xs text-zinc-500">
                        Últimas {{ $this->recentMovements->count() }} líneas que tocaron variantes de este producto.
                    </flux:text>

                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Fecha</flux:table.column>
                            <flux:table.column>N°</flux:table.column>
                            <flux:table.column>Tipo</flux:table.column>
                            <flux:table.column>SKU</flux:table.column>
                            <flux:table.column>Almacén</flux:table.column>
                            <flux:table.column>Dirección</flux:table.column>
                            <flux:table.column align="end">Cantidad</flux:table.column>
                            <flux:table.column>Por</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->recentMovements as $line)
                                <flux:table.row :key="$line->id">
                                    <flux:table.cell class="text-xs text-zinc-500 whitespace-nowrap">
                                        {{ $line->movement?->occurred_at?->format('d/m/Y H:i') ?? '—' }}
                                    </flux:table.cell>
                                    <flux:table.cell variant="strong">
                                        <flux:link :href="route('movements.show', $line->movement_id)" wire:navigate>
                                            <code class="text-xs font-mono">{{ $line->movement?->number }}</code>
                                        </flux:link>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge size="sm" inset="top bottom">
                                            {{ static::MOVEMENT_TYPE_LABELS[$line->movement?->type] ?? $line->movement?->type }}
                                        </flux:badge>
                                        @if ($line->movement?->status === 'voided')
                                            <flux:badge color="red" size="sm" inset="top bottom">Anulado</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell class="text-xs">
                                        <code class="font-mono">{{ $line->sku?->internal_code }}</code>
                                        @if ($line->sku?->variant_name)
                                            <span class="text-zinc-500"> · {{ $line->sku->variant_name }}</span>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell class="text-xs text-zinc-500">
                                        {{ $line->warehouse?->code ?? '—' }}
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if ($line->direction === 'in')
                                            <flux:badge color="green" size="sm" inset="top bottom">Entrada</flux:badge>
                                        @else
                                            <flux:badge color="red" size="sm" inset="top bottom">Salida</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell align="end" variant="strong">
                                        {{ rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.') ?: '0' }}
                                    </flux:table.cell>
                                    <flux:table.cell class="text-xs text-zinc-500">
                                        {{ $line->movement?->creator?->name ?? '—' }}
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </div>
        @endif
    </div>

    <flux:modal name="sku-form" class="md:w-[40rem]">
        <form wire:submit="save" class="space-y-5">
            <div>
                <flux:heading size="lg">
                    {{ $editingId ? 'Editar SKU' : 'Nuevo SKU' }}
                </flux:heading>
                <flux:text class="mt-1">
                    Producto: <strong>{{ $product->name }}</strong>
                </flux:text>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input
                    wire:model="internalCode"
                    label="Código interno"
                    required
                    maxlength="20"
                />

                <flux:select wire:model="status" label="Estado">
                    @foreach (static::STATUSES as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:input
                wire:model="variantName"
                label="Nombre de variante"
                placeholder="Negro / Mesh / 120cm"
                maxlength="150"
            />

            <div class="grid grid-cols-2 gap-4">
                <flux:input
                    wire:model="salePrice"
                    type="number"
                    step="0.01"
                    min="0"
                    label="Precio de venta (S/)"
                />

                <flux:input
                    wire:model="purchasePrice"
                    type="number"
                    step="0.01"
                    min="0"
                    label="Precio de compra (S/)"
                />
            </div>

            @if ($this->familyAttributes->isNotEmpty())
                <div class="space-y-3 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                    <flux:heading size="sm">Atributos de la familia</flux:heading>

                    @foreach ($this->familyAttributes as $attr)
                        @php
                            $field = "attributeValues.{$attr->id}";
                            $isRequired = (bool) $attr->pivot->is_required;
                            $isKey = (bool) $attr->pivot->is_key;
                            $label = $attr->name.($attr->unit ? ' ('.$attr->unit.')' : '');
                        @endphp

                        <div wire:key="attr-{{ $attr->id }}">
                            @if ($attr->type === 'list')
                                <flux:select wire:model="{{ $field }}" :label="$label" :required="$isRequired">
                                    <flux:select.option value="">—</flux:select.option>
                                    @foreach ($attr->options ?? [] as $opt)
                                        <flux:select.option :value="$opt">{{ $opt }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            @elseif ($attr->type === 'boolean')
                                <flux:switch wire:model="{{ $field }}" :label="$label" value="1" />
                            @elseif ($attr->type === 'number')
                                <flux:input
                                    wire:model="{{ $field }}"
                                    type="number"
                                    step="any"
                                    :label="$label"
                                    :required="$isRequired"
                                />
                            @else
                                <flux:input
                                    wire:model="{{ $field }}"
                                    :label="$label"
                                    :required="$isRequired"
                                    maxlength="255"
                                />
                            @endif
                            @if ($isKey)
                                <flux:text class="text-xs text-zinc-500 mt-0.5">
                                    Atributo clave (forma parte del identificador anti-duplicado).
                                </flux:text>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="save-sku">
                    Guardar
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="report-damage" class="md:w-[32rem]">
        <form wire:submit="reportDamage" class="space-y-5">
            <div>
                <flux:heading size="lg">Reportar dañado</flux:heading>
                <flux:text class="mt-1">
                    Mueve unidades al taller (<code class="text-xs font-mono">TALLER</code>) para que puedan repararse o descartarse.
                </flux:text>
            </div>

            @if (empty($this->damageEligibleWarehouses))
                <div class="rounded-lg bg-amber-50 dark:bg-amber-950/30 p-4 text-sm text-amber-700 dark:text-amber-300">
                    Esta variante no tiene stock disponible en ningún almacén operativo.
                </div>
            @else
                <flux:select wire:model.live="damageOriginWarehouseId" label="Almacén de origen" required>
                    <flux:select.option value="">Selecciona el almacén…</flux:select.option>
                    @foreach ($this->damageEligibleWarehouses as $row)
                        <flux:select.option :value="$row['warehouse']->id">
                            {{ $row['warehouse']->code }} — {{ $row['warehouse']->name }}
                            ({{ rtrim(rtrim(number_format($row['qty'], 2), '0'), '.') }} disponibles)
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input
                    wire:model="damageQuantity"
                    type="number"
                    step="0.01"
                    min="0.01"
                    :max="$this->damageMaxQuantity ?: null"
                    label="Cantidad a reportar"
                    required
                />

                <flux:textarea
                    wire:model="damageNotes"
                    label="Motivo / observación"
                    rows="3"
                    placeholder="Pata rota, tela manchada, etc."
                    maxlength="255"
                />
            @endif

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button
                    type="submit"
                    variant="danger"
                    :disabled="empty($this->damageEligibleWarehouses)"
                    data-test="confirm-damage-report"
                >
                    Reportar
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="sku-delete" class="md:w-[28rem]">
        @if ($this->deletingSku)
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">Eliminar SKU</flux:heading>
                    <flux:text class="mt-2">
                        ¿Seguro que quieres eliminar <code>{{ $this->deletingSku->internal_code }}</code>?
                    </flux:text>
                </div>

                @if ($this->deletingSku->movement_lines_count > 0)
                    <div class="rounded-lg bg-red-50 dark:bg-red-950/30 p-4 text-sm text-red-700 dark:text-red-300">
                        Tiene <strong>{{ $this->deletingSku->movement_lines_count }}</strong> movimiento(s) de stock.
                        No se puede eliminar.
                    </div>
                @else
                    <flux:text class="text-zinc-500">El SKU se archiva (soft delete).</flux:text>
                @endif

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancelar</flux:button>
                    </flux:modal.close>
                    <flux:button
                        variant="danger"
                        wire:click="delete"
                        :disabled="$this->deletingSku->movement_lines_count > 0"
                        data-test="confirm-delete-sku"
                    >
                        Eliminar
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</section>
