<?php

use App\Models\Attribute;
use App\Models\Family;
use App\Models\Product;
use App\Models\Sku;
use App\Models\Subfamily;
use App\Models\User;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Productos')] class extends Component {
    use WithPagination;

    public const UNITS = [
        'unit' => 'Unidad',
        'meter' => 'Metro',
        'kg' => 'Kilogramo',
        'set' => 'Juego',
        'pair' => 'Par',
        'box' => 'Caja',
    ];

    public const STATUSES = [
        'draft' => 'Borrador',
        'active' => 'Activo',
        'discontinued' => 'Descontinuado',
    ];

    public const CREATED_PERIODS = [
        '' => 'Cualquier fecha',
        '1d' => 'Hoy',
        '7d' => 'Últimos 7 días',
        '30d' => 'Últimos 30 días',
    ];

    // Filtros
    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'familia')]
    public string $familyFilter = '';

    #[Url(as: 'subfamilia')]
    public string $subfamilyFilter = '';

    #[Url(as: 'estado')]
    public string $statusFilter = '';

    #[Url(as: 'creador')]
    public string $creatorFilter = '';

    #[Url(as: 'periodo')]
    public string $periodFilter = '';

    // Edición (form simple sin wizard)
    public ?int $editingId = null;

    public string $editInternalCode = '';

    public string $editName = '';

    public string $editDescription = '';

    public ?int $editFamilyId = null;

    public ?int $editSubfamilyId = null;

    public string $editBrand = '';

    public string $editUnitOfMeasure = 'unit';

    public bool $editIsTemporary = false;

    public ?string $editTemporaryEndDate = null;

    public string $editStatus = 'draft';

    /**
     * SKUs cargados para edición inline en el modal de editar.
     * Los SKUs existentes tienen `id` numérico; los nuevos tienen `id = null`.
     *
     * @var array<int, array{id: ?int, variant_name: ?string, sale_price: ?string, purchase_price: ?string, status: string, movement_lines_count: int}>
     */
    public array $editSkus = [];

    // Eliminación
    public ?int $deletingId = null;

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

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCreatorFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPeriodFilter(): void
    {
        $this->resetPage();
    }

    public function updatedEditFamilyId(): void
    {
        $this->editSubfamilyId = null;
    }

    #[Computed]
    public function products()
    {
        return Product::query()
            ->with(['family:id,name,code', 'subfamily:id,name', 'creator:id,name'])
            ->withCount('skus')
            ->when($this->search, function ($query) {
                $term = '%'.mb_strtolower($this->search).'%';
                $query->where(fn ($q) => $q
                    ->whereRaw('LOWER(internal_code) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(brand) LIKE ?', [$term]));
            })
            ->when($this->familyFilter, fn ($q) => $q->where('family_id', $this->familyFilter))
            ->when($this->subfamilyFilter, fn ($q) => $q->where('subfamily_id', $this->subfamilyFilter))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->creatorFilter, fn ($q) => $q->where('created_by', $this->creatorFilter))
            ->when($this->periodFilter === '1d', fn ($q) => $q->where('created_at', '>=', now()->startOfDay()))
            ->when($this->periodFilter === '7d', fn ($q) => $q->where('created_at', '>=', now()->subDays(7)))
            ->when($this->periodFilter === '30d', fn ($q) => $q->where('created_at', '>=', now()->subDays(30)))
            ->orderByDesc('id')
            ->paginate(20);
    }

    #[Computed]
    public function families()
    {
        return Family::query()->active()->orderBy('name')->get();
    }

    #[Computed]
    public function subfamiliesForFilter()
    {
        return $this->familyFilter
            ? Subfamily::query()->where('family_id', $this->familyFilter)->active()->orderBy('name')->get()
            : collect();
    }

    #[Computed]
    public function editSubfamilies()
    {
        return $this->editFamilyId
            ? Subfamily::query()->where('family_id', $this->editFamilyId)->active()->orderBy('name')->get()
            : collect();
    }

    #[Computed]
    public function creators()
    {
        return User::query()
            ->whereIn('id', Product::query()->whereNotNull('created_by')->distinct()->pluck('created_by'))
            ->orderBy('name')
            ->get();
    }

    public function openEdit(int $id): void
    {
        $product = Product::findOrFail($id);

        $this->authorize('update', $product);

        $this->editingId = $product->id;
        $this->editInternalCode = $product->internal_code;
        $this->editName = $product->name;
        $this->editDescription = $product->description ?? '';
        $this->editFamilyId = $product->family_id;
        $this->editSubfamilyId = $product->subfamily_id;
        $this->editBrand = $product->brand ?? '';
        $this->editUnitOfMeasure = $product->unit_of_measure;
        $this->editIsTemporary = $product->is_temporary;
        $this->editTemporaryEndDate = $product->temporary_end_date?->format('Y-m-d');
        $this->editStatus = $product->status;

        $this->editSkus = $product->skus()
            ->withCount('movementLines')
            ->orderBy('internal_code')
            ->get()
            ->map(fn (Sku $sku) => [
                'id' => $sku->id,
                'internal_code' => $sku->internal_code,
                'variant_name' => $sku->variant_name,
                'sale_price' => $sku->sale_price !== null ? (string) $sku->sale_price : null,
                'purchase_price' => $sku->purchase_price !== null ? (string) $sku->purchase_price : null,
                'status' => $sku->status,
                'movement_lines_count' => (int) $sku->movement_lines_count,
            ])
            ->all();

        Flux::modal('product-edit')->show();
    }

    public function addEditVariant(): void
    {
        $this->editSkus[] = [
            'id' => null,
            'internal_code' => '(nuevo)',
            'variant_name' => null,
            'sale_price' => null,
            'purchase_price' => null,
            'status' => 'active',
            'movement_lines_count' => 0,
        ];
    }

    public function removeEditSku(int $index): void
    {
        if (! isset($this->editSkus[$index])) {
            return;
        }

        $row = $this->editSkus[$index];

        // Fila nueva (no persistida): solo removerla del array.
        if ($row['id'] === null) {
            unset($this->editSkus[$index]);
            $this->editSkus = array_values($this->editSkus);

            return;
        }

        // Fila existente: bloqueamos si tiene movimientos; si no, hard delete.
        $sku = Sku::withCount('movementLines')->findOrFail($row['id']);

        if ($sku->movement_lines_count > 0) {
            Flux::toast(
                variant: 'danger',
                text: "No se puede eliminar {$sku->internal_code}: tiene {$sku->movement_lines_count} movimiento(s). Márcalo como descontinuado.",
            );

            return;
        }

        $code = $sku->internal_code;
        $sku->forceDelete();

        unset($this->editSkus[$index]);
        $this->editSkus = array_values($this->editSkus);

        Flux::toast(variant: 'success', text: "SKU {$code} eliminado.");
    }

    public function saveEdit(): void
    {
        $this->authorize('update', Product::findOrFail($this->editingId));

        $productRules = [
            'editInternalCode' => ['required', 'string', 'max:20', Rule::unique('products', 'internal_code')->ignore($this->editingId)],
            'editName' => ['required', 'string', 'max:200'],
            'editDescription' => ['nullable', 'string'],
            'editFamilyId' => ['required', 'exists:families,id'],
            'editSubfamilyId' => ['nullable', 'exists:subfamilies,id'],
            'editBrand' => ['nullable', 'string', 'max:100'],
            'editUnitOfMeasure' => ['required', Rule::in(array_keys(self::UNITS))],
            'editIsTemporary' => ['boolean'],
            'editTemporaryEndDate' => [Rule::requiredIf(fn () => $this->editIsTemporary), 'nullable', 'date'],
            'editStatus' => ['required', Rule::in(array_keys(self::STATUSES))],
        ];

        $skuRules = [];
        foreach ($this->editSkus as $i => $_) {
            $skuRules["editSkus.{$i}.variant_name"] = ['nullable', 'string', 'max:150'];
            $skuRules["editSkus.{$i}.sale_price"] = ['nullable', 'numeric', 'min:0'];
            $skuRules["editSkus.{$i}.purchase_price"] = ['nullable', 'numeric', 'min:0'];
            $skuRules["editSkus.{$i}.status"] = ['required', Rule::in(array_keys(self::STATUSES))];
        }

        $validated = $this->validate(array_merge($productRules, $skuRules));

        $product = Product::findOrFail($this->editingId);

        $product->update([
            'internal_code' => $validated['editInternalCode'],
            'name' => $validated['editName'],
            'description' => $validated['editDescription'] ?? null,
            'family_id' => $validated['editFamilyId'],
            'subfamily_id' => $validated['editSubfamilyId'] ?? null,
            'brand' => $validated['editBrand'] ?? null,
            'unit_of_measure' => $validated['editUnitOfMeasure'],
            'is_temporary' => $validated['editIsTemporary'],
            'temporary_end_date' => $validated['editIsTemporary'] ? $validated['editTemporaryEndDate'] : null,
            'status' => $validated['editStatus'],
            'fingerprint' => hash('sha256', mb_strtolower(trim($validated['editName'])).'|'.$validated['editFamilyId']),
        ]);

        $lastSkuId = Sku::withTrashed()->max('id') ?? 0;

        foreach ($this->editSkus as $row) {
            $payload = [
                'variant_name' => $row['variant_name'] !== null && trim((string) $row['variant_name']) !== ''
                    ? trim((string) $row['variant_name']) : null,
                'sale_price' => $row['sale_price'] !== null && $row['sale_price'] !== ''
                    ? (float) $row['sale_price'] : null,
                'purchase_price' => $row['purchase_price'] !== null && $row['purchase_price'] !== ''
                    ? (float) $row['purchase_price'] : null,
                'status' => $row['status'],
            ];

            if ($row['id'] !== null) {
                Sku::findOrFail($row['id'])->update($payload);

                continue;
            }

            $lastSkuId++;

            Sku::create([
                'product_id' => $product->id,
                'internal_code' => 'SKU-'.str_pad((string) $lastSkuId, 6, '0', STR_PAD_LEFT),
                ...$payload,
                'fingerprint' => hash('sha256', $product->id.'|'.($payload['variant_name'] ?? '').'|'.uniqid()),
            ]);
        }

        $this->editingId = null;
        $this->editSkus = [];
        Flux::modal('product-edit')->close();
        Flux::toast(variant: 'success', text: 'Producto y variantes actualizados.');
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('delete', Product::findOrFail($id));
        $this->deletingId = $id;
        Flux::modal('product-delete')->show();
    }

    #[Computed]
    public function deletingProduct(): ?Product
    {
        return $this->deletingId
            ? Product::withCount('skus')->find($this->deletingId)
            : null;
    }

    public function delete(): void
    {
        $product = Product::withCount('skus')->findOrFail($this->deletingId);

        $this->authorize('delete', $product);

        if ($product->skus_count > 0) {
            Flux::toast(
                variant: 'danger',
                text: "Tiene {$product->skus_count} SKU(s). Elimínalos primero.",
            );

            return;
        }

        $name = $product->name;
        $product->delete();

        $this->deletingId = null;
        Flux::modal('product-delete')->close();
        Flux::toast(variant: 'success', text: "Producto «{$name}» eliminado.");
    }

}; ?>

