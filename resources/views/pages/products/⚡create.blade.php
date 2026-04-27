<?php

use App\Models\Family;
use App\Models\Product;
use App\Models\Subfamily;
use App\Services\Catalog\ProductCreator;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Nuevo producto')] class extends Component {
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

    public int $step = 1;

    public string $newName = '';

    public string $newDescription = '';

    public ?int $newFamilyId = null;

    public ?int $newSubfamilyId = null;

    public string $newBrand = '';

    public string $newUnitOfMeasure = 'unit';

    public string $newStatus = 'active';

    public bool $newHasVariants = false;

    /**
     * @var array<int, array{variant_name: ?string, sale_price: ?string, purchase_price: ?string, attributes: array<string, string>}>
     */
    public array $newSkus = [];

    public string $draftVariantName = '';

    public ?string $draftSalePrice = null;

    public ?string $draftPurchasePrice = null;

    /**
     * @var array<string, string>
     */
    public array $draftAttributes = [];

    public function mount(): void
    {
        $this->authorize('create', Product::class);
    }

    public function updatedNewFamilyId(): void
    {
        $this->newSubfamilyId = null;
        $this->draftAttributes = [];
    }

    #[Computed]
    public function families(): EloquentCollection
    {
        return Family::query()->active()->orderBy('name')->get();
    }

    #[Computed]
    public function newSubfamilies(): EloquentCollection
    {
        if (! $this->newFamilyId) {
            return new EloquentCollection;
        }

        return Subfamily::query()
            ->where('family_id', $this->newFamilyId)
            ->active()
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function newFamilyAttributes(): EloquentCollection
    {
        if (! $this->newFamilyId) {
            return new EloquentCollection;
        }

        $family = Family::with(['attributes' => fn ($q) => $q->orderBy('family_attributes.sort_order')])
            ->find($this->newFamilyId);

        return $family?->attributes ?? new EloquentCollection;
    }

    public function nextStep(): void
    {
        $this->validate([
            'newName' => ['required', 'string', 'max:200'],
            'newFamilyId' => ['required', 'exists:families,id'],
            'newSubfamilyId' => ['nullable', 'exists:subfamilies,id'],
            'newBrand' => ['nullable', 'string', 'max:100'],
            'newDescription' => ['nullable', 'string'],
            'newUnitOfMeasure' => ['required', Rule::in(array_keys(self::UNITS))],
            'newStatus' => ['required', Rule::in(array_keys(self::STATUSES))],
        ]);

        $this->step = 2;
        $this->resetDraftSku();
    }

    public function backStep(): void
    {
        $this->step = 1;
    }

    public function addVariant(): void
    {
        $this->validate([
            'draftVariantName' => ['nullable', 'string', 'max:150'],
            'draftSalePrice' => ['nullable', 'numeric', 'min:0'],
            'draftPurchasePrice' => ['nullable', 'numeric', 'min:0'],
            'draftAttributes.*' => ['nullable', 'string', 'max:255'],
        ]);

        $this->newSkus[] = [
            'variant_name' => trim($this->draftVariantName) !== '' ? trim($this->draftVariantName) : null,
            'sale_price' => $this->draftSalePrice !== '' ? $this->draftSalePrice : null,
            'purchase_price' => $this->draftPurchasePrice !== '' ? $this->draftPurchasePrice : null,
            'attributes' => array_filter($this->draftAttributes, fn ($v) => $v !== null && $v !== ''),
        ];

        $this->resetDraftSku();
    }

    public function removeVariant(int $index): void
    {
        unset($this->newSkus[$index]);
        $this->newSkus = array_values($this->newSkus);
    }

    public function save()
    {
        $this->authorize('create', Product::class);

        // Si no se activó "tiene variantes" y no hay SKUs agregadas, tomamos el draft como el único SKU
        if (! $this->newHasVariants && $this->newSkus === []) {
            $this->addVariant();
        }

        if ($this->newSkus === []) {
            Flux::toast(variant: 'danger', text: 'Agrega al menos una variante antes de guardar.');

            return null;
        }

        $result = app(ProductCreator::class)->create([
            'name' => $this->newName,
            'family_id' => $this->newFamilyId,
            'subfamily_id' => $this->newSubfamilyId,
            'brand' => trim($this->newBrand) !== '' ? trim($this->newBrand) : null,
            'description' => trim($this->newDescription) !== '' ? trim($this->newDescription) : null,
            'unit_of_measure' => $this->newUnitOfMeasure,
            'status' => $this->newStatus,
            'created_by' => Auth::id(),
        ], array_map(fn ($sku) => [
            'variant_name' => $sku['variant_name'],
            'sale_price' => $sku['sale_price'] !== null ? (float) $sku['sale_price'] : null,
            'purchase_price' => $sku['purchase_price'] !== null ? (float) $sku['purchase_price'] : null,
            'status' => $this->newStatus,
            'attributes' => $sku['attributes'],
        ], $this->newSkus));

        $skuCount = $result['skus']->count();
        $product = $result['product'];

        Flux::toast(
            variant: 'success',
            text: "Producto creado con {$skuCount} SKU(s).",
        );

        return $this->redirectRoute('products.skus', $product, navigate: true);
    }

    private function resetDraftSku(): void
    {
        $this->draftVariantName = '';
        $this->draftSalePrice = null;
        $this->draftPurchasePrice = null;
        $this->draftAttributes = [];
    }
}; ?>

