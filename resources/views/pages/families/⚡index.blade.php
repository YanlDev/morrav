<?php

use App\Models\Attribute;
use App\Models\Family;
use App\Models\Subfamily;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Familias')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public bool $active = true;

    public ?int $deletingId = null;

    public ?int $deletingSubfamilyId = null;

    public ?int $subfamilyParentId = null;

    public ?int $editingSubfamilyId = null;

    public string $subfamilyCode = '';

    public string $subfamilyName = '';

    public bool $subfamilyActive = true;

    public bool $showSubfamilyForm = false;

    public ?int $attributeParentId = null;

    public ?int $attachingAttributeId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function families()
    {
        return Family::query()
            ->withCount(['subfamilies', 'products', 'attributes'])
            ->when($this->search, function ($query) {
                $term = '%'.mb_strtolower($this->search).'%';
                $query->where(fn ($q) => $q
                    ->whereRaw('LOWER(code) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$term]));
            })
            ->orderBy('name')
            ->paginate(15);
    }

    #[Computed]
    public function selectedFamily(): ?Family
    {
        return $this->subfamilyParentId
            ? Family::find($this->subfamilyParentId)
            : null;
    }

    #[Computed]
    public function subfamilies()
    {
        if (! $this->subfamilyParentId) {
            return collect();
        }

        return Subfamily::query()
            ->where('family_id', $this->subfamilyParentId)
            ->orderBy('name')
            ->get();
    }

    public function openCreate(): void
    {
        $this->authorize('create', Family::class);
        $this->resetForm();
        Flux::modal('family-form')->show();
    }

    public function openEdit(int $id): void
    {
        $family = Family::findOrFail($id);

        $this->authorize('update', $family);

        $this->editingId = $family->id;
        $this->code = $family->code;
        $this->name = $family->name;
        $this->description = $family->description ?? '';
        $this->active = $family->active;

        Flux::modal('family-form')->show();
    }

    public function save(): void
    {
        if ($this->editingId) {
            $this->authorize('update', Family::findOrFail($this->editingId));
        } else {
            $this->authorize('create', Family::class);
        }

        $this->code = Str::upper(Str::squish($this->code));

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_]+$/', Rule::unique('families', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'active' => ['boolean'],
        ], [
            'code.regex' => 'El código solo puede contener mayúsculas, números y guión bajo.',
        ]);

        if ($this->editingId) {
            Family::findOrFail($this->editingId)->update($validated);
            $message = 'Familia actualizada.';
        } else {
            Family::create($validated);
            $message = 'Familia creada.';
        }

        $this->resetForm();
        Flux::modal('family-form')->close();
        Flux::toast(variant: 'success', text: $message);
    }

    public function toggleActive(int $id): void
    {
        $family = Family::findOrFail($id);

        $this->authorize('update', $family);

        $family->update(['active' => ! $family->active]);

        Flux::toast(
            variant: 'success',
            text: $family->active ? 'Familia activada.' : 'Familia desactivada.',
        );
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('delete', Family::findOrFail($id));
        $this->deletingId = $id;
        Flux::modal('family-delete')->show();
    }

    #[Computed]
    public function deletingFamily(): ?Family
    {
        return $this->deletingId
            ? Family::withCount(['subfamilies', 'products'])->find($this->deletingId)
            : null;
    }

    public function delete(): void
    {
        $family = Family::withCount(['subfamilies', 'products'])->findOrFail($this->deletingId);

        $this->authorize('delete', $family);

        if ($family->subfamilies_count > 0) {
            Flux::toast(
                variant: 'danger',
                text: "Tiene {$family->subfamilies_count} subfamilia(s). Elimínalas primero.",
            );

            return;
        }

        if ($family->products_count > 0) {
            Flux::toast(
                variant: 'danger',
                text: "Tiene {$family->products_count} producto(s) vinculados. Reasígnalos primero.",
            );

            return;
        }

        $name = $family->name;
        $family->delete();

        $this->deletingId = null;
        Flux::modal('family-delete')->close();
        Flux::toast(variant: 'success', text: "Familia «{$name}» eliminada.");
    }

    public function openSubfamilies(int $familyId): void
    {
        $this->subfamilyParentId = $familyId;
        $this->resetSubfamilyForm();
        Flux::modal('subfamilies-manager')->show();
    }

    public function showAddSubfamily(): void
    {
        $this->resetSubfamilyForm();
        $this->showSubfamilyForm = true;
    }

    public function editSubfamily(int $id): void
    {
        $subfamily = Subfamily::findOrFail($id);

        $this->editingSubfamilyId = $subfamily->id;
        $this->subfamilyCode = $subfamily->code;
        $this->subfamilyName = $subfamily->name;
        $this->subfamilyActive = $subfamily->active;
        $this->showSubfamilyForm = true;
        $this->resetValidation();
    }

    public function saveSubfamily(): void
    {
        abort_unless($this->subfamilyParentId, 404);

        $this->authorize('update', Family::findOrFail($this->subfamilyParentId));

        $this->subfamilyCode = Str::upper(Str::squish($this->subfamilyCode));

        $validated = $this->validate([
            'subfamilyCode' => [
                'required', 'string', 'max:20', 'regex:/^[A-Z0-9_]+$/',
                Rule::unique('subfamilies', 'code')
                    ->where('family_id', $this->subfamilyParentId)
                    ->ignore($this->editingSubfamilyId),
            ],
            'subfamilyName' => ['required', 'string', 'max:100'],
            'subfamilyActive' => ['boolean'],
        ], [
            'subfamilyCode.regex' => 'El código solo puede contener mayúsculas, números y guión bajo.',
            'subfamilyCode.unique' => 'Ya existe una subfamilia con ese código en esta familia.',
        ]);

        $payload = [
            'family_id' => $this->subfamilyParentId,
            'code' => $validated['subfamilyCode'],
            'name' => $validated['subfamilyName'],
            'active' => $validated['subfamilyActive'],
        ];

        if ($this->editingSubfamilyId) {
            Subfamily::findOrFail($this->editingSubfamilyId)->update($payload);
            $message = 'Subfamilia actualizada.';
        } else {
            Subfamily::create($payload);
            $message = 'Subfamilia creada.';
        }

        $this->resetSubfamilyForm();
        Flux::toast(variant: 'success', text: $message);
    }

    public function toggleSubfamilyActive(int $id): void
    {
        $subfamily = Subfamily::findOrFail($id);

        $this->authorize('update', $subfamily->family);

        $subfamily->update(['active' => ! $subfamily->active]);

        Flux::toast(
            variant: 'success',
            text: $subfamily->active ? 'Subfamilia activada.' : 'Subfamilia desactivada.',
        );
    }

    public function confirmDeleteSubfamily(int $id): void
    {
        $subfamily = Subfamily::findOrFail($id);
        $this->authorize('delete', $subfamily->family);
        $this->deletingSubfamilyId = $id;
        Flux::modal('subfamily-delete')->show();
    }

    #[Computed]
    public function deletingSubfamily(): ?Subfamily
    {
        return $this->deletingSubfamilyId
            ? Subfamily::withCount('products')->find($this->deletingSubfamilyId)
            : null;
    }

    public function deleteSubfamily(): void
    {
        $subfamily = Subfamily::withCount('products')->findOrFail($this->deletingSubfamilyId);

        $this->authorize('delete', $subfamily->family);

        if ($subfamily->products_count > 0) {
            Flux::toast(
                variant: 'danger',
                text: "Tiene {$subfamily->products_count} producto(s) vinculados. Reasígnalos primero.",
            );

            return;
        }

        $name = $subfamily->name;
        $subfamily->delete();

        $this->deletingSubfamilyId = null;
        Flux::modal('subfamily-delete')->close();
        Flux::toast(variant: 'success', text: "Subfamilia «{$name}» eliminada.");
    }

    public function cancelSubfamilyForm(): void
    {
        $this->resetSubfamilyForm();
    }

    public function openFamilyAttributes(int $familyId): void
    {
        $this->attributeParentId = $familyId;
        $this->attachingAttributeId = null;
        Flux::modal('family-attributes-manager')->show();
    }

    #[Computed]
    public function attributeParent(): ?Family
    {
        return $this->attributeParentId
            ? Family::find($this->attributeParentId)
            : null;
    }

    #[Computed]
    public function familyAttributes()
    {
        if (! $this->attributeParentId) {
            return collect();
        }

        return Attribute::query()
            ->whereHas('families', fn ($q) => $q->where('families.id', $this->attributeParentId))
            ->with(['families' => fn ($q) => $q->where('families.id', $this->attributeParentId)])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function availableAttributes()
    {
        if (! $this->attributeParentId) {
            return collect();
        }

        $assignedIds = $this->familyAttributes->pluck('id');

        return Attribute::query()
            ->whereNotIn('id', $assignedIds)
            ->orderBy('name')
            ->get();
    }

    public function attachAttribute(): void
    {
        abort_unless($this->attributeParentId, 404);

        $family = Family::findOrFail($this->attributeParentId);
        $this->authorize('update', $family);

        $this->validate([
            'attachingAttributeId' => ['required', 'exists:attributes,id'],
        ]);

        $family->attributes()->syncWithoutDetaching([
            $this->attachingAttributeId => [
                'is_required' => false,
                'is_key' => false,
                'sort_order' => $this->familyAttributes->count(),
            ],
        ]);

        unset($this->familyAttributes, $this->availableAttributes);
        $this->attachingAttributeId = null;

        Flux::toast(variant: 'success', text: 'Atributo asignado.');
    }

    public function toggleRequired(int $attributeId): void
    {
        $family = Family::findOrFail($this->attributeParentId);
        $this->authorize('update', $family);

        $pivot = $family->attributes()->where('attributes.id', $attributeId)->first()?->pivot;
        abort_unless($pivot, 404);

        $family->attributes()->updateExistingPivot($attributeId, [
            'is_required' => ! $pivot->is_required,
        ]);

        unset($this->familyAttributes);
    }

    public function toggleKey(int $attributeId): void
    {
        $family = Family::findOrFail($this->attributeParentId);
        $this->authorize('update', $family);

        $pivot = $family->attributes()->where('attributes.id', $attributeId)->first()?->pivot;
        abort_unless($pivot, 404);

        $family->attributes()->updateExistingPivot($attributeId, [
            'is_key' => ! $pivot->is_key,
        ]);

        unset($this->familyAttributes);
    }

    public function detachAttribute(int $attributeId): void
    {
        $family = Family::findOrFail($this->attributeParentId);
        $this->authorize('update', $family);

        $family->attributes()->detach($attributeId);

        unset($this->familyAttributes, $this->availableAttributes);

        Flux::toast(variant: 'success', text: 'Atributo desvinculado.');
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'code', 'name', 'description']);
        $this->active = true;
        $this->resetValidation();
    }

    private function resetSubfamilyForm(): void
    {
        $this->reset(['editingSubfamilyId', 'subfamilyCode', 'subfamilyName', 'showSubfamilyForm']);
        $this->subfamilyActive = true;
        $this->resetValidation();
    }
}; ?>

