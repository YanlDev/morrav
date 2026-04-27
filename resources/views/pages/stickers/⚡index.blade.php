<?php

use App\Models\Family;
use App\Models\Sku;
use App\Models\Subfamily;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Impresión de etiquetas')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'f')]
    public string $familyFilter = '';

    #[Url(as: 'sf')]
    public string $subfamilyFilter = '';

    /**
     * Cola de impresión: sku_id => copias.
     *
     * @var array<int, int>
     */
    public array $queue = [];

    // Estado del modal "Agregar a cola"
    public ?int $addingSkuId = null;

    public int $addingCopies = 1;

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
        return Sku::query()
            ->with([
                'product:id,internal_code,name,family_id,subfamily_id',
                'product.family:id,name',
                'product.subfamily:id,name',
            ])
            ->where('status', '!=', 'discontinued')
            ->when($this->search !== '', function ($query) {
                $term = '%'.mb_strtolower($this->search).'%';
                $query->where(fn ($q) => $q
                    ->whereRaw('LOWER(internal_code) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(variant_name) LIKE ?', [$term])
                    ->orWhereHas('product', fn ($p) => $p
                        ->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(internal_code) LIKE ?', [$term])));
            })
            ->when($this->familyFilter !== '', fn ($q) => $q
                ->whereHas('product', fn ($p) => $p->where('family_id', $this->familyFilter)))
            ->when($this->subfamilyFilter !== '', fn ($q) => $q
                ->whereHas('product', fn ($p) => $p->where('subfamily_id', $this->subfamilyFilter)))
            ->orderBy('internal_code')
            ->paginate(20);
    }

    /**
     * Detalle de la cola para mostrar en el panel lateral.
     *
     * @return array<int, array{sku_id: int, code: string, name: string, variant: ?string, copies: int}>
     */
    #[Computed]
    public function queueItems(): array
    {
        if ($this->queue === []) {
            return [];
        }

        $skus = Sku::with('product:id,name')
            ->whereIn('id', array_keys($this->queue))
            ->get()
            ->keyBy('id');

        $items = [];

        foreach ($this->queue as $skuId => $copies) {
            $sku = $skus->get($skuId);
            if (! $sku) {
                continue;
            }
            $items[] = [
                'sku_id' => (int) $skuId,
                'code' => $sku->internal_code,
                'name' => $sku->product?->name ?? '—',
                'variant' => $sku->variant_name,
                'copies' => (int) $copies,
            ];
        }

        return $items;
    }

    #[Computed]
    public function queueTotal(): int
    {
        return array_sum($this->queue);
    }

    /**
     * URL del endpoint de impresión con el estado actual de la cola.
     */
    #[Computed]
    public function printUrl(): ?string
    {
        if ($this->queue === []) {
            return null;
        }

        $items = collect($this->queue)
            ->filter(fn ($copies) => $copies > 0)
            ->map(fn ($copies, $skuId) => $skuId.'x'.$copies)
            ->implode(',');

        return route('stickers.print', ['items' => $items]);
    }

    public function openAdd(int $skuId): void
    {
        $this->addingSkuId = $skuId;
        $this->addingCopies = $this->queue[$skuId] ?? 1;
        Flux::modal('sticker-add')->show();
    }

    #[Computed]
    public function addingSku(): ?Sku
    {
        return $this->addingSkuId
            ? Sku::with('product:id,name')->find($this->addingSkuId)
            : null;
    }

    public function addToQueue(): void
    {
        if ($this->addingSkuId === null) {
            return;
        }

        $this->validate([
            'addingCopies' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $this->queue[$this->addingSkuId] = (int) $this->addingCopies;

        Flux::modal('sticker-add')->close();
        Flux::toast(variant: 'success', text: 'Agregado a la cola.');

        $this->addingSkuId = null;
        $this->addingCopies = 1;
    }

    public function quickPick(int $copies): void
    {
        $this->addingCopies = max(1, $copies);
    }

    public function incrementInQueue(int $skuId): void
    {
        if (! isset($this->queue[$skuId])) {
            return;
        }

        $this->queue[$skuId] = min(500, $this->queue[$skuId] + 1);
    }

    public function decrementInQueue(int $skuId): void
    {
        if (! isset($this->queue[$skuId])) {
            return;
        }

        $new = $this->queue[$skuId] - 1;

        if ($new <= 0) {
            unset($this->queue[$skuId]);

            return;
        }

        $this->queue[$skuId] = $new;
    }

    public function removeFromQueue(int $skuId): void
    {
        unset($this->queue[$skuId]);
    }

    public function clearQueue(): void
    {
        $this->queue = [];
        Flux::toast(variant: 'success', text: 'Cola vaciada.');
    }
}; ?>

<section class="w-full p-6">
    <div class="flex flex-col gap-6">
        <div>
            <flux:heading size="xl">Impresión de etiquetas</flux:heading>
            <flux:text>Busca productos, agrega las cantidades a la cola e imprime todo en un solo lote.</flux:text>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_24rem]">
            {{-- Panel izquierdo: búsqueda + lista de SKUs --}}
            <div class="flex flex-col gap-4">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        icon="magnifying-glass"
                        placeholder="Buscar SKU, producto o variante..."
                        class="md:col-span-3"
                    />

                    <flux:select wire:model.live="familyFilter">
                        <flux:select.option value="">Todas las familias</flux:select.option>
                        @foreach ($this->families as $fam)
                            <flux:select.option :value="$fam->id">{{ $fam->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="subfamilyFilter" :disabled="! $familyFilter">
                        <flux:select.option value="">Todas las subfamilias</flux:select.option>
                        @foreach ($this->subfamilies as $sf)
                            <flux:select.option :value="$sf->id">{{ $sf->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:table :paginate="$this->skus">
                    <flux:table.columns>
                        <flux:table.column>Código</flux:table.column>
                        <flux:table.column>Producto</flux:table.column>
                        <flux:table.column>Familia</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($this->skus as $sku)
                            @php $inQueue = isset($queue[$sku->id]); @endphp
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
                                <flux:table.cell class="text-sm text-zinc-500">
                                    <div class="flex flex-col">
                                        <span>{{ $sku->product?->family?->name ?? '—' }}</span>
                                        @if ($sku->product?->subfamily)
                                            <span class="text-xs">{{ $sku->product->subfamily->name }}</span>
                                        @endif
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($inQueue)
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            icon="check"
                                            wire:click="openAdd({{ $sku->id }})"
                                            inset="top bottom"
                                        >
                                            En cola ({{ $queue[$sku->id] }})
                                        </flux:button>
                                    @else
                                        <flux:button
                                            variant="primary"
                                            size="sm"
                                            icon="plus"
                                            wire:click="openAdd({{ $sku->id }})"
                                            inset="top bottom"
                                        >
                                            Agregar
                                        </flux:button>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="text-center text-zinc-500 py-8">
                                    No se encontraron SKUs.
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>

            {{-- Panel derecho: cola de impresión --}}
            <aside class="sticky top-6 self-start rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading size="lg">
                        Cola
                        @if (count($this->queueItems) > 0)
                            <span class="ml-1 text-sm font-normal text-zinc-500">
                                ({{ count($this->queueItems) }} SKU / {{ $this->queueTotal }} etiq.)
                            </span>
                        @endif
                    </flux:heading>
                    @if (count($this->queueItems) > 0)
                        <flux:button
                            variant="ghost"
                            size="sm"
                            wire:click="clearQueue"
                            wire:confirm="¿Vaciar la cola?"
                        >
                            Vaciar
                        </flux:button>
                    @endif
                </div>

                @if (count($this->queueItems) === 0)
                    <div class="p-8 text-center text-sm text-zinc-500">
                        Selecciona productos de la lista para agregarlos aquí.
                    </div>
                @else
                    <div class="max-h-[32rem] overflow-y-auto divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($this->queueItems as $item)
                            <div class="flex items-center gap-2 p-3" wire:key="queue-{{ $item['sku_id'] }}">
                                <div class="flex flex-col min-w-0 flex-1">
                                    <code class="text-xs font-mono text-zinc-500">{{ $item['code'] }}</code>
                                    <span class="text-sm truncate">{{ $item['name'] }}</span>
                                    @if ($item['variant'])
                                        <span class="text-xs text-zinc-500 truncate">{{ $item['variant'] }}</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="minus"
                                        wire:click="decrementInQueue({{ $item['sku_id'] }})"
                                        inset="top bottom"
                                    />
                                    <span class="w-8 text-center text-sm font-semibold">{{ $item['copies'] }}</span>
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="plus"
                                        wire:click="incrementInQueue({{ $item['sku_id'] }})"
                                        inset="top bottom"
                                    />
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="x-mark"
                                        wire:click="removeFromQueue({{ $item['sku_id'] }})"
                                        inset="top bottom"
                                        class="text-red-600 hover:text-red-700"
                                    />
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:button
                            variant="primary"
                            icon="printer"
                            :href="$this->printUrl"
                            target="_blank"
                            class="w-full"
                            data-test="print-queue"
                        >
                            Imprimir {{ $this->queueTotal }} etiqueta(s)
                        </flux:button>
                    </div>
                @endif
            </aside>
        </div>
    </div>

    {{-- Modal: agregar a cola --}}
    <flux:modal name="sticker-add" class="md:w-[28rem]">
        @if ($this->addingSku)
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">Agregar a la cola</flux:heading>
                    <flux:text class="mt-1">
                        <code class="font-mono text-xs">{{ $this->addingSku->internal_code }}</code>
                        — {{ $this->addingSku->product?->name }}
                        @if ($this->addingSku->variant_name)
                            <span class="text-zinc-500">· {{ $this->addingSku->variant_name }}</span>
                        @endif
                    </flux:text>
                </div>

                <div class="space-y-2">
                    <flux:input
                        wire:model="addingCopies"
                        type="number"
                        min="1"
                        max="500"
                        label="¿Cuántas etiquetas?"
                    />
                    <div class="flex flex-wrap gap-2">
                        @foreach ([1, 5, 10, 20, 50] as $n)
                            <flux:button
                                type="button"
                                variant="ghost"
                                size="sm"
                                wire:click="quickPick({{ $n }})"
                            >
                                {{ $n }}
                            </flux:button>
                        @endforeach
                    </div>
                </div>

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancelar</flux:button>
                    </flux:modal.close>
                    <flux:button
                        variant="primary"
                        wire:click="addToQueue"
                        data-test="confirm-add-to-queue"
                    >
                        @if (isset($queue[$addingSkuId ?? -1]))
                            Actualizar
                        @else
                            Agregar
                        @endif
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</section>