<section class="w-full p-6">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Productos</flux:heading>
                <flux:text class="mt-1">Catálogo de productos. Cada producto puede tener varios SKUs (variantes).</flux:text>
            </div>

            @can('create', App\Models\Product::class)
                <flux:button
                    variant="primary"
                    icon="plus"
                    :href="route('products.create')"
                    wire:navigate
                    data-test="new-product"
                >
                    Nuevo producto
                </flux:button>
            @endcan
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-6">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Buscar por código, nombre o marca..."
                class="md:col-span-2"
            />

            <flux:select wire:model.live="familyFilter" placeholder="Todas las familias">
                <flux:select.option value="">Todas las familias</flux:select.option>
                @foreach ($this->families as $fam)
                    <flux:select.option :value="$fam->id">{{ $fam->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="subfamilyFilter" :disabled="! $familyFilter">
                <flux:select.option value="">Todas las subfamilias</flux:select.option>
                @foreach ($this->subfamiliesForFilter as $sf)
                    <flux:select.option :value="$sf->id">{{ $sf->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="statusFilter">
                <flux:select.option value="">Todos los estados</flux:select.option>
                @foreach (static::STATUSES as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="periodFilter">
                @foreach (static::CREATED_PERIODS as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="creatorFilter" class="md:col-span-2">
                <flux:select.option value="">Cualquier usuario</flux:select.option>
                @foreach ($this->creators as $creator)
                    <flux:select.option :value="$creator->id">{{ $creator->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:table :paginate="$this->products">
            <flux:table.columns>
                <flux:table.column>Código</flux:table.column>
                <flux:table.column>Nombre</flux:table.column>
                <flux:table.column>Familia</flux:table.column>
                <flux:table.column>Marca</flux:table.column>
                <flux:table.column>SKUs</flux:table.column>
                <flux:table.column>Creado por</flux:table.column>
                <flux:table.column>Fecha</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->products as $product)
                    <flux:table.row :key="$product->id">
                        <flux:table.cell variant="strong">
                            <code class="text-xs font-mono">{{ $product->internal_code }}</code>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <flux:link :href="route('products.skus', $product)" wire:navigate>
                                    {{ $product->name }}
                                </flux:link>
                                @if ($product->is_temporary)
                                    <flux:badge color="amber" size="sm" inset="top bottom">Temporal</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span class="text-sm">{{ $product->family?->name }}</span>
                                @if ($product->subfamily)
                                    <span class="text-xs text-zinc-500">{{ $product->subfamily->name }}</span>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $product->brand ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button
                                variant="ghost"
                                size="sm"
                                :href="route('products.skus', $product)"
                                wire:navigate
                                inset="top bottom"
                            >
                                <flux:badge color="zinc" size="sm" inset="top bottom">
                                    {{ $product->skus_count }}
                                </flux:badge>
                                <span class="ml-1">Ver</span>
                            </flux:button>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500">
                            {{ $product->creator?->name ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500">
                            {{ $product->created_at?->format('d/m/Y') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($product->status === 'active')
                                <flux:badge color="green" size="sm" inset="top bottom">Activo</flux:badge>
                            @elseif ($product->status === 'draft')
                                <flux:badge color="amber" size="sm" inset="top bottom">Borrador</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm" inset="top bottom">Descontinuado</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-1">
                                @can('update', $product)
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="pencil-square"
                                        wire:click="openEdit({{ $product->id }})"
                                        inset="top bottom"
                                    />
                                @endcan
                                @can('delete', $product)
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="trash"
                                        wire:click="confirmDelete({{ $product->id }})"
                                        inset="top bottom"
                                        class="text-red-600 hover:text-red-700"
                                    />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="9" class="text-center text-zinc-500 py-8">
                            No hay productos registrados.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- EDICIÓN: datos del producto (sin tocar SKUs) --}}
    <flux:modal name="product-edit" class="md:w-[40rem]">
        <form wire:submit="saveEdit" class="space-y-5">
            <div>
                <flux:heading size="lg">Editar producto</flux:heading>
                <flux:text class="mt-1">
                    Los SKUs se gestionan desde la ficha del producto.
                </flux:text>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input
                    wire:model="editInternalCode"
                    label="Código interno"
                    required
                    maxlength="20"
                />

                <flux:select wire:model="editStatus" label="Estado">
                    @foreach (static::STATUSES as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:input wire:model="editName" label="Nombre" required maxlength="200" />

            <flux:textarea wire:model="editDescription" label="Descripción" rows="2" />

            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model.live="editFamilyId" label="Familia" required>
                    <flux:select.option value="">Elige una familia...</flux:select.option>
                    @foreach ($this->families as $fam)
                        <flux:select.option :value="$fam->id">{{ $fam->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="editSubfamilyId" label="Subfamilia" :disabled="! $editFamilyId">
                    <flux:select.option value="">Sin subfamilia</flux:select.option>
                    @foreach ($this->editSubfamilies as $sf)
                        <flux:select.option :value="$sf->id">{{ $sf->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="editBrand" label="Marca" maxlength="100" />

                <flux:select wire:model="editUnitOfMeasure" label="Unidad de medida">
                    @foreach (static::UNITS as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="space-y-3 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                <flux:switch wire:model.live="editIsTemporary" label="Producto temporal" />
                @if ($editIsTemporary)
                    <flux:input wire:model="editTemporaryEndDate" type="date" label="Fecha de fin" />
                @endif
            </div>

            {{-- Variantes (SKUs) --}}
            <div class="space-y-3 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="sm">Variantes (SKUs)</flux:heading>
                    <flux:button
                        type="button"
                        variant="filled"
                        size="sm"
                        icon="plus"
                        wire:click="addEditVariant"
                        data-test="add-edit-variant"
                    >
                        Agregar variante
                    </flux:button>
                </div>

                @php
                    $canSeeCosts = auth()->user()->can('viewCosts', App\Models\Product::class);
                @endphp

                @if (count($editSkus) === 0)
                    <flux:text class="text-sm text-zinc-500">
                        Este producto aún no tiene variantes.
                    </flux:text>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-xs text-zinc-500">
                                <tr>
                                    <th class="text-left pb-2 pr-2">Código</th>
                                    <th class="text-left pb-2 pr-2">Variante</th>
                                    <th class="text-right pb-2 pr-2">P. venta</th>
                                    <th class="text-right pb-2 pr-2 {{ $canSeeCosts ? '' : 'hidden' }}">P. compra</th>
                                    <th class="text-left pb-2 pr-2">Estado</th>
                                    <th class="pb-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach ($editSkus as $i => $row)
                                    <tr wire:key="edit-sku-{{ $i }}-{{ $row['id'] ?? 'new' }}">
                                        <td class="py-2 pr-2 font-mono text-xs text-zinc-500 whitespace-nowrap">
                                            {{ $row['internal_code'] ?? '(nuevo)' }}
                                        </td>
                                        <td class="py-2 pr-2">
                                            <flux:input
                                                wire:model="editSkus.{{ $i }}.variant_name"
                                                size="sm"
                                                placeholder="Negro / Cuero"
                                            />
                                        </td>
                                        <td class="py-2 pr-2 w-24">
                                            <flux:input
                                                wire:model="editSkus.{{ $i }}.sale_price"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                size="sm"
                                                placeholder="—"
                                            />
                                        </td>
                                        <td class="py-2 pr-2 w-24 {{ $canSeeCosts ? '' : 'hidden' }}">
                                            @if ($canSeeCosts)
                                                <flux:input
                                                    wire:model="editSkus.{{ $i }}.purchase_price"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    size="sm"
                                                    placeholder="—"
                                                />
                                            @endif
                                        </td>
                                        <td class="py-2 pr-2 w-32">
                                            <flux:select wire:model="editSkus.{{ $i }}.status" size="sm">
                                                @foreach (static::STATUSES as $v => $label)
                                                    <flux:select.option :value="$v">{{ $label }}</flux:select.option>
                                                @endforeach
                                            </flux:select>
                                        </td>
                                        <td class="py-2">
                                            @if (($row['movement_lines_count'] ?? 0) > 0)
                                                <flux:tooltip content="{{ $row['movement_lines_count'] }} movimiento(s). No se puede eliminar, márcalo como descontinuado.">
                                                    <flux:button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        icon="lock-closed"
                                                        disabled
                                                        inset="top bottom"
                                                    />
                                                </flux:tooltip>
                                            @else
                                                <flux:button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    icon="trash"
                                                    wire:click="removeEditSku({{ $i }})"
                                                    wire:confirm="¿Eliminar esta variante?"
                                                    inset="top bottom"
                                                    class="text-red-600 hover:text-red-700"
                                                />
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <flux:text class="text-xs text-zinc-500">
                        Para editar atributos (color, material, etc.), usa la ficha completa del SKU.
                    </flux:text>
                @endif
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="save-edit-product">
                    Guardar cambios
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="product-delete" class="md:w-[28rem]">
        @if ($this->deletingProduct)
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">Eliminar producto</flux:heading>
                    <flux:text class="mt-2">
                        ¿Seguro que quieres eliminar <strong>{{ $this->deletingProduct->name }}</strong>
                        (<code>{{ $this->deletingProduct->internal_code }}</code>)?
                    </flux:text>
                </div>

                @if ($this->deletingProduct->skus_count > 0)
                    <div class="rounded-lg bg-red-50 dark:bg-red-950/30 p-4 text-sm text-red-700 dark:text-red-300">
                        Tiene <strong>{{ $this->deletingProduct->skus_count }}</strong> SKU(s).
                        Elimínalos primero.
                    </div>
                @else
                    <flux:text class="text-zinc-500">
                        El producto se archiva (soft delete). Se puede restaurar.
                    </flux:text>
                @endif

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancelar</flux:button>
                    </flux:modal.close>
                    <flux:button
                        variant="danger"
                        wire:click="delete"
                        :disabled="$this->deletingProduct->skus_count > 0"
                        data-test="confirm-delete-product"
                    >
                        Eliminar
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</section>
