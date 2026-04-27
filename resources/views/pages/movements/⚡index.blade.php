<?php

use App\Models\Movement;
use App\Models\Warehouse;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Movimientos')] class extends Component {
    use WithPagination;

    public const TYPES = [
        'inbound' => 'Ingreso',
        'outbound' => 'Salida',
        'transfer' => 'Traspaso',
        'adjustment' => 'Ajuste',
        'initial_load' => 'Carga inicial',
    ];

    public const STATUSES = [
        'draft' => 'Borrador',
        'confirmed' => 'Confirmado',
        'voided' => 'Anulado',
    ];

    #[Url(as: 't')]
    public string $typeFilter = '';

    #[Url(as: 's')]
    public string $statusFilter = '';

    #[Url(as: 'w')]
    public string $warehouseFilter = '';

    public string $newType = 'inbound';

    public string $newOccurredAt = '';

    public ?int $newOriginWarehouseId = null;

    public ?int $newDestinationWarehouseId = null;

    public string $newReason = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Movement::class);
        $this->newOccurredAt = now()->format('Y-m-d\TH:i');
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingWarehouseFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function movements()
    {
        return Movement::query()
            ->with(['originWarehouse:id,code,name', 'destinationWarehouse:id,code,name', 'creator:id,name'])
            ->withCount('lines')
            ->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->warehouseFilter, fn ($q) => $q->where(function ($inner) {
                $inner->where('origin_warehouse_id', $this->warehouseFilter)
                    ->orWhere('destination_warehouse_id', $this->warehouseFilter);
            }))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(20);
    }

    #[Computed]
    public function warehouses()
    {
        return Warehouse::query()->active()->orderBy('name')->get();
    }

    public function openCreate(): void
    {
        $this->authorize('create', Movement::class);
        $this->resetFormFields();
        Flux::modal('movement-new')->show();
    }

    public function create(): void
    {
        $this->authorize('create', Movement::class);
        $validated = $this->validate([
            'newType' => ['required', Rule::in(array_keys(self::TYPES))],
            'newOccurredAt' => ['required', 'date'],
            'newOriginWarehouseId' => [
                Rule::requiredIf(fn () => in_array($this->newType, ['outbound', 'adjustment', 'transfer'], true)),
                'nullable',
                'exists:warehouses,id',
            ],
            'newDestinationWarehouseId' => [
                Rule::requiredIf(fn () => in_array($this->newType, ['inbound', 'initial_load', 'transfer'], true)),
                'nullable',
                'exists:warehouses,id',
                'different:newOriginWarehouseId',
            ],
            'newReason' => ['nullable', 'string', 'max:255'],
        ], [
            'newDestinationWarehouseId.different' => 'El almacén destino debe ser distinto al origen.',
        ]);

        $movement = Movement::create([
            'number' => $this->generateNumber(),
            'type' => $validated['newType'],
            'occurred_at' => $validated['newOccurredAt'],
            'reason' => $validated['newReason'] ?? null,
            'origin_warehouse_id' => $validated['newOriginWarehouseId'] ?? null,
            'destination_warehouse_id' => $validated['newDestinationWarehouseId'] ?? null,
            'status' => 'draft',
            'created_by' => Auth::id(),
        ]);

        Flux::modal('movement-new')->close();
        Flux::toast(variant: 'success', text: 'Movimiento creado. Agrega las líneas.');

        $this->redirectRoute('movements.show', $movement, navigate: true);
    }

    private function generateNumber(): string
    {
        $lastId = Movement::max('id') ?? 0;

        return 'MOV-'.str_pad((string) ($lastId + 1), 6, '0', STR_PAD_LEFT);
    }

    private function resetFormFields(): void
    {
        $this->reset(['newOriginWarehouseId', 'newDestinationWarehouseId', 'newReason']);
        $this->newType = 'inbound';
        $this->newOccurredAt = now()->format('Y-m-d\TH:i');
        $this->resetValidation();
    }
}; ?>