<section class="w-full p-6">
    <div class="mx-auto flex max-w-4xl flex-col gap-6">
        <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2 text-sm text-zinc-500">
                <flux:link :href="route('products.index')" wire:navigate>Productos</flux:link>
                <span>/</span>
                <span>Nuevo producto</span>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:heading size="xl">Nuevo producto</flux:heading>
                <flux:badge size="sm" inset="top bottom">Paso {{ $step }} de 2</flux:badge>
            </div>
        </div>

        {{-- Stepper visual --}}
        <div class="flex items-center gap-3 text-sm">
            <div @class([
                'flex items-center gap-2 px-3 py-1.5 rounded-full border',
                'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900' => $step === 1,
                'border-zinc-200 text-zinc-500 dark:border-zinc-700' => $step !== 1,
            ])>
                <span @class([
                    'flex size-5 items-center justify-center rounded-full text-xs font-semibold',
                    'bg-white text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100' => $step === 1,
                    'bg-zinc-100 dark:bg-zinc-800' => $step !== 1,
                ])>1</span>
                Datos del producto
            </div>
            <div class="h-px w-8 bg-zinc-300 dark:bg-zinc-700"></div>
            <div @class([
                'flex items-center gap-2 px-3 py-1.5 rounded-full border',
                'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900' => $step === 2,
                'border-zinc-200 text-zinc-500 dark:border-zinc-700' => $step !== 2,
            ])>
                <span @class([
                    'flex size-5 items-center justify-center rounded-full text-xs font-semibold',
                    'bg-white text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100' => $step === 2,
                    'bg-zinc-100 dark:bg-zinc-800' => $step !== 2,
                ])>2</span>
                Variantes (SKUs)
            </div>
        </div>

        @if ($step === 1)
            {{-- ======== Paso 1: Datos del producto ======== --}}
            <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="space-y-5">
                    <flux:input
                        wire:model="newName"
                        label="Nombre del producto"
                        placeholder="Silla gerencial Milano"
                        required
                        maxlength="200"
                    />

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <flux:select wire:model.live="newFamilyId" label="Familia" required>
                            <flux:select.option value="">Elige una familia...</flux:select.option>
                            @foreach ($this->families as $fam)
                                <flux:select.option :value="$fam->id">{{ $fam->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model="newSubfamilyId" label="Subfamilia" :disabled="! $newFamilyId">
                            <flux:select.option value="">Pendiente (por defecto)</flux:select.option>
                            @foreach ($this->newSubfamilies as $sf)
                                <flux:select.option :value="$sf->id">{{ $sf->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <flux:input wire:model="newBrand" label="Marca" placeholder="ErgoMax" maxlength="100" />

                        <flux:select wire:model="newUnitOfMeasure" label="Unidad de medida">
                            @foreach (static::UNITS as $value => $label)
                                <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <flux:textarea wire:model="newDescription" label="Descripción" rows="3" />

                    <flux:select wire:model="newStatus" label="Estado inicial">
                        @foreach (static::STATUSES as $value => $label)
                            <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <flux:switch wire:model.live="newHasVariants" label="Este producto tiene varias variantes (SKUs)" />
                        <flux:text class="mt-1 text-xs text-zinc-500">
                            Actívalo si hay distintos colores, materiales o medidas del mismo producto.
                            Si está apagado, se crea un único SKU genérico.
                        </flux:text>
                    </div>
                </div>
            </div>

            <div class="flex justify-between gap-2">
                <flux:button variant="ghost" :href="route('products.index')" wire:navigate>
                    Cancelar
                </flux:button>
                <flux:button variant="primary" wire:click="nextStep" data-test="wizard-next">
                    Siguiente →
                </flux:button>
            </div>
        @else
            {{-- ======== Paso 2: SKU(s) ======== --}}
            <div class="space-y-5">
                @if (count($newSkus) > 0)
                    <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="border-b border-zinc-200 p-3 text-xs font-semibold uppercase text-zinc-500 dark:border-zinc-700">
                            Variantes agregadas ({{ count($newSkus) }})
                        </div>
                        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($newSkus as $i => $sku)
                                <div class="flex items-center justify-between p-3" wire:key="added-sku-{{ $i }}">
                                    <div class="flex flex-col">
                                        <span class="text-sm">
                                            {{ $sku['variant_name'] ?? '(sin nombre de variante)' }}
                                        </span>
                                        <span class="text-xs text-zinc-500">
                                            @foreach ($sku['attributes'] as $code => $val)
                                                {{ $code }}: {{ $val }}{{ ! $loop->last ? ' · ' : '' }}
                                            @endforeach
                                            @if (($sku['sale_price'] ?? null) !== null)
                                                · Venta S/ {{ $sku['sale_price'] }}
                                            @endif
                                        </span>
                                    </div>
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="trash"
                                        wire:click="removeVariant({{ $i }})"
                                        inset="top bottom"
                                        class="text-red-600 hover:text-red-700"
                                    />
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="sm">
                        {{ $newHasVariants ? 'Nueva variante' : 'Datos del SKU' }}
                    </flux:heading>

                    <div class="mt-4 space-y-4">
                        @if ($newHasVariants)
                            <flux:input
                                wire:model="draftVariantName"
                                label="Nombre de la variante"
                                placeholder="Negro / Cuero"
                                maxlength="150"
                            />
                        @endif

                        @if ($this->newFamilyAttributes->isNotEmpty())
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                @foreach ($this->newFamilyAttributes as $attr)
                                    @php
                                        $field = "draftAttributes.{$attr->code}";
                                    @endphp
                                    <div wire:key="attr-{{ $attr->code }}">
                                        @if ($attr->type === 'list')
                                            <flux:select wire:model="{{ $field }}" :label="$attr->name">
                                                <flux:select.option value="">—</flux:select.option>
                                                @foreach ($attr->options ?? [] as $opt)
                                                    <flux:select.option :value="$opt">{{ $opt }}</flux:select.option>
                                                @endforeach
                                            </flux:select>
                                        @else
                                            <flux:input
                                                wire:model="{{ $field }}"
                                                :label="$attr->name"
                                                :placeholder="$attr->unit ?? ''"
                                            />
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <flux:input
                                wire:model="draftSalePrice"
                                type="number"
                                step="0.01"
                                min="0"
                                label="Precio de venta (S/)"
                            />
                            <flux:input
                                wire:model="draftPurchasePrice"
                                type="number"
                                step="0.01"
                                min="0"
                                label="Precio de compra (S/)"
                            />
                        </div>

                        @if ($newHasVariants)
                            <div class="flex justify-end">
                                <flux:button
                                    variant="filled"
                                    icon="plus"
                                    wire:click="addVariant"
                                    data-test="wizard-add-variant"
                                >
                                    Agregar esta variante
                                </flux:button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex justify-between gap-2">
                <flux:button variant="ghost" wire:click="backStep">← Atrás</flux:button>
                <div class="flex gap-2">
                    <flux:button variant="ghost" :href="route('products.index')" wire:navigate>
                        Cancelar
                    </flux:button>
                    <flux:button variant="primary" wire:click="save" data-test="wizard-save">
                        @if ($newHasVariants)
                            Crear producto con {{ count($newSkus) }} variante(s)
                        @else
                            Crear producto
                        @endif
                    </flux:button>
                </div>
            </div>
        @endif
    </div>
</section>
