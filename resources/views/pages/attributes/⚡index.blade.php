<?php

use App\Models\Attribute;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Atributos')] class extends Component {
    use WithPagination;

    public const TYPES = [
        'text' => 'Texto',
        'number' => 'Número',
        'boolean' => 'Sí / No',
        'list' => 'Lista de opciones',
    ];

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'type')]
    public string $typeFilter = '';

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $type = 'text';

    public string $unit = '';

    public string $optionsText = '';

    public ?int $deletingId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function attributeList()
    {
        return Attribute::query()
            ->withCount(['families', 'skuValues'])
            ->when($this->search, function ($query) {
                $term = '%'.mb_strtolower($this->search).'%';
                $query->where(fn ($q) => $q
                    ->whereRaw('LOWER(code) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$term]));
            })
            ->when($this->typeFilter, fn ($query) => $query->where('type', $this->typeFilter))
            ->orderBy('name')
            ->paginate(15);
    }

    public function openCreate(): void
    {
        $this->authorize('create', Attribute::class);
        $this->resetForm();
        Flux::modal('attribute-form')->show();
    }

    public function openEdit(int $id): void
    {
        $attribute = Attribute::findOrFail($id);

        $this->authorize('update', $attribute);

        $this->editingId = $attribute->id;
        $this->code = $attribute->code;
        $this->name = $attribute->name;
        $this->type = $attribute->type;
        $this->unit = $attribute->unit ?? '';
        $this->optionsText = $attribute->options
            ? implode("\n", $attribute->options)
            : '';

        Flux::modal('attribute-form')->show();
    }

    public function save(): void
    {
        if ($this->editingId) {
            $this->authorize('update', Attribute::findOrFail($this->editingId));
        } else {
            $this->authorize('create', Attribute::class);
        }

        $this->code = Str::lower(Str::squish($this->code));

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('attributes', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'unit' => ['nullable', 'string', 'max:10'],
            'optionsText' => [Rule::requiredIf(fn () => $this->type === 'list')],
        ], [
            'code.regex' => 'El código debe empezar en minúscula y solo contener letras, números y guión bajo.',
            'optionsText.required' => 'Define al menos una opción cuando el tipo es lista.',
        ]);

        $options = null;
        if ($this->type === 'list') {
            $options = collect(preg_split('/\r\n|\r|\n/', $this->optionsText))
                ->map(fn ($line) => trim($line))
                ->filter()
                ->values()
                ->all();

            if (empty($options)) {
                $this->addError('optionsText', 'Define al menos una opción cuando el tipo es lista.');

                return;
            }
        }

        $payload = [
            'code' => $validated['code'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'unit' => $this->unit ?: null,
            'options' => $options,
        ];

        if ($this->editingId) {
            Attribute::findOrFail($this->editingId)->update($payload);
            $message = 'Atributo actualizado.';
        } else {
            Attribute::create($payload);
            $message = 'Atributo creado.';
        }

        $this->resetForm();
        Flux::modal('attribute-form')->close();
        Flux::toast(variant: 'success', text: $message);
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('delete', Attribute::findOrFail($id));
        $this->deletingId = $id;
        Flux::modal('attribute-delete')->show();
    }

    #[Computed]
    public function deletingRecord(): ?Attribute
    {
        return $this->deletingId
            ? Attribute::withCount(['families', 'skuValues'])->find($this->deletingId)
            : null;
    }

    public function delete(): void
    {
        $attribute = Attribute::withCount(['families', 'skuValues'])->findOrFail($this->deletingId);

        $this->authorize('delete', $attribute);

        if ($attribute->families_count > 0) {
            Flux::toast(
                variant: 'danger',
                text: "Está asignado a {$attribute->families_count} familia(s). Quita la asignación primero.",
            );

            return;
        }

        if ($attribute->sku_values_count > 0) {
            Flux::toast(
                variant: 'danger',
                text: "Tiene {$attribute->sku_values_count} valor(es) registrado(s) en SKUs.",
            );

            return;
        }

        $name = $attribute->name;
        $attribute->delete();

        $this->deletingId = null;
        Flux::modal('attribute-delete')->close();
        Flux::toast(variant: 'success', text: "Atributo «{$name}» eliminado.");
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'code', 'name', 'unit', 'optionsText']);
        $this->type = 'text';
        $this->resetValidation();
    }
}; ?>