<section class="w-full p-6">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Movimientos</flux:heading>
                <flux:text class="mt-1">Ingresos, salidas, ajustes y cargas iniciales de stock.</flux:text>
            </div>

            @can('create', App\Models\Movement::class)
                <flux:button variant="primary" icon="plus" wire:click="openCreate" data-test="new-movement">
                    Nuevo movimiento
                </flux:button>
            @endcan
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <flux:select wire:model.live="typeFilter" placeholder="Todos los tipos" class="sm:w-48">
                <flux:select.option value="">Todos los tipos</flux:select.option>
                @foreach (static::TYPES as $v => $l)
                    <flux:select.option :value="$v">{{ $l }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="statusFilter" placeholder="Todos los estados" class="sm:w-44">
                <flux:select.option value="">Todos los estados</flux:select.option>
                @foreach (static::STATUSES as $v => $l)
                    <flux:select.option :value="$v">{{ $l }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="warehouseFilter" placeholder="Todos los almacenes" class="sm:w-56">
                <flux:select.option value="">Todos los almacenes</flux:select.option>
                @foreach ($this->warehouses as $wh)
                    <flux:select.option :value="$wh->id">{{ $wh->code }} — {{ $wh->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:table :paginate="$this->movements">
            <flux:table.columns>
                <flux:table.column>N°</flux:table.column>
                <flux:table.column>Fecha</flux:table.column>
                <flux:table.column>Tipo</flux:table.column>
                <flux:table.column>Almacén</flux:table.column>
                <flux:table.column>Líneas</flux:table.column>
                <flux:table.column>Creado por</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->movements as $mov)
                    <flux:table.row :key="$mov->id">
                        <flux:table.cell variant="strong">
                            <code class="text-xs font-mono">{{ $mov->number }}</code>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm">
                            {{ $mov->occurred_at?->format('d/m/Y H:i') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" inset="top bottom">
                                {{ static::TYPES[$mov->type] ?? $mov->type }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500">
                            @if ($mov->type === 'inbound' || $mov->type === 'initial_load')
                                → {{ $mov->destinationWarehouse?->code ?? '—' }}
                            @elseif ($mov->type === 'outbound' || $mov->type === 'adjustment')
                                {{ $mov->originWarehouse?->code ?? '—' }}
                            @elseif ($mov->type === 'transfer')
                                {{ $mov->originWarehouse?->code ?? '—' }} → {{ $mov->destinationWarehouse?->code ?? '—' }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge color="zinc" size="sm" inset="top bottom">
                                {{ $mov->lines_count }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500">
                            {{ $mov->creator?->name ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($mov->status === 'confirmed')
                                <flux:badge color="green" size="sm" inset="top bottom">Confirmado</flux:badge>
                            @elseif ($mov->status === 'draft')
                                <flux:badge color="amber" size="sm" inset="top bottom">Borrador</flux:badge>
                            @else
                                <flux:badge color="red" size="sm" inset="top bottom">Anulado</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="eye"
                                :href="route('movements.show', $mov)"
                                wire:navigate
                                inset="top bottom"
                            />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="text-center text-zinc-500 py-8">
                            No hay movimientos registrados.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="movement-new" class="md:w-[36rem]">
        <form wire:submit="create" class="space-y-5">
            <div>
                <flux:heading size="lg">Nuevo movimiento</flux:heading>
                <flux:text class="mt-1">Elige el tipo y el almacén. Las líneas se agregan después.</flux:text>
            </div>

            <flux:select wire:model.live="newType" label="Tipo de movimiento">
                @foreach (static::TYPES as $v => $l)
                    <flux:select.option :value="$v">{{ $l }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input
                wire:model="newOccurredAt"
                type="datetime-local"
                label="Fecha y hora"
                required
            />

            @if (in_array($newType, ['outbound', 'adjustment', 'transfer'], true))
                <flux:select wire:model="newOriginWarehouseId" label="Almacén origen" required>
                    <flux:select.option value="">Elige un almacén...</flux:select.option>
                    @foreach ($this->warehouses as $wh)
                        <flux:select.option :value="$wh->id">{{ $wh->code }} — {{ $wh->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            @if (in_array($newType, ['inbound', 'initial_load', 'transfer'], true))
                <flux:select wire:model="newDestinationWarehouseId" label="Almacén destino" required>
                    <flux:select.option value="">Elige un almacén...</flux:select.option>
                    @foreach ($this->warehouses as $wh)
                        <flux:select.option :value="$wh->id">{{ $wh->code }} — {{ $wh->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:input
                wire:model="newReason"
                label="Motivo / referencia"
                placeholder="OC #123, Devolución cliente, etc. (opcional)"
                maxlength="255"
            />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="create-movement">
                    Crear borrador
                </flux:button>
            </div>
        </form>
    </flux:modal>
</section>
