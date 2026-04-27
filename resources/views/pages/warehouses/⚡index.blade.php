<?php

use App\Models\Warehouse;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Almacenes')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'type')]
    public string $typeFilter = '';

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $type = 'store';

    public string $address = '';

    public bool $active = true;

    public ?int $deletingId = null;

    public const TYPES = [
        'central' => 'Almacén central',
        'store' => 'Tienda',
        'workshop' => 'Taller',
        'scrap' => 'Merma',
        'transit' => 'Tránsito',
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function warehouses()
    {
        return Warehouse::query()
            ->when($this->search, function ($query) {
                $term = '%'.mb_strtolower($this->search).'%';
                $query->where(fn ($q) => $q
                    ->whereRaw('LOWER(code) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$term]));
            })
            ->when($this->typeFilter, fn ($query) => $query->where('type', $this->typeFilter))
            ->orderBy('code')
            ->paginate(15);
    }

    public function openCreate(): void
    {
        $this->authorize('create', Warehouse::class);
        $this->resetForm();
        Flux::modal('warehouse-form')->show();
    }

    public function openEdit(int $id): void
    {
        $warehouse = Warehouse::findOrFail($id);

        $this->authorize('update', $warehouse);

        $this->editingId = $warehouse->id;
        $this->code = $warehouse->code;
        $this->name = $warehouse->name;
        $this->type = $warehouse->type;
        $this->address = $warehouse->address ?? '';
        $this->active = $warehouse->active;

        Flux::modal('warehouse-form')->show();
    }

    public function save(): void
    {
        if ($this->editingId) {
            $this->authorize('update', Warehouse::findOrFail($this->editingId));
        } else {
            $this->authorize('create', Warehouse::class);
        }

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('warehouses', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'address' => ['nullable', 'string', 'max:255'],
            'active' => ['boolean'],
        ]);

        if ($this->editingId) {
            Warehouse::findOrFail($this->editingId)->update($validated);
            $message = 'Almacén actualizado.';
        } else {
            Warehouse::create($validated);
            $message = 'Almacén creado.';
        }

        $this->resetForm();
        Flux::modal('warehouse-form')->close();
        Flux::toast(variant: 'success', text: $message);
    }

    public function toggleActive(int $id): void
    {
        $warehouse = Warehouse::findOrFail($id);

        $this->authorize('update', $warehouse);

        $warehouse->update(['active' => ! $warehouse->active]);

        Flux::toast(
            variant: 'success',
            text: $warehouse->active ? 'Almacén activado.' : 'Almacén desactivado.',
        );
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('delete', Warehouse::findOrFail($id));
        $this->deletingId = $id;
        Flux::modal('warehouse-delete')->show();
    }

    #[Computed]
    public function deletingWarehouse(): ?Warehouse
    {
        return $this->deletingId
            ? Warehouse::withCount('movementLines')->find($this->deletingId)
            : null;
    }

    public function delete(): void
    {
        $warehouse = Warehouse::withCount('movementLines')->findOrFail($this->deletingId);

        $this->authorize('delete', $warehouse);

        if ($warehouse->movement_lines_count > 0) {
            Flux::toast(
                variant: 'danger',
                text: "No se puede eliminar: el almacén tiene {$warehouse->movement_lines_count} línea(s) de movimiento.",
            );

            return;
        }

        $name = $warehouse->name;
        $warehouse->delete();

        $this->deletingId = null;
        Flux::modal('warehouse-delete')->close();
        Flux::toast(variant: 'success', text: "Almacén «{$name}» eliminado.");
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'code', 'name', 'address']);
        $this->type = 'store';
        $this->active = true;
        $this->resetValidation();
    }
}; ?>