<section class="w-full p-6">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Atributos</flux:heading>
                <flux:text class="mt-1">
                    Catálogo global de atributos reutilizables (color, material, medidas, etc.).
                </flux:text>
            </div>

            @can('create', App\Models\Attribute::class)
                <flux:button variant="primary" icon="plus" wire:click="openCreate" data-test="new-attribute">
                    Nuevo atributo
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

        <flux:table :paginate="$this->attributeList">
            <flux:table.columns>
                <flux:table.column>Código</flux:table.column>
                <flux:table.column>Nombre</flux:table.column>
                <flux:table.column>Tipo</flux:table.column>
                <flux:table.column>Unidad</flux:table.column>
                <flux:table.column>Uso</flux:table.column>
                <flux:table.column>Acciones</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->attributeList as $attr)
                    <flux:table.row :key="$attr->id">
                        <flux:table.cell variant="strong">
                            <code class="text-xs font-mono">{{ $attr->code }}</code>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span>{{ $attr->name }}</span>
                                @if ($attr->type === 'list' && $attr->options)
                                    <span class="text-xs text-zinc-500">
                                        {{ Str::limit(implode(', ', $attr->options), 60) }}
                                    </span>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" inset="top bottom">
                                {{ static::TYPES[$attr->type] ?? $attr->type }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $attr->unit ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-2 text-xs text-zinc-500">
                                <span>{{ $attr->families_count }} fam.</span>
                                <span>·</span>
                                <span>{{ $attr->sku_values_count }} SKUs</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-1">
                                @can('update', $attr)
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="pencil-square"
                                        wire:click="openEdit({{ $attr->id }})"
                                        inset="top bottom"
                                    />
                                @endcan
                                @can('delete', $attr)
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="trash"
                                        wire:click="confirmDelete({{ $attr->id }})"
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
                            No hay atributos registrados.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="attribute-form" class="md:w-[32rem]">
        <form wire:submit="save" class="space-y-5">
            <div>
                <flux:heading size="lg">
                    {{ $editingId ? 'Editar atributo' : 'Nuevo atributo' }}
                </flux:heading>
                <flux:text class="mt-1">
                    Usa código en minúsculas tipo <code>color</code>, <code>material</code>, <code>largo_cm</code>.
                </flux:text>
            </div>

            <flux:input
                wire:model="code"
                label="Código"
                placeholder="color"
                required
                autofocus
                maxlength="30"
            />

            <flux:input
                wire:model="name"
                label="Nombre"
                placeholder="Color"
                required
                maxlength="100"
            />

            <flux:select wire:model.live="type" label="Tipo">
                @foreach (static::TYPES as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($type === 'number')
                <flux:input
                    wire:model="unit"
                    label="Unidad"
                    placeholder="cm, kg, mm..."
                    maxlength="10"
                />
            @endif

            @if ($type === 'list')
                <flux:textarea
                    wire:model="optionsText"
                    label="Opciones"
                    placeholder="Una opción por línea&#10;negro&#10;blanco&#10;azul"
                    rows="5"
                />
            @endif

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="save-attribute">
                    Guardar
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="attribute-delete" class="md:w-[28rem]">
        @if ($this->deletingRecord)
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">Eliminar atributo</flux:heading>
                    <flux:text class="mt-2">
                        ¿Seguro que quieres eliminar <strong>{{ $this->deletingRecord->name }}</strong>
                        (<code>{{ $this->deletingRecord->code }}</code>)?
                    </flux:text>
                </div>

                @php
                    $aBlocked = $this->deletingRecord->families_count > 0
                        || $this->deletingRecord->sku_values_count > 0;
                @endphp

                @if ($this->deletingRecord->families_count > 0)
                    <div class="rounded-lg bg-red-50 dark:bg-red-950/30 p-4 text-sm text-red-700 dark:text-red-300">
                        Está asignado a <strong>{{ $this->deletingRecord->families_count }}</strong>
                        familia(s). Quita la asignación primero.
                    </div>
                @endif

                @if ($this->deletingRecord->sku_values_count > 0)
                    <div class="rounded-lg bg-red-50 dark:bg-red-950/30 p-4 text-sm text-red-700 dark:text-red-300">
                        Tiene <strong>{{ $this->deletingRecord->sku_values_count }}</strong>
                        valor(es) registrado(s) en SKUs.
                    </div>
                @endif

                @if (! $aBlocked)
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
                        :disabled="$aBlocked"
                        data-test="confirm-delete-attribute"
                    >
                        Eliminar
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</section>
