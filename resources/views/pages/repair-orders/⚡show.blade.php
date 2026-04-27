<?php

use App\Models\Movement;
use App\Models\RepairOrder;
use App\Models\Warehouse;
use App\Services\Repair\RepairOrderService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Orden de reparación')] class extends Component {
    public RepairOrder $repairOrder;

    /**
     * Datos del cierre por línea. Estructura:
     * `[ line_id => ['quantity_repaired' => float|null, 'quantity_scrapped' => float|null, 'destination_warehouse_id' => int|null] ]`.
     *
     * @var array<int, array{quantity_repaired: float|null, quantity_scrapped: float|null, destination_warehouse_id: int|null}>
     */
    public array $closure = [];

    public string $cancelReason = '';

    public function mount(RepairOrder $repairOrder): void
    {
        $this->authorize('view', $repairOrder);
        $this->repairOrder = $repairOrder->load(['lines.sku.product', 'lines.destinationWarehouse', 'opener', 'closer']);
    }

    /**
     * Almacenes elegibles como destino al cerrar (todos menos taller / merma / tránsito).
     */
    #[Computed]
    public function destinationOptions()
    {
        return Warehouse::query()
            ->active()
            ->whereNotIn('type', ['workshop', 'scrap', 'transit'])
            ->orderBy('code')
            ->get();
    }

    /**
     * Movimientos generados por esta orden (al cerrar con resultado).
     */
    #[Computed]
    public function relatedMovements()
    {
        return Movement::query()
            ->with(['lines.sku:id,internal_code,variant_name', 'lines.warehouse:id,code', 'creator:id,name'])
            ->where('reference_type', 'repair_order')
            ->where('reference_id', $this->repairOrder->id)
            ->orderBy('id')
            ->get();
    }

    public function openClose(): void
    {
        $this->authorize('close', $this->repairOrder);
        abort_unless($this->repairOrder->isOpen(), 403);

        $this->closure = [];

        foreach ($this->repairOrder->lines as $line) {
            $this->closure[$line->id] = [
                'quantity_repaired' => (float) $line->quantity_claimed,
                'quantity_scrapped' => 0.0,
                'destination_warehouse_id' => null,
            ];
        }

        Flux::modal('repair-close')->show();
    }

    public function close(): void
    {
        $this->authorize('close', $this->repairOrder);

        $closures = [];

        foreach ($this->repairOrder->lines as $line) {
            $entry = $this->closure[$line->id] ?? null;

            if (! $entry) {
                continue;
            }

            $closures[] = [
                'line_id' => $line->id,
                'quantity_repaired' => (float) ($entry['quantity_repaired'] ?? 0),
                'quantity_scrapped' => (float) ($entry['quantity_scrapped'] ?? 0),
                'destination_warehouse_id' => $entry['destination_warehouse_id']
                    ? (int) $entry['destination_warehouse_id']
                    : null,
            ];
        }

        try {
            app(RepairOrderService::class)->close(
                order: $this->repairOrder,
                user: Auth::user(),
                closuresData: $closures,
            );
        } catch (\RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::modal('repair-close')->close();
        Flux::toast(variant: 'success', text: "Orden {$this->repairOrder->code} cerrada. Stock actualizado.");

        $this->repairOrder = $this->repairOrder->fresh(['lines.sku.product', 'lines.destinationWarehouse', 'opener', 'closer']);
        unset($this->relatedMovements);
    }

    public function openCancel(): void
    {
        $this->authorize('cancel', $this->repairOrder);
        $this->cancelReason = '';
        Flux::modal('repair-cancel')->show();
    }

    public function cancel(): void
    {
        $this->authorize('cancel', $this->repairOrder);

        try {
            app(RepairOrderService::class)->cancel(
                order: $this->repairOrder,
                user: Auth::user(),
                reason: $this->cancelReason ?: null,
            );
        } catch (\RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::modal('repair-cancel')->close();
        Flux::toast(variant: 'success', text: 'Orden cancelada. Las unidades vuelven a estar disponibles para otra orden.');

        $this->repairOrder = $this->repairOrder->fresh(['lines.sku.product', 'lines.destinationWarehouse', 'opener', 'closer']);
    }
}; ?>

<section class="w-full p-6">
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2 text-sm text-zinc-500">
                <flux:link :href="route('repair-orders.index')" wire:navigate>Órdenes de reparación</flux:link>
                <span>/</span>
                <span>{{ $repairOrder->code }}</span>
            </div>

            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="xl">{{ $repairOrder->code }}</flux:heading>
                    <flux:text class="mt-1">
                        Abierta por <strong>{{ $repairOrder->opener?->name ?? '—' }}</strong>
                        el {{ $repairOrder->created_at?->format('d/m/Y H:i') }}.
                        @if ($repairOrder->isClosed())
                            Cerrada por <strong>{{ $repairOrder->closer?->name ?? '—' }}</strong>
                            el {{ $repairOrder->closed_at?->format('d/m/Y H:i') }}.
                        @endif
                    </flux:text>
                </div>

                <div class="flex gap-2">
                    @if ($repairOrder->isOpen())
                        @can('close', $repairOrder)
                            <flux:button variant="primary" icon="check-circle" wire:click="openClose" data-test="open-close">
                                Cerrar con resultado
                            </flux:button>
                        @endcan
                        @can('cancel', $repairOrder)
                            <flux:button variant="ghost" icon="x-circle" wire:click="openCancel" data-test="open-cancel">
                                Cancelar
                            </flux:button>
                        @endcan
                    @else
                        @if ($repairOrder->outcome === 'completed')
                            <flux:badge color="green" size="lg">Cerrada · completada</flux:badge>
                        @else
                            <flux:badge color="zinc" size="lg">Cerrada · cancelada</flux:badge>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        @if ($repairOrder->notes)
            <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
                <flux:heading size="sm" class="mb-2">Observaciones</flux:heading>
                <div class="whitespace-pre-wrap">{{ $repairOrder->notes }}</div>
            </div>
        @endif

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="sm">Líneas</flux:heading>

            <flux:table class="mt-3">
                <flux:table.columns>
                    <flux:table.column>SKU</flux:table.column>
                    <flux:table.column>Producto</flux:table.column>
                    <flux:table.column align="end">Reclamadas</flux:table.column>
                    <flux:table.column align="end">Reparadas</flux:table.column>
                    <flux:table.column align="end">Merma</flux:table.column>
                    <flux:table.column>Destino</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($repairOrder->lines as $line)
                        <flux:table.row :key="$line->id">
                            <flux:table.cell variant="strong">
                                <code class="text-xs font-mono">{{ $line->sku?->internal_code }}</code>
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $line->sku?->product?->name }}
                                @if ($line->sku?->variant_name)
                                    <span class="text-zinc-500"> · {{ $line->sku->variant_name }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end" variant="strong">
                                {{ rtrim(rtrim(number_format((float) $line->quantity_claimed, 2), '0'), '.') }}
                            </flux:table.cell>
                            <flux:table.cell align="end" class="text-green-700 dark:text-green-400">
                                {{ $line->quantity_repaired !== null ? rtrim(rtrim(number_format((float) $line->quantity_repaired, 2), '0'), '.') : '—' }}
                            </flux:table.cell>
                            <flux:table.cell align="end" class="text-red-600 dark:text-red-400">
                                {{ $line->quantity_scrapped !== null ? rtrim(rtrim(number_format((float) $line->quantity_scrapped, 2), '0'), '.') : '—' }}
                            </flux:table.cell>
                            <flux:table.cell class="text-xs text-zinc-500">
                                {{ $line->destinationWarehouse?->code ?? '—' }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>

        @if ($this->relatedMovements->isNotEmpty())
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="sm">Movimientos generados</flux:heading>
                <flux:table class="mt-3">
                    <flux:table.columns>
                        <flux:table.column>N°</flux:table.column>
                        <flux:table.column>Origen → Destino</flux:table.column>
                        <flux:table.column>Motivo</flux:table.column>
                        <flux:table.column align="end">Unidades</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->relatedMovements as $mov)
                            <flux:table.row :key="$mov->id">
                                <flux:table.cell variant="strong">
                                    <flux:link :href="route('movements.show', $mov)" wire:navigate>
                                        <code class="text-xs font-mono">{{ $mov->number }}</code>
                                    </flux:link>
                                </flux:table.cell>
                                <flux:table.cell class="text-sm text-zinc-500">
                                    {{ $mov->originWarehouse?->code ?? '—' }} → {{ $mov->destinationWarehouse?->code ?? '—' }}
                                </flux:table.cell>
                                <flux:table.cell class="text-xs text-zinc-500">
                                    {{ $mov->reason }}
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    {{ rtrim(rtrim(number_format((float) $mov->lines->where('direction', 'in')->sum('quantity'), 2), '0'), '.') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </div>

    <flux:modal name="repair-close" class="md:w-[48rem]">
        <form wire:submit="close" class="space-y-5">
            <div>
                <flux:heading size="lg">Cerrar orden con resultado</flux:heading>
                <flux:text class="mt-1">
                    Para cada línea declara cuántas unidades quedaron reparadas y cuántas
                    se descartaron. Las reparadas se devuelven al almacén que elijas; las descartadas
                    se mueven a <code class="text-xs font-mono">MERMA</code>.
                </flux:text>
            </div>

            <div class="space-y-4">
                @foreach ($repairOrder->lines as $line)
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4" wire:key="closure-{{ $line->id }}">
                        <div class="text-sm">
                            <code class="text-xs font-mono">{{ $line->sku?->internal_code }}</code>
                            · {{ $line->sku?->product?->name }}
                            @if ($line->sku?->variant_name)
                                / {{ $line->sku->variant_name }}
                            @endif
                            <span class="text-zinc-500">
                                · {{ rtrim(rtrim(number_format((float) $line->quantity_claimed, 2), '0'), '.') }} reclamadas
                            </span>
                        </div>

                        <div class="mt-3 grid grid-cols-3 gap-3">
                            <flux:input
                                wire:model.live="closure.{{ $line->id }}.quantity_repaired"
                                type="number"
                                step="0.01"
                                min="0"
                                label="Reparadas"
                            />
                            <flux:input
                                wire:model.live="closure.{{ $line->id }}.quantity_scrapped"
                                type="number"
                                step="0.01"
                                min="0"
                                label="Merma"
                            />
                            <flux:select wire:model="closure.{{ $line->id }}.destination_warehouse_id" label="Destino reparadas">
                                <flux:select.option value="">—</flux:select.option>
                                @foreach ($this->destinationOptions as $wh)
                                    <flux:select.option :value="$wh->id">{{ $wh->code }} — {{ $wh->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="confirm-close-repair">
                    Confirmar cierre
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="repair-cancel" class="md:w-[32rem]">
        <form wire:submit="cancel" class="space-y-5">
            <div>
                <flux:heading size="lg">Cancelar orden</flux:heading>
                <flux:text class="mt-1">
                    Las unidades reclamadas vuelven a estar disponibles para otra orden. No se generan movimientos.
                </flux:text>
            </div>

            <flux:textarea
                wire:model="cancelReason"
                label="Motivo (opcional)"
                rows="3"
                maxlength="255"
                placeholder="Por qué se cancela la orden"
            />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Volver</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger" data-test="confirm-cancel-repair">
                    Cancelar orden
                </flux:button>
            </div>
        </form>
    </flux:modal>
</section>