<section class="w-full p-6">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Almacenes</flux:heading>
                <flux:text class="mt-1">Gestión de almacén central, tiendas y ubicaciones lógicas.</flux:text>
            </div>

            @can('create', App\Models\Warehouse::class)
                <flux:button variant="primary" icon="plus" wire:click="openCreate" data-test="new-warehouse">
                    Nuevo almacén
                </flux:button>
            @endcan
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Buscar por código o nombre..."
                class="flex-1"
            />

            <flux:select wire:model.live="typeFilter" placeholder="Todos los tipos" class="sm:w-64">
                <flux:select.option value="">Todos los tipos</flux:select.option>
                @foreach (static::TYPES as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:table :paginate="$this->warehouses">
            <flux:table.columns>
                <flux:table.column>Código</flux:table.column>
                <flux:table.column>Nombre</flux:table.column>
                <flux:table.column>Tipo</flux:table.column>
                <flux:table.column>Dirección</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column>Acciones</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->warehouses as $warehouse)
                    <flux:table.row :key="$warehouse->id">
                        <flux:table.cell variant="strong">{{ $warehouse->code }}</flux:table.cell>
                        <flux:table.cell>{{ $warehouse->name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" inset="top bottom">
                                {{ static::TYPES[$warehouse->type] ?? $warehouse->type }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $warehouse->address ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($warehouse->active)
                                <flux:badge color="green" size="sm" inset="top bottom">Activo</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm" inset="top bottom">Inactivo</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-1">
                                @can('update', $warehouse)
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="pencil-square"
                                        wire:click="openEdit({{ $warehouse->id }})"
                                        inset="top bottom"
                                    />
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        :icon="$warehouse->active ? 'pause-circle' : 'play-circle'"
                                        wire:click="toggleActive({{ $warehouse->id }})"
                                        inset="top bottom"
                                    />
                                @endcan
                                @can('delete', $warehouse)
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="trash"
                                        wire:click="confirmDelete({{ $warehouse->id }})"
                                        inset="top bottom"
                                        class="text-red-600 hover:text-red-700"
                                    />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-500 py-8">
                            No hay almacenes que coincidan con la búsqueda.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="warehouse-form" class="md:w-[32rem]">
        <form wire:submit="save" class="space-y-5">
            <div>
                <flux:heading size="lg">
                    {{ $editingId ? 'Editar almacén' : 'Nuevo almacén' }}
                </flux:heading>
                <flux:text class="mt-1">
                    Los códigos deben ser únicos (ej. <code>ALM</code>, <code>TDA1</code>).
                </flux:text>
            </div>

            <flux:input
                wire:model="code"
                label="Código"
                placeholder="ALM"
                required
                autofocus
                maxlength="20"
            />

            <flux:input
                wire:model="name"
                label="Nombre"
                placeholder="Almacén Central"
                required
                maxlength="100"
            />

            <flux:select wire:model="type" label="Tipo">
                @foreach (static::TYPES as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input
                wire:model="address"
                label="Dirección"
                placeholder="Opcional"
                maxlength="255"
            />

            <flux:switch wire:model="active" label="Activo" />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="save-warehouse">
                    Guardar
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="warehouse-delete" class="md:w-[28rem]">
        @if ($this->deletingWarehouse)
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">Eliminar almacén</flux:heading>
                    <flux:text class="mt-2">
                        ¿Seguro que quieres eliminar <strong>{{ $this->deletingWarehouse->name }}</strong>
                        ({{ $this->deletingWarehouse->code }})?
                    </flux:text>
                </div>

                @if ($this->deletingWarehouse->movement_lines_count > 0)
                    <div class="rounded-lg bg-red-50 dark:bg-red-950/30 p-4 text-sm text-red-700 dark:text-red-300">
                        Este almacén tiene <strong>{{ $this->deletingWarehouse->movement_lines_count }}</strong>
                        línea(s) de movimiento. No se puede eliminar.
                        Considera desactivarlo en su lugar.
                    </div>
                @else
                    <flux:text class="text-zinc-500">
                        Esta acción es permanente.
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
                        :disabled="$this->deletingWarehouse->movement_lines_count > 0"
                        data-test="confirm-delete-warehouse"
                    >
                        Eliminar
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</section>