<section class="w-full p-6">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Familias</flux:heading>
                <flux:text class="mt-1">Clasificación principal del catálogo de productos.</flux:text>
            </div>

            @can('create', App\Models\Family::class)
                <flux:button variant="primary" icon="plus" wire:click="openCreate" data-test="new-family">
                    Nueva familia
                </flux:button>
            @endcan
        </div>

        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="Buscar por código o nombre..."
        />

        <flux:table :paginate="$this->families">
            <flux:table.columns>
                <flux:table.column>Código</flux:table.column>
                <flux:table.column>Nombre</flux:table.column>
                <flux:table.column>Subfamilias</flux:table.column>
                <flux:table.column>Atributos</flux:table.column>
                <flux:table.column>Productos</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column>Acciones</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->families as $family)
                    <flux:table.row :key="$family->id">
                        <flux:table.cell variant="strong">{{ $family->code }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span>{{ $family->name }}</span>
                                @if ($family->description)
                                    <span class="text-xs text-zinc-500">{{ Str::limit($family->description, 80) }}</span>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button
                                variant="ghost"
                                size="sm"
                                wire:click="openSubfamilies({{ $family->id }})"
                                inset="top bottom"
                            >
                                <flux:badge color="blue" size="sm" inset="top bottom">
                                    {{ $family->subfamilies_count }}
                                </flux:badge>
                                <span class="ml-1">Gestionar</span>
                            </flux:button>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button
                                variant="ghost"
                                size="sm"
                                wire:click="openFamilyAttributes({{ $family->id }})"
                                inset="top bottom"
                            >
                                <flux:badge color="purple" size="sm" inset="top bottom">
                                    {{ $family->attributes_count }}
                                </flux:badge>
                                <span class="ml-1">Gestionar</span>
                            </flux:button>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge color="zinc" size="sm" inset="top bottom">
                                {{ $family->products_count }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($family->active)
                                <flux:badge color="green" size="sm" inset="top bottom">Activa</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm" inset="top bottom">Inactiva</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-1">
                                @can('update', $family)
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="pencil-square"
                                        wire:click="openEdit({{ $family->id }})"
                                        inset="top bottom"
                                    />
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        :icon="$family->active ? 'pause-circle' : 'play-circle'"
                                        wire:click="toggleActive({{ $family->id }})"
                                        inset="top bottom"
                                    />
                                @endcan
                                @can('delete', $family)
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="trash"
                                        wire:click="confirmDelete({{ $family->id }})"
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
                            No hay familias registradas.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="family-form" class="md:w-[32rem]">
        <form wire:submit="save" class="space-y-5">
            <div>
                <flux:heading size="lg">
                    {{ $editingId ? 'Editar familia' : 'Nueva familia' }}
                </flux:heading>
                <flux:text class="mt-1">
                    El código se convierte a mayúsculas automáticamente.
                </flux:text>
            </div>

            <flux:input
                wire:model="code"
                label="Código"
                placeholder="SILLAS"
                required
                autofocus
                maxlength="20"
            />

            <flux:input
                wire:model="name"
                label="Nombre"
                placeholder="Sillas"
                required
                maxlength="100"
            />

            <flux:textarea
                wire:model="description"
                label="Descripción"
                placeholder="Opcional"
                rows="3"
            />

            <flux:switch wire:model="active" label="Activa" />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="save-family">
                    Guardar
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="subfamilies-manager" flyout class="md:w-[36rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Subfamilias</flux:heading>
                @if ($this->selectedFamily)
                    <flux:text class="mt-1">
                        Gestiona las subfamilias de <strong>{{ $this->selectedFamily->name }}</strong>
                        ({{ $this->selectedFamily->code }}).
                    </flux:text>
                @endif
            </div>

            @if (! $showSubfamilyForm)
                <flux:button variant="primary" icon="plus" size="sm" wire:click="showAddSubfamily" data-test="new-subfamily">
                    Nueva subfamilia
                </flux:button>
            @endif

            @if ($showSubfamilyForm)
                <form wire:submit="saveSubfamily" class="space-y-4 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                    <flux:heading size="sm">
                        {{ $editingSubfamilyId ? 'Editar subfamilia' : 'Nueva subfamilia' }}
                    </flux:heading>

                    <flux:input
                        wire:model="subfamilyCode"
                        label="Código"
                        placeholder="OFICINA"
                        required
                        maxlength="20"
                    />

                    <flux:input
                        wire:model="subfamilyName"
                        label="Nombre"
                        placeholder="Sillas de oficina"
                        required
                        maxlength="100"
                    />

                    <flux:switch wire:model="subfamilyActive" label="Activa" />

                    <div class="flex gap-2 justify-end">
                        <flux:button variant="ghost" size="sm" type="button" wire:click="cancelSubfamilyForm">
                            Cancelar
                        </flux:button>
                        <flux:button type="submit" variant="primary" size="sm" data-test="save-subfamily">
                            Guardar
                        </flux:button>
                    </div>
                </form>
            @endif

            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->subfamilies as $subfamily)
                    <div class="flex items-center justify-between py-3" wire:key="subfamily-{{ $subfamily->id }}">
                        <div class="flex flex-col">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-sm text-zinc-500">{{ $subfamily->code }}</span>
                                @if (! $subfamily->active)
                                    <flux:badge color="zinc" size="sm" inset="top bottom">Inactiva</flux:badge>
                                @endif
                            </div>
                            <span>{{ $subfamily->name }}</span>
                        </div>

                        <div class="flex gap-1">
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="pencil-square"
                                wire:click="editSubfamily({{ $subfamily->id }})"
                                inset="top bottom"
                            />
                            <flux:button
                                variant="ghost"
                                size="sm"
                                :icon="$subfamily->active ? 'pause-circle' : 'play-circle'"
                                wire:click="toggleSubfamilyActive({{ $subfamily->id }})"
                                inset="top bottom"
                            />
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="trash"
                                wire:click="confirmDeleteSubfamily({{ $subfamily->id }})"
                                inset="top bottom"
                                class="text-red-600 hover:text-red-700"
                            />
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-zinc-500">
                        Aún no hay subfamilias. Agrega la primera.
                    </div>
                @endforelse
            </div>
        </div>
    </flux:modal>

    <flux:modal name="family-delete" class="md:w-[28rem]">
        @if ($this->deletingFamily)
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">Eliminar familia</flux:heading>
                    <flux:text class="mt-2">
                        ¿Seguro que quieres eliminar <strong>{{ $this->deletingFamily->name }}</strong>
                        ({{ $this->deletingFamily->code }})?
                    </flux:text>
                </div>

                @php
                    $fBlocked = $this->deletingFamily->subfamilies_count > 0
                        || $this->deletingFamily->products_count > 0;
                @endphp

                @if ($this->deletingFamily->subfamilies_count > 0)
                    <div class="rounded-lg bg-red-50 dark:bg-red-950/30 p-4 text-sm text-red-700 dark:text-red-300">
                        Tiene <strong>{{ $this->deletingFamily->subfamilies_count }}</strong> subfamilia(s).
                        Elimínalas primero.
                    </div>
                @endif

                @if ($this->deletingFamily->products_count > 0)
                    <div class="rounded-lg bg-red-50 dark:bg-red-950/30 p-4 text-sm text-red-700 dark:text-red-300">
                        Tiene <strong>{{ $this->deletingFamily->products_count }}</strong> producto(s) vinculados.
                        Reasígnalos o elimínalos primero.
                    </div>
                @endif

                @if (! $fBlocked)
                    <flux:text class="text-zinc-500">Esta acción es permanente.</flux:text>
                @endif

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancelar</flux:button>
                    </flux:modal.close>
                    <flux:button
                        variant="danger"
                        wire:click="delete"
                        :disabled="$fBlocked"
                        data-test="confirm-delete-family"
                    >
                        Eliminar
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal name="subfamily-delete" class="md:w-[28rem]">
        @if ($this->deletingSubfamily)
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">Eliminar subfamilia</flux:heading>
                    <flux:text class="mt-2">
                        ¿Seguro que quieres eliminar <strong>{{ $this->deletingSubfamily->name }}</strong>
                        ({{ $this->deletingSubfamily->code }})?
                    </flux:text>
                </div>

                @if ($this->deletingSubfamily->products_count > 0)
                    <div class="rounded-lg bg-red-50 dark:bg-red-950/30 p-4 text-sm text-red-700 dark:text-red-300">
                        Tiene <strong>{{ $this->deletingSubfamily->products_count }}</strong> producto(s) vinculados.
                        Reasígnalos o elimínalos primero.
                    </div>
                @else
                    <flux:text class="text-zinc-500">Esta acción es permanente.</flux:text>
                @endif

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancelar</flux:button>
                    </flux:modal.close>
                    <flux:button
                        variant="danger"
                        wire:click="deleteSubfamily"
                        :disabled="$this->deletingSubfamily->products_count > 0"
                        data-test="confirm-delete-subfamily"
                    >
                        Eliminar
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal name="family-attributes-manager" flyout class="md:w-[40rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Atributos de la familia</flux:heading>
                @if ($this->attributeParent)
                    <flux:text class="mt-1">
                        Define qué atributos aplican a los productos de
                        <strong>{{ $this->attributeParent->name }}</strong> ({{ $this->attributeParent->code }}).
                    </flux:text>
                @endif
            </div>

            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 space-y-3">
                <flux:heading size="sm">Asignar atributo</flux:heading>

                @if ($this->availableAttributes->isEmpty())
                    <flux:text class="text-zinc-500 text-sm">
                        No hay más atributos disponibles. Crea uno desde la página de Atributos.
                    </flux:text>
                @else
                    <form wire:submit="attachAttribute" class="flex gap-2">
                        <flux:select wire:model="attachingAttributeId" placeholder="Elige un atributo..." class="flex-1">
                            <flux:select.option value="">Elige un atributo...</flux:select.option>
                            @foreach ($this->availableAttributes as $attr)
                                <flux:select.option :value="$attr->id">
                                    {{ $attr->name }} ({{ $attr->code }})
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:button type="submit" variant="primary" icon="plus" data-test="attach-attribute">
                            Asignar
                        </flux:button>
                    </form>
                @endif
            </div>

            <div class="space-y-2">
                <flux:heading size="sm">Atributos asignados</flux:heading>

                @forelse ($this->familyAttributes as $attr)
                    @php $pivot = $attr->families->first()->pivot; @endphp
                    <div
                        class="flex items-start justify-between gap-4 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3"
                        wire:key="family-attr-{{ $attr->id }}"
                    >
                        <div class="flex-1 space-y-2">
                            <div class="flex items-center gap-2">
                                <code class="text-xs font-mono text-zinc-500">{{ $attr->code }}</code>
                                <span>{{ $attr->name }}</span>
                                <flux:badge size="sm" inset="top bottom">
                                    {{ ['text' => 'Texto', 'number' => 'Número', 'boolean' => 'Sí/No', 'list' => 'Lista'][$attr->type] ?? $attr->type }}
                                </flux:badge>
                                @if ($attr->unit)
                                    <span class="text-xs text-zinc-500">({{ $attr->unit }})</span>
                                @endif
                            </div>

                            <div class="flex flex-wrap gap-3 text-xs">
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        class="rounded"
                                        wire:click="toggleRequired({{ $attr->id }})"
                                        @checked($pivot->is_required)
                                    />
                                    <span>Obligatorio</span>
                                </label>
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        class="rounded"
                                        wire:click="toggleKey({{ $attr->id }})"
                                        @checked($pivot->is_key)
                                    />
                                    <span>Clave (anti-duplicado)</span>
                                </label>
                            </div>
                        </div>

                        <flux:button
                            variant="ghost"
                            size="sm"
                            icon="trash"
                            wire:click="detachAttribute({{ $attr->id }})"
                            inset="top bottom"
                            class="text-red-600 hover:text-red-700"
                            data-test="detach-attribute-{{ $attr->id }}"
                        />
                    </div>
                @empty
                    <div class="py-8 text-center text-zinc-500">
                        Aún no hay atributos asignados a esta familia.
                    </div>
                @endforelse
            </div>
        </div>
    </flux:modal>
</section>
