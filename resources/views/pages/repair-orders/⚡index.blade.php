<?php

use App\Models\RepairOrder;
use App\Models\Sku;
use App\Models\Warehouse;
use App\Services\Repair\RepairOrderService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Órdenes de reparación')] class extends Component {
    use WithPagination;

    public const STATUSES = [
        'open' => 'Abiertas',
        'closed' => 'Cerradas',
    ];

    #[Url(as: 's')]
    public string $statusFilter = '';

    public string $newNotes = '';

    /**
     * Líneas en el modal de creación. Cada entrada:
     * `['sku_id' => int|null, 'quantity_claimed' => float|null]`.
     *
     * @var array<int, array{sku_id: int|null, quantity_claimed: float|null}>
     */
    public array $newLines = [];

    public function mount(): void
    {
        $this->authorize('viewAny', RepairOrder::class);
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function orders()
    {
        return RepairOrder::query()
            ->withCount('lines')
            ->with(['opener:id,name', 'closer:id,name'])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id')
            ->paginate(20);
    }

    /**
     * SKUs con stock disponible en taller (no reclamados por otra orden abierta).
     */
    #[Computed]
    public function repairableSkus()
    {
        return app(RepairOrderService::class)->repairableSkus();
    }

    /**
     * Resumen de stock pendiente en taller (sin orden abierta) para mostrar
     * un aviso en la cabecera.
     *
     * @return array{units: float, skus: int}
     */
    #[Computed]
    public function pendingStats(): array
    {
        return app(RepairOrderService::class)->pendingInWorkshopStats();
    }

    public function openCreate(): void
    {
        $this->authorize('create', RepairOrder::class);
        $this->resetCreateForm();
        $this->addLine();
        Flux::modal('repair-new')->show();
    }

    public function addLine(): void
    {
        $this->newLines[] = ['sku_id' => null, 'quantity_claimed' => null];
    }

    public function removeLine(int $index): void
    {
        unset($this->newLines[$index]);
        $this->newLines = array_values($this->newLines);

        if ($this->newLines === []) {
            $this->addLine();
        }
    }

    public function create(): void
    {
        $this->authorize('create', RepairOrder::class);

        $cleaned = collect($this->newLines)
            ->filter(fn ($l) => ! empty($l['sku_id']) && (float) ($l['quantity_claimed'] ?? 0) > 0)
            ->map(fn ($l) => [
                'sku_id' => (int) $l['sku_id'],
                'quantity_claimed' => (float) $l['quantity_claimed'],
            ])
            ->values()
            ->all();

        if ($cleaned === []) {
            Flux::toast(variant: 'danger', text: 'Agrega al menos una línea con SKU y cantidad.');

            return;
        }

        try {
            $order = app(RepairOrderService::class)->open(
                user: Auth::user(),
                linesData: $cleaned,
                notes: $this->newNotes ?: null,
            );
        } catch (\RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::modal('repair-new')->close();
        Flux::toast(variant: 'success', text: "Orden {$order->code} creada.");
        $this->resetCreateForm();
        $this->redirectRoute('repair-orders.show', $order, navigate: true);
    }

    private function resetCreateForm(): void
    {
        $this->reset(['newNotes', 'newLines']);
        $this->resetValidation();
    }
}; ?>

<section class="w-full p-6">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Órdenes de reparación</flux:heading>
                <flux:text class="mt-1">
                    Trabajo en taller. Las unidades reclamadas siguen en
                    <code class="text-xs font-mono">TALLER</code> hasta que la orden se cierra.
                </flux:text>
            </div>

            @can('create', App\Models\RepairOrder::class)
                <flux:button variant="primary" icon="plus" wire:click="openCreate" data-test="new-repair-order">
                    Nueva orden
                </flux:button>
            @endcan
        </div>

        @if ($this->pendingStats['units'] > 0)
            <div class="rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-900/40 dark:bg-amber-950/30 p-4 flex items-start gap-3" data-test="pending-banner">
                <flux:icon.exclamation-triangle class="size-5 text-amber-600 shrink-0 mt-0.5" />
                <div class="flex-1">
                    <flux:heading size="sm">Hay stock en taller esperando una orden</flux:heading>
                    <flux:text class="mt-0.5">
                        {{ rtrim(rtrim(number_format($this->pendingStats['units'], 2), '0'), '.') }}
                        unidades en {{ $this->pendingStats['skus'] }}
                        {{ $this->pendingStats['skus'] === 1 ? 'variante' : 'variantes' }}.
                        Abrí una orden para empezar a repararlas.
                    </flux:text>
                </div>
                @can('create', App\Models\RepairOrder::class)
                    <flux:button variant="primary" size="sm" wire:click="openCreate">
                        Abrir orden
                    </flux:button>
                @endcan
            </div>
        @endif

        <flux:select wire:model.live="statusFilter" placeholder="Todos los estados" class="sm:w-48">
            <flux:select.option value="">Todos los estados</flux:select.option>
            @foreach (static::STATUSES as $v => $l)
                <flux:select.option :value="$v">{{ $l }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:table :paginate="$this->orders">
            <flux:table.columns>
                <flux:table.column>N°</flux:table.column>
                <flux:table.column>Fecha</flux:table.column>
                <flux:table.column>Líneas</flux:table.column>
                <flux:table.column>Abierta por</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->orders as $order)
                    <flux:table.row :key="$order->id">
                        <flux:table.cell variant="strong">
                            <code class="text-xs font-mono">{{ $order->code }}</code>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm">
                            {{ $order->created_at?->format('d/m/Y H:i') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge color="zinc" size="sm" inset="top bottom">
                                {{ $order->lines_count }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500">
                            {{ $order->opener?->name ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($order->status === 'open')
                                <flux:badge color="amber" size="sm" inset="top bottom">Abierta</flux:badge>
                            @elseif ($order->outcome === 'completed')
                                <flux:badge color="green" size="sm" inset="top bottom">Cerrada · completada</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm" inset="top bottom">Cerrada · cancelada</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="eye"
                                :href="route('repair-orders.show', $order)"
                                wire:navigate
                                inset="top bottom"
                            />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-500 py-8">
                            @if ($this->pendingStats['units'] > 0)
                                Aún no hay órdenes abiertas. Hay stock en taller esperando — abrí la primera con el botón de arriba.
                            @else
                                Aún no hay órdenes de reparación. Cuando reportes unidades dañadas y abras una orden, van a aparecer acá.
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="repair-new" class="md:w-[44rem]">
        <form wire:submit="create" class="space-y-5">
            <div>
                <flux:heading size="lg">Nueva orden de reparación</flux:heading>
                <flux:text class="mt-1">
                    Solo aparecen variantes con stock en taller no reclamado por otra orden abierta.
                </flux:text>
            </div>

            @if ($this->repairableSkus->isEmpty())
                <div class="rounded-lg bg-amber-50 dark:bg-amber-950/30 p-4 text-sm text-amber-700 dark:text-amber-300">
                    No hay variantes con stock disponible en el taller. Reporta unidades dañadas desde la ficha del producto antes de abrir una orden.
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($newLines as $index => $line)
                        <div class="grid grid-cols-12 gap-2 items-end" wire:key="repair-line-{{ $index }}">
                            <div class="col-span-7">
                                <flux:select wire:model="newLines.{{ $index }}.sku_id" label="Variante" required>
                                    <flux:select.option value="">Selecciona la variante…</flux:select.option>
                                    @foreach ($this->repairableSkus as $sku)
                                        <flux:select.option :value="$sku->id">
                                            {{ $sku->internal_code }} · {{ $sku->product?->name }}{{ $sku->variant_name ? ' / '.$sku->variant_name : '' }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                            <div class="col-span-3">
                                <flux:input
                                    wire:model="newLines.{{ $index }}.quantity_claimed"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    label="Cantidad"
                                    required
                                />
                            </div>
                            <div class="col-span-2">
                                <flux:button
                                    type="button"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="removeLine({{ $index }})"
                                    inset="top bottom"
                                    class="text-red-600 hover:text-red-700"
                                />
                            </div>
                        </div>
                    @endforeach
                </div>

                <flux:button type="button" variant="ghost" icon="plus" wire:click="addLine" size="sm">
                    Agregar línea
                </flux:button>

                <flux:textarea
                    wire:model="newNotes"
                    label="Observaciones"
                    rows="2"
                    placeholder="Datos adicionales de la orden (opcional)"
                />
            @endif

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button
                    type="submit"
                    variant="primary"
                    :disabled="$this->repairableSkus->isEmpty()"
                    data-test="confirm-new-repair-order"
                >
                    Crear orden
                </flux:button>
            </div>
        </form>
    </flux:modal>
</section>
