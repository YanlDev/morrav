<?php

use App\Models\Family;
use App\Models\Movement;
use App\Models\MovementLine;
use App\Models\Sku;
use App\Models\Subfamily;
use App\Services\Catalog\ProductCreator;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Movimiento')] class extends Component {
    public const TYPE_LABELS = [
        'inbound' => 'Ingreso',
        'outbound' => 'Salida',
        'adjustment' => 'Ajuste',
        'initial_load' => 'Carga inicial',
        'transfer' => 'Traspaso',
    ];

    public Movement $movement;

    public ?int $lineSkuId = null;

    public string $lineSkuSearch = '';

    public ?string $lineQuantity = null;

    public ?string $lineUnitCost = null;

    public string $lineDirection = 'in';

    public string $lineNotes = '';

    public string $voidReason = '';

    /**
     * Filtro del picker: 'recent' (por defecto), 'all', o el id de una subfamilia.
     */
    public string $pickerTab = 'recent';

    // Wizard para crear producto + SKUs + cantidades al vuelo
    public const UNITS = [
        'unit' => 'Unidad',
        'meter' => 'Metro',
        'kg' => 'Kilogramo',
        'set' => 'Juego',
        'pair' => 'Par',
        'box' => 'Caja',
    ];

    public int $wizardStep = 1;

    // Paso 1 - producto
    public string $wizName = '';

    public string $wizDescription = '';

    public ?int $wizFamilyId = null;

    public ?int $wizSubfamilyId = null;

    public string $wizBrand = '';

    public string $wizUnitOfMeasure = 'unit';

    public bool $wizHasVariants = false;

    // Paso 2 - SKUs creados + borrador actual
    /**
     * @var array<int, array{variant_name: ?string, sale_price: ?string, purchase_price: ?string, attributes: array<string, string>}>
     */
    public array $wizSkus = [];

    public string $wizDraftVariantName = '';

    public ?string $wizDraftSalePrice = null;

    public ?string $wizDraftPurchasePrice = null;

    /**
     * @var array<string, string>
     */
    public array $wizDraftAttributes = [];

    // Paso 3 - cantidades por SKU (indexadas por posición en $wizSkus)
    /**
     * @var array<int, string>
     */
    public array $wizQuantities = [];

    public function mount(Movement $movement): void
    {
        $this->authorize('view', $movement);

        $this->movement = $movement->load(['originWarehouse', 'destinationWarehouse', 'creator']);
        $this->lineDirection = match ($movement->type) {
            'inbound', 'initial_load' => 'in',
            'outbound' => 'out',
            default => 'in',
        };
    }

    #[Computed]
    public function lines()
    {
        $query = $this->movement->lines()
            ->with(['sku.product:id,name,internal_code', 'warehouse:id,code,name'])
            ->orderBy('id');

        // En un traspaso cada línea lógica vive como 2 filas (out + in).
        // Mostramos solo la salida — la columna "Almacén" renderiza origen → destino.
        if ($this->movement->type === 'transfer') {
            $query->where('direction', 'out');
        }

        return $query->get();
    }

    #[Computed]
    public function skuOptions()
    {
        $term = trim($this->lineSkuSearch);
        $subfamilyId = is_numeric($this->pickerTab) ? (int) $this->pickerTab : null;

        $query = Sku::query()
            ->with('product:id,name,internal_code,subfamily_id')
            ->where('status', '!=', 'discontinued');

        if ($term !== '') {
            $pattern = '%'.mb_strtolower($term).'%';
            $query->where(fn ($q) => $q
                ->whereRaw('LOWER(internal_code) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(variant_name) LIKE ?', [$pattern])
                ->orWhereHas('product', fn ($p) => $p->whereRaw('LOWER(internal_code) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$pattern])));
        }

        if ($subfamilyId !== null) {
            $query->whereHas('product', fn ($p) => $p->where('subfamily_id', $subfamilyId));
        }

        // Tab "recientes": priorizar los SKUs que este usuario tocó en los últimos 7 días.
        if ($this->pickerTab === 'recent' && $term === '') {
            $recentIds = $this->recentSkuIds();

            if ($recentIds !== []) {
                $query->whereIn('id', $recentIds);
                $ordering = DB::raw('CASE id '.collect($recentIds)
                    ->map(fn ($id, $i) => 'WHEN '.(int) $id.' THEN '.$i)
                    ->implode(' ').' END');

                return $query->orderBy($ordering)->limit(20)->get();
            }
        }

        return $query->orderBy('internal_code')->limit(20)->get();
    }

    /**
     * IDs de SKUs que el usuario actual usó en movimientos recientes (últimos 7 días),
     * ordenados del más reciente al más antiguo, sin repetidos.
     *
     * @return array<int, int>
     */
    private function recentSkuIds(): array
    {
        $since = now()->subDays(7);

        return MovementLine::query()
            ->join('movements', 'movement_lines.movement_id', '=', 'movements.id')
            ->where('movements.created_by', Auth::id())
            ->where('movements.id', '!=', $this->movement->id)
            ->where('movement_lines.created_at', '>=', $since)
            ->select('movement_lines.sku_id')
            ->selectRaw('MAX(movement_lines.created_at) as last_used')
            ->groupBy('movement_lines.sku_id')
            ->orderByDesc('last_used')
            ->limit(20)
            ->pluck('sku_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Subfamilias con al menos un SKU activo. Se muestran como chips/tabs.
     */
    #[Computed]
    public function pickerSubfamilies(): EloquentCollection
    {
        return Subfamily::query()
            ->active()
            ->whereHas('products.skus', fn ($q) => $q->where('status', '!=', 'discontinued'))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function hasRecentUsage(): bool
    {
        return $this->recentSkuIds() !== [];
    }

    public function setPickerTab(string $tab): void
    {
        $this->pickerTab = $tab;
    }

    /**
     * Stock actual en el almacén de origen para cada SKU listado.
     * Solo aplica a outbound / transfer / adjustment; permite decidir si el SKU tiene stock suficiente.
     *
     * @return array<int, float>
     */
    #[Computed]
    public function skuOptionsStock(): array
    {
        $originId = match ($this->movement->type) {
            'outbound', 'transfer', 'adjustment' => $this->movement->origin_warehouse_id,
            default => null,
        };

        if ($originId === null) {
            return [];
        }

        $skuIds = $this->skuOptions->pluck('id');

        if ($skuIds->isEmpty()) {
            return [];
        }

        $rows = DB::table('movement_lines as ml')
            ->join('movements as m', 'ml.movement_id', '=', 'm.id')
            ->where('m.status', 'confirmed')
            ->where('ml.warehouse_id', $originId)
            ->whereIn('ml.sku_id', $skuIds)
            ->select('ml.sku_id', DB::raw(
                "SUM(CASE WHEN ml.direction = 'in' THEN ml.quantity ELSE -ml.quantity END) as total"
            ))
            ->groupBy('ml.sku_id')
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row->sku_id] = (float) $row->total;
        }

        return $map;
    }

    #[Computed]
    public function totalSkus(): int
    {
        return Sku::query()->where('status', '!=', 'discontinued')->count();
    }

    #[Computed]
    public function selectedSku(): ?Sku
    {
        return $this->lineSkuId
            ? Sku::with('product:id,name,internal_code')->find($this->lineSkuId)
            : null;
    }

    public function pickSku(int $skuId): void
    {
        $this->lineSkuId = $skuId;
        $this->lineSkuSearch = '';
    }

    public function clearSku(): void
    {
        $this->lineSkuId = null;
    }

    public function addLine(): void
    {
        $this->authorize('update', $this->movement);
        abort_unless($this->movement->status === 'draft', 403);

        $validated = $this->validate([
            'lineSkuId' => ['required', 'exists:skus,id'],
            'lineQuantity' => ['required', 'numeric', 'gt:0'],
            'lineUnitCost' => ['nullable', 'numeric', 'min:0'],
            'lineDirection' => ['required', 'in:in,out'],
            'lineNotes' => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->movement->type === 'transfer') {
            $this->addTransferLines($validated);
        } else {
            $warehouseId = match ($this->movement->type) {
                'inbound', 'initial_load' => $this->movement->destination_warehouse_id,
                'outbound', 'adjustment' => $this->movement->origin_warehouse_id,
                default => null,
            };

            abort_if($warehouseId === null, 422, 'El movimiento no tiene almacén asignado.');

            MovementLine::create([
                'movement_id' => $this->movement->id,
                'sku_id' => $validated['lineSkuId'],
                'warehouse_id' => $warehouseId,
                'direction' => $this->movement->type === 'adjustment'
                    ? $validated['lineDirection']
                    : $this->defaultDirection(),
                'quantity' => $validated['lineQuantity'],
                'unit_cost' => $validated['lineUnitCost'] ?? null,
                'notes' => $validated['lineNotes'] ?? null,
            ]);
        }

        unset($this->lines);
        $this->resetLineForm();
        Flux::toast(variant: 'success', text: 'Línea agregada.');
    }

    /**
     * Un traspaso genera dos filas con el mismo `movement_id`:
     * una `out` del origen y una `in` al destino.
     *
     * @param  array<string, mixed>  $validated
     */
    private function addTransferLines(array $validated): void
    {
        abort_if(
            $this->movement->origin_warehouse_id === null
                || $this->movement->destination_warehouse_id === null,
            422,
            'El traspaso requiere almacén origen y destino.'
        );

        DB::transaction(function () use ($validated) {
            MovementLine::create([
                'movement_id' => $this->movement->id,
                'sku_id' => $validated['lineSkuId'],
                'warehouse_id' => $this->movement->origin_warehouse_id,
                'direction' => 'out',
                'quantity' => $validated['lineQuantity'],
                'notes' => $validated['lineNotes'] ?? null,
            ]);

            MovementLine::create([
                'movement_id' => $this->movement->id,
                'sku_id' => $validated['lineSkuId'],
                'warehouse_id' => $this->movement->destination_warehouse_id,
                'direction' => 'in',
                'quantity' => $validated['lineQuantity'],
                'notes' => $validated['lineNotes'] ?? null,
            ]);
        });
    }

    public function removeLine(int $lineId): void
    {
        $this->authorize('update', $this->movement);
        abort_unless($this->movement->status === 'draft', 403);

        if ($this->movement->type === 'transfer') {
            $this->removeTransferPair($lineId);
        } else {
            MovementLine::where('movement_id', $this->movement->id)
                ->where('id', $lineId)
                ->delete();
        }

        unset($this->lines);
        Flux::toast(variant: 'success', text: 'Línea eliminada.');
    }

    /**
     * Borra la fila y su contraparte en el mismo traspaso (mismo sku + qty, dirección opuesta).
     */
    private function removeTransferPair(int $lineId): void
    {
        $line = MovementLine::where('movement_id', $this->movement->id)
            ->where('id', $lineId)
            ->first();

        if (! $line) {
            return;
        }

        DB::transaction(function () use ($line) {
            MovementLine::where('movement_id', $this->movement->id)
                ->where('sku_id', $line->sku_id)
                ->where('quantity', $line->quantity)
                ->where('direction', $line->direction === 'in' ? 'out' : 'in')
                ->limit(1)
                ->delete();

            $line->delete();
        });
    }

    public function confirm(): void
    {
        $this->authorize('confirm', $this->movement);
        abort_unless($this->movement->status === 'draft', 403);

        if ($this->lines->isEmpty()) {
            Flux::toast(variant: 'danger', text: 'Agrega al menos una línea antes de confirmar.');

            return;
        }

        $this->movement->update([
            'status' => 'confirmed',
            'confirmed_by' => Auth::id(),
            'confirmed_at' => now(),
        ]);

        $this->movement->refresh();
        Flux::toast(variant: 'success', text: 'Movimiento confirmado. El stock se actualizó.');
    }

    public function openVoid(): void
    {
        $this->authorize('void', $this->movement);
        $this->voidReason = '';
        Flux::modal('movement-void')->show();
    }

    public function voidMovement(): void
    {
        $this->authorize('void', $this->movement);
        abort_unless($this->movement->status === 'confirmed', 403);

        $validated = $this->validate([
            'voidReason' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'voidReason.required' => 'Indica el motivo de la anulación.',
            'voidReason.min' => 'El motivo debe tener al menos 5 caracteres.',
        ]);

        $this->movement->update([
            'status' => 'voided',
            'voided_by' => Auth::id(),
            'voided_at' => now(),
            'void_reason' => $validated['voidReason'],
        ]);

        $this->movement->refresh();
        Flux::modal('movement-void')->close();
        Flux::toast(variant: 'success', text: 'Movimiento anulado.');
    }

    public function deleteDraft(): void
    {
        $this->authorize('delete', $this->movement);
        abort_unless($this->movement->status === 'draft', 403);

        $this->movement->lines()->delete();
        $this->movement->delete();

        Flux::toast(variant: 'success', text: 'Borrador eliminado.');

        $this->redirectRoute('movements.index', navigate: true);
    }

    private function defaultDirection(): string
    {
        return match ($this->movement->type) {
            'inbound', 'initial_load' => 'in',
            'outbound' => 'out',
            default => 'in',
        };
    }

    // =========================
    //  Wizard crear producto
    // =========================

    #[Computed]
    public function wizardFamilies(): EloquentCollection
    {
        return Family::query()->active()->orderBy('name')->get();
    }

    #[Computed]
    public function wizardSubfamilies(): EloquentCollection
    {
        if (! $this->wizFamilyId) {
            return new EloquentCollection;
        }

        return Subfamily::query()
            ->where('family_id', $this->wizFamilyId)
            ->active()
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function wizardFamilyAttributes(): EloquentCollection
    {
        if (! $this->wizFamilyId) {
            return new EloquentCollection;
        }

        $family = Family::with(['attributes' => fn ($q) => $q->orderBy('family_attributes.sort_order')])
            ->find($this->wizFamilyId);

        return $family?->attributes ?? new EloquentCollection;
    }

    public function openWizard(): void
    {
        $this->resetWizard();
        Flux::modal('product-wizard')->show();
    }

    public function updatedWizFamilyId(): void
    {
        $this->wizSubfamilyId = null;
        $this->wizDraftAttributes = [];
    }

    public function wizardNext(): void
    {
        if ($this->wizardStep === 1) {
            $this->validate([
                'wizName' => ['required', 'string', 'max:200'],
                'wizFamilyId' => ['required', 'exists:families,id'],
                'wizSubfamilyId' => ['nullable', 'exists:subfamilies,id'],
                'wizBrand' => ['nullable', 'string', 'max:100'],
                'wizDescription' => ['nullable', 'string'],
                'wizUnitOfMeasure' => ['required', Rule::in(array_keys(self::UNITS))],
            ]);

            $this->wizardStep = 2;
            $this->resetWizDraft();

            return;
        }

        if ($this->wizardStep === 2) {
            // Si no activó variantes y aún no agrega el único SKU, lo agregamos ahora
            if (! $this->wizHasVariants && $this->wizSkus === []) {
                $this->wizardAddVariant();
            }

            if ($this->wizSkus === []) {
                Flux::toast(variant: 'danger', text: 'Agrega al menos una variante antes de continuar.');

                return;
            }

            // Prefill cantidades con 0 para cada SKU creado
            $this->wizQuantities = array_fill(0, count($this->wizSkus), '');
            $this->wizardStep = 3;
        }
    }

    public function wizardBack(): void
    {
        if ($this->wizardStep > 1) {
            $this->wizardStep--;
        }
    }

    public function wizardAddVariant(): void
    {
        $this->validate([
            'wizDraftVariantName' => ['nullable', 'string', 'max:150'],
            'wizDraftSalePrice' => ['nullable', 'numeric', 'min:0'],
            'wizDraftPurchasePrice' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->wizSkus[] = [
            'variant_name' => trim($this->wizDraftVariantName) !== '' ? trim($this->wizDraftVariantName) : null,
            'sale_price' => $this->wizDraftSalePrice !== '' ? $this->wizDraftSalePrice : null,
            'purchase_price' => $this->wizDraftPurchasePrice !== '' ? $this->wizDraftPurchasePrice : null,
            'attributes' => array_filter($this->wizDraftAttributes, fn ($v) => $v !== null && $v !== ''),
        ];

        $this->resetWizDraft();
    }

    public function wizardRemoveVariant(int $index): void
    {
        unset($this->wizSkus[$index]);
        $this->wizSkus = array_values($this->wizSkus);
        $this->wizQuantities = [];
    }

    public function wizardSave(): void
    {
        $this->authorize('update', $this->movement);
        $this->authorize('create', \App\Models\Product::class);
        abort_unless($this->movement->status === 'draft', 403);
        abort_unless(in_array($this->movement->type, ['inbound', 'initial_load'], true), 403);

        $rules = [];
        foreach ($this->wizSkus as $i => $_) {
            $rules["wizQuantities.{$i}"] = ['required', 'numeric', 'gt:0'];
        }
        $this->validate($rules, [], [
            'wizQuantities.*' => 'cantidad',
        ]);

        $skusPayload = [];
        foreach ($this->wizSkus as $i => $sku) {
            $skusPayload[] = [
                'variant_name' => $sku['variant_name'],
                'sale_price' => $sku['sale_price'] !== null ? (float) $sku['sale_price'] : null,
                'purchase_price' => $sku['purchase_price'] !== null ? (float) $sku['purchase_price'] : null,
                'status' => 'active',
                'attributes' => $sku['attributes'],
                'quantity' => (float) $this->wizQuantities[$i],
            ];
        }

        app(ProductCreator::class)->create([
            'name' => $this->wizName,
            'family_id' => $this->wizFamilyId,
            'subfamily_id' => $this->wizSubfamilyId,
            'brand' => trim($this->wizBrand) !== '' ? trim($this->wizBrand) : null,
            'description' => trim($this->wizDescription) !== '' ? trim($this->wizDescription) : null,
            'unit_of_measure' => $this->wizUnitOfMeasure,
            'status' => 'active',
            'created_by' => Auth::id(),
        ], $skusPayload, $this->movement);

        unset($this->lines);
        $this->resetWizard();
        Flux::modal('product-wizard')->close();
        Flux::toast(variant: 'success', text: 'Producto creado y líneas agregadas al movimiento.');
    }

    private function resetWizard(): void
    {
        $this->reset([
            'wizardStep', 'wizName', 'wizDescription', 'wizFamilyId', 'wizSubfamilyId',
            'wizBrand', 'wizHasVariants', 'wizSkus', 'wizQuantities',
            'wizDraftVariantName', 'wizDraftSalePrice', 'wizDraftPurchasePrice', 'wizDraftAttributes',
        ]);
        $this->wizardStep = 1;
        $this->wizUnitOfMeasure = 'unit';
        $this->resetValidation();
    }

    private function resetWizDraft(): void
    {
        $this->wizDraftVariantName = '';
        $this->wizDraftSalePrice = null;
        $this->wizDraftPurchasePrice = null;
        $this->wizDraftAttributes = [];
    }

    private function resetLineForm(): void
    {
        $this->reset(['lineSkuId', 'lineSkuSearch', 'lineQuantity', 'lineUnitCost', 'lineNotes']);
        $this->lineDirection = $this->defaultDirection();
        $this->resetValidation();
    }
}; ?>

<section class="w-full p-6">
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2 text-sm text-zinc-500">
                <flux:link :href="route('movements.index')" wire:navigate>Movimientos</flux:link>
                <span>/</span>
                <span>{{ $movement->number }}</span>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <flux:heading size="xl">{{ $movement->number }}</flux:heading>
                    <flux:badge size="md" inset="top bottom">
                        {{ static::TYPE_LABELS[$movement->type] ?? $movement->type }}
                    </flux:badge>
                    @if ($movement->status === 'confirmed')
                        <flux:badge color="green" inset="top bottom">Confirmado</flux:badge>
                    @elseif ($movement->status === 'draft')
                        <flux:badge color="amber" inset="top bottom">Borrador</flux:badge>
                    @else
                        <flux:badge color="red" inset="top bottom">Anulado</flux:badge>
                    @endif
                </div>

                <div class="flex gap-2">
                    @if ($movement->status === 'draft')
                        @can('confirm', $movement)
                            <flux:button variant="primary" icon="check" wire:click="confirm" data-test="confirm-movement">
                                Confirmar
                            </flux:button>
                        @endcan
                        @can('delete', $movement)
                            <flux:button
                                variant="danger"
                                icon="trash"
                                wire:click="deleteDraft"
                                wire:confirm="¿Eliminar el borrador?"
                            >
                                Descartar
                            </flux:button>
                        @endcan
                    @elseif ($movement->status === 'confirmed')
                        @if (in_array($movement->type, ['inbound', 'initial_load'], true))
                            <flux:button
                                variant="filled"
                                icon="printer"
                                :href="route('stickers.print', ['movement' => $movement->id])"
                                target="_blank"
                                data-test="print-movement-stickers"
                            >
                                Imprimir etiquetas
                            </flux:button>
                        @endif
                        @can('void', $movement)
                            <flux:button variant="danger" icon="no-symbol" wire:click="openVoid" data-test="open-void">
                                Anular
                            </flux:button>
                        @endcan
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
            <div>
                <flux:text class="text-xs text-zinc-500">Fecha</flux:text>
                <flux:text>{{ $movement->occurred_at?->format('d/m/Y H:i') }}</flux:text>
            </div>
            <div>
                <flux:text class="text-xs text-zinc-500">Creado por</flux:text>
                <flux:text>{{ $movement->creator?->name ?? '—' }}</flux:text>
            </div>
            @if ($movement->originWarehouse)
                <div>
                    <flux:text class="text-xs text-zinc-500">
                        {{ $movement->type === 'adjustment' ? 'Almacén' : 'Origen' }}
                    </flux:text>
                    <flux:text>{{ $movement->originWarehouse->code }} — {{ $movement->originWarehouse->name }}</flux:text>
                </div>
            @endif
            @if ($movement->destinationWarehouse && $movement->type !== 'adjustment')
                <div>
                    <flux:text class="text-xs text-zinc-500">Destino</flux:text>
                    <flux:text>{{ $movement->destinationWarehouse->code }} — {{ $movement->destinationWarehouse->name }}</flux:text>
                </div>
            @endif
            @if ($movement->reason)
                <div class="col-span-2 md:col-span-4">
                    <flux:text class="text-xs text-zinc-500">Motivo</flux:text>
                    <flux:text>{{ $movement->reason }}</flux:text>
                </div>
            @endif
            @if ($movement->status === 'voided')
                <div class="col-span-2 md:col-span-4 rounded bg-red-50 dark:bg-red-950/30 p-2 text-sm text-red-700 dark:text-red-300">
                    <strong>Anulado</strong> el {{ $movement->voided_at?->format('d/m/Y H:i') }}.
                    Motivo: {{ $movement->void_reason }}
                </div>
            @endif
        </div>

        @if ($movement->status === 'draft' && auth()->user()->can('update', $movement))
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="sm">Agregar línea</flux:heading>
                    @if (in_array($movement->type, ['inbound', 'initial_load'], true) && auth()->user()->can('create', App\Models\Product::class))
                        <flux:button
                            variant="filled"
                            size="sm"
                            icon="plus"
                            wire:click="openWizard"
                            data-test="open-wizard"
                        >
                            Crear producto nuevo
                        </flux:button>
                    @endif
                </div>

                @if (! $this->selectedSku)
                    <div class="flex flex-wrap gap-2">
                        @if ($this->hasRecentUsage)
                            <button
                                type="button"
                                wire:click="setPickerTab('recent')"
                                class="rounded-full px-3 py-1 text-xs font-medium transition {{ $pickerTab === 'recent'
                                    ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900'
                                    : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}"
                            >
                                Recientes
                            </button>
                        @endif
                        <button
                            type="button"
                            wire:click="setPickerTab('all')"
                            class="rounded-full px-3 py-1 text-xs font-medium transition {{ $pickerTab === 'all' || (! $this->hasRecentUsage && $pickerTab === 'recent')
                                ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900'
                                : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}"
                        >
                            Todos
                        </button>
                        @foreach ($this->pickerSubfamilies as $sub)
                            <button
                                type="button"
                                wire:click="setPickerTab('{{ $sub->id }}')"
                                class="rounded-full px-3 py-1 text-xs font-medium transition {{ (string) $pickerTab === (string) $sub->id
                                    ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900'
                                    : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}"
                            >
                                {{ $sub->name }}
                            </button>
                        @endforeach
                    </div>

                    <flux:input
                        wire:model.live.debounce.300ms="lineSkuSearch"
                        icon="magnifying-glass"
                        placeholder="Buscar por código, variante o nombre del producto..."
                    />

                    @php
                        $showStock = in_array($movement->type, ['outbound', 'transfer', 'adjustment'], true);
                        $originWarehouseCode = $movement->originWarehouse?->code;
                    @endphp

                    <div class="flex items-center justify-between text-xs text-zinc-500">
                        <span>
                            @if ($lineSkuSearch)
                                {{ $this->skuOptions->count() }} resultado(s) para «{{ $lineSkuSearch }}»
                            @else
                                Mostrando {{ $this->skuOptions->count() }} de {{ $this->totalSkus }} SKUs
                            @endif
                        </span>
                        @if ($showStock && $originWarehouseCode)
                            <span>Stock mostrado en <strong>{{ $originWarehouseCode }}</strong></span>
                        @endif
                    </div>

                    @if ($this->skuOptions->isEmpty())
                        <div class="rounded border border-dashed border-zinc-300 dark:border-zinc-700 p-6 text-center text-sm text-zinc-500">
                            @if ($lineSkuSearch)
                                Sin coincidencias. Prueba con otro término.
                            @else
                                Aún no hay SKUs registrados.
                                <flux:link :href="route('products.index')" wire:navigate>Ir al catálogo</flux:link>
                            @endif
                        </div>
                    @else
                        <div class="max-h-80 overflow-y-auto divide-y divide-zinc-200 dark:divide-zinc-700 rounded border border-zinc-200 dark:border-zinc-700">
                            @foreach ($this->skuOptions as $opt)
                                @php
                                    $stock = $this->skuOptionsStock[$opt->id] ?? 0.0;
                                    $stockColor = match (true) {
                                        $stock > 0 => 'text-green-700 dark:text-green-400',
                                        $stock < 0 => 'text-red-600 dark:text-red-400',
                                        default => 'text-zinc-400',
                                    };
                                @endphp
                                <button
                                    type="button"
                                    wire:click="pickSku({{ $opt->id }})"
                                    class="w-full p-3 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800 flex items-center justify-between gap-4"
                                >
                                    <div class="flex flex-col min-w-0">
                                        <span class="text-sm truncate">
                                            <code class="font-mono text-xs">{{ $opt->internal_code }}</code>
                                            — {{ $opt->product?->name }}
                                        </span>
                                        @if ($opt->variant_name)
                                            <span class="text-xs text-zinc-500 truncate">{{ $opt->variant_name }}</span>
                                        @endif
                                    </div>
                                    @if ($showStock)
                                        <div class="text-right shrink-0">
                                            <div class="text-xs text-zinc-500">Stock</div>
                                            <div class="text-sm font-semibold {{ $stockColor }}">
                                                {{ rtrim(rtrim(number_format($stock, 2), '0'), '.') ?: '0' }}
                                            </div>
                                        </div>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    @endif
                @else
                    <div class="flex items-center justify-between rounded bg-zinc-100 dark:bg-zinc-800 p-2">
                        <div class="flex flex-col">
                            <span class="text-sm">
                                <code class="font-mono text-xs">{{ $this->selectedSku->internal_code }}</code>
                                — {{ $this->selectedSku->product?->name }}
                            </span>
                            @if ($this->selectedSku->variant_name)
                                <span class="text-xs text-zinc-500">{{ $this->selectedSku->variant_name }}</span>
                            @endif
                        </div>
                        <flux:button variant="ghost" size="sm" icon="x-mark" wire:click="clearSku" inset="top bottom" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <flux:input
                            wire:model="lineQuantity"
                            type="number"
                            step="0.01"
                            min="0.01"
                            label="Cantidad"
                            required
                        />

                        @if ($movement->type === 'inbound' || $movement->type === 'initial_load')
                            <flux:input
                                wire:model="lineUnitCost"
                                type="number"
                                step="0.01"
                                min="0"
                                label="Costo unitario (S/)"
                            />
                        @endif

                        @if ($movement->type === 'adjustment')
                            <flux:select wire:model="lineDirection" label="Dirección">
                                <flux:select.option value="in">Entrada</flux:select.option>
                                <flux:select.option value="out">Salida</flux:select.option>
                            </flux:select>
                        @endif
                    </div>

                    <flux:input wire:model="lineNotes" label="Notas" maxlength="255" />

                    <div class="flex justify-end">
                        <flux:button variant="primary" wire:click="addLine" data-test="add-line">
                            Agregar línea
                        </flux:button>
                    </div>
                @endif
            </div>
        @endif

        <flux:table>
            <flux:table.columns>
                <flux:table.column>SKU</flux:table.column>
                <flux:table.column>Producto</flux:table.column>
                <flux:table.column>Almacén</flux:table.column>
                @if ($movement->type !== 'transfer')
                    <flux:table.column>Dirección</flux:table.column>
                @endif
                <flux:table.column>Cantidad</flux:table.column>
                @if (in_array($movement->type, ['inbound', 'initial_load'], true))
                    <flux:table.column>Costo unit.</flux:table.column>
                @endif
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->lines as $line)
                    <flux:table.row :key="$line->id">
                        <flux:table.cell variant="strong">
                            <code class="text-xs font-mono">{{ $line->sku->internal_code }}</code>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span class="text-sm">{{ $line->sku->product?->name }}</span>
                                @if ($line->sku->variant_name)
                                    <span class="text-xs text-zinc-500">{{ $line->sku->variant_name }}</span>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm">
                            @if ($movement->type === 'transfer')
                                {{ $movement->originWarehouse?->code }} → {{ $movement->destinationWarehouse?->code }}
                            @else
                                {{ $line->warehouse->code }}
                            @endif
                        </flux:table.cell>
                        @if ($movement->type !== 'transfer')
                            <flux:table.cell>
                                @if ($line->direction === 'in')
                                    <flux:badge color="green" size="sm" inset="top bottom">Entrada</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm" inset="top bottom">Salida</flux:badge>
                                @endif
                            </flux:table.cell>
                        @endif
                        <flux:table.cell variant="strong">{{ number_format((float) $line->quantity, 2) }}</flux:table.cell>
                        @if (in_array($movement->type, ['inbound', 'initial_load'], true))
                            <flux:table.cell class="text-sm text-zinc-500">
                                {{ $line->unit_cost !== null ? 'S/ '.number_format((float) $line->unit_cost, 2) : '—' }}
                            </flux:table.cell>
                        @endif
                        <flux:table.cell>
                            @if ($movement->status === 'draft' && auth()->user()->can('update', $movement))
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="trash"
                                    wire:click="removeLine({{ $line->id }})"
                                    inset="top bottom"
                                    class="text-red-600 hover:text-red-700"
                                />
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center text-zinc-500 py-8">
                            El movimiento aún no tiene líneas.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="movement-void" class="md:w-[28rem]">
        <form wire:submit="voidMovement" class="space-y-5">
            <div>
                <flux:heading size="lg">Anular movimiento</flux:heading>
                <flux:text class="mt-2">
                    Al anular, el impacto en stock se revierte. Queda el registro histórico.
                </flux:text>
            </div>

            <flux:textarea
                wire:model="voidReason"
                label="Motivo de la anulación"
                placeholder="Ej. Error de conteo, devolución cancelada..."
                rows="3"
                required
            />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger" data-test="confirm-void">
                    Anular
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- WIZARD: Crear producto nuevo en 3 pasos --}}
    <flux:modal name="product-wizard" class="md:w-[48rem]">
        <div class="space-y-5">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">Crear producto nuevo</flux:heading>
                <flux:badge size="sm" inset="top bottom">Paso {{ $wizardStep }} de 3</flux:badge>
            </div>

            @if ($wizardStep === 1)
                {{-- Paso 1: Datos del producto --}}
                <div class="space-y-4">
                    <flux:input
                        wire:model="wizName"
                        label="Nombre del producto"
                        placeholder="Silla gerencial Milano"
                        required
                        maxlength="200"
                    />

                    <div class="grid grid-cols-2 gap-4">
                        <flux:select wire:model.live="wizFamilyId" label="Familia" required>
                            <flux:select.option value="">Elige una familia...</flux:select.option>
                            @foreach ($this->wizardFamilies as $fam)
                                <flux:select.option :value="$fam->id">{{ $fam->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model="wizSubfamilyId" label="Subfamilia" :disabled="! $wizFamilyId">
                            <flux:select.option value="">Pendiente (por defecto)</flux:select.option>
                            @foreach ($this->wizardSubfamilies as $sf)
                                <flux:select.option :value="$sf->id">{{ $sf->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="wizBrand" label="Marca" placeholder="ErgoMax" maxlength="100" />

                        <flux:select wire:model="wizUnitOfMeasure" label="Unidad de medida">
                            @foreach (static::UNITS as $value => $label)
                                <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <flux:textarea wire:model="wizDescription" label="Descripción" rows="2" />

                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                        <flux:switch wire:model.live="wizHasVariants" label="Este producto tiene varias variantes (SKUs)" />
                        <flux:text class="mt-1 text-xs text-zinc-500">
                            Actívalo si hay distintos colores, materiales o medidas del mismo producto.
                        </flux:text>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancelar</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" wire:click="wizardNext" data-test="wizard-next-1">
                        Siguiente →
                    </flux:button>
                </div>
            @elseif ($wizardStep === 2)
                {{-- Paso 2: Variantes / SKUs --}}
                <div class="space-y-4">
                    @if (count($wizSkus) > 0)
                        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 divide-y divide-zinc-200 dark:divide-zinc-700">
                            <div class="p-3 text-xs font-semibold uppercase text-zinc-500">
                                Variantes agregadas ({{ count($wizSkus) }})
                            </div>
                            @foreach ($wizSkus as $i => $sku)
                                <div class="flex items-center justify-between p-3">
                                    <div class="flex flex-col">
                                        <span class="text-sm">{{ $sku['variant_name'] ?? '(sin nombre)' }}</span>
                                        <span class="text-xs text-zinc-500">
                                            @foreach ($sku['attributes'] as $code => $val)
                                                {{ $code }}: {{ $val }}{{ ! $loop->last ? ' · ' : '' }}
                                            @endforeach
                                        </span>
                                    </div>
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="trash"
                                        wire:click="wizardRemoveVariant({{ $i }})"
                                        inset="top bottom"
                                        class="text-red-600 hover:text-red-700"
                                    />
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 space-y-4">
                        <flux:heading size="sm">
                            {{ $wizHasVariants ? 'Nueva variante' : 'Datos del SKU' }}
                        </flux:heading>

                        @if ($wizHasVariants)
                            <flux:input
                                wire:model="wizDraftVariantName"
                                label="Nombre de la variante"
                                placeholder="Negro / Cuero"
                                maxlength="150"
                            />
                        @endif

                        @if ($this->wizardFamilyAttributes->isNotEmpty())
                            <div class="grid grid-cols-2 gap-3">
                                @foreach ($this->wizardFamilyAttributes as $attr)
                                    <div wire:key="wiz-attr-{{ $attr->code }}">
                                        @if ($attr->type === 'list')
                                            <flux:select wire:model="wizDraftAttributes.{{ $attr->code }}" :label="$attr->name">
                                                <flux:select.option value="">—</flux:select.option>
                                                @foreach ($attr->options ?? [] as $opt)
                                                    <flux:select.option :value="$opt">{{ $opt }}</flux:select.option>
                                                @endforeach
                                            </flux:select>
                                        @else
                                            <flux:input wire:model="wizDraftAttributes.{{ $attr->code }}" :label="$attr->name" />
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="grid grid-cols-2 gap-3">
                            <flux:input
                                wire:model="wizDraftSalePrice"
                                type="number"
                                step="0.01"
                                min="0"
                                label="Precio de venta (S/)"
                            />
                            <flux:input
                                wire:model="wizDraftPurchasePrice"
                                type="number"
                                step="0.01"
                                min="0"
                                label="Precio de compra (S/)"
                            />
                        </div>

                        @if ($wizHasVariants)
                            <div class="flex justify-end">
                                <flux:button variant="filled" icon="plus" wire:click="wizardAddVariant" data-test="wizard-add-variant">
                                    Agregar esta variante
                                </flux:button>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex justify-between gap-2 pt-2">
                    <flux:button variant="ghost" wire:click="wizardBack">← Atrás</flux:button>
                    <div class="flex gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost">Cancelar</flux:button>
                        </flux:modal.close>
                        <flux:button variant="primary" wire:click="wizardNext" data-test="wizard-next-2">
                            Siguiente →
                        </flux:button>
                    </div>
                </div>
            @else
                {{-- Paso 3: Cantidades por SKU --}}
                <div class="space-y-4">
                    <flux:text>
                        ¿Cuánto contaste/recibiste de cada variante? Al confirmar se crea el producto y
                        se agrega una línea por cada variante a este movimiento.
                    </flux:text>

                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($wizSkus as $i => $sku)
                            <div class="flex items-center gap-3 p-3">
                                <div class="flex-1 flex flex-col">
                                    <span class="text-sm font-medium">{{ $wizName }}</span>
                                    <span class="text-xs text-zinc-500">
                                        {{ $sku['variant_name'] ?? '(sin variante)' }}
                                        @foreach ($sku['attributes'] as $code => $val)
                                            · {{ $code }}: {{ $val }}
                                        @endforeach
                                    </span>
                                </div>
                                <div class="w-32">
                                    <flux:input
                                        wire:model="wizQuantities.{{ $i }}"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        placeholder="Cantidad"
                                    />
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-between gap-2 pt-2">
                    <flux:button variant="ghost" wire:click="wizardBack">← Atrás</flux:button>
                    <div class="flex gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost">Cancelar</flux:button>
                        </flux:modal.close>
                        <flux:button variant="primary" wire:click="wizardSave" data-test="wizard-save">
                            Crear y agregar al movimiento
                        </flux:button>
                    </div>
                </div>
            @endif
        </div>
    </flux:modal>
</section>
