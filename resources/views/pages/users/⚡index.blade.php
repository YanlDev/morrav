<?php

use App\Enums\UserRole;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Usuarios')] class extends Component {
    use WithPagination;

    public const STATUSES = [
        '' => 'Todos',
        'enabled' => 'Activos',
        'disabled' => 'Deshabilitados',
    ];

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'role')]
    public string $roleFilter = '';

    #[Url(as: 's')]
    public string $statusFilter = '';

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $role = 'seller';

    public ?int $deletingId = null;

    public ?int $disablingId = null;

    /**
     * Link de invitación generado al crear un usuario o al regenerarlo desde la tabla.
     * Se muestra una sola vez en un modal para que el admin lo copie.
     */
    public ?string $inviteLink = null;

    public ?int $inviteUserId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->when($this->search, function ($query) {
                $term = '%'.mb_strtolower($this->search).'%';
                $query->where(fn ($q) => $q
                    ->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term]));
            })
            ->when($this->roleFilter, fn ($query) => $query->where('role', $this->roleFilter))
            ->when($this->statusFilter === 'enabled', fn ($query) => $query->whereNull('disabled_at'))
            ->when($this->statusFilter === 'disabled', fn ($query) => $query->whereNotNull('disabled_at'))
            ->orderBy('name')
            ->paginate(15);
    }

    public function openCreate(): void
    {
        $this->authorize('create', User::class);
        $this->resetForm();
        Flux::modal('user-form')->show();
    }

    public function openEdit(int $id): void
    {
        $this->authorize('create', User::class);
        $user = User::findOrFail($id);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;

        Flux::modal('user-form')->show();
    }

    public function save(): void
    {
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($this->editingId)],
            'role' => ['required', Rule::in(array_column(UserRole::cases(), 'value'))],
        ];

        $validated = $this->validate($rules);

        if ($this->editingId) {
            $this->authorize('create', User::class);
            $user = User::findOrFail($this->editingId);
            $user->update($validated);
            Flux::modal('user-form')->close();
            $this->resetForm();
            Flux::toast(variant: 'success', text: 'Usuario actualizado.');

            return;
        }

        $this->authorize('create', User::class);

        $user = User::create([
            'name' => $validated['name'],
            'email' => mb_strtolower($validated['email']),
            'password' => bcrypt(Str::random(32)),
            'role' => $validated['role'],
        ]);

        Flux::modal('user-form')->close();
        $this->resetForm();

        $this->showInviteLink($user);
    }

    public function regenerateInvite(int $id): void
    {
        $this->authorize('create', User::class);
        $user = User::findOrFail($id);

        $this->showInviteLink($user);
    }

    private function showInviteLink(User $user): void
    {
        $token = Password::broker()->createToken($user);

        $this->inviteUserId = $user->id;
        $this->inviteLink = route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]);

        Flux::modal('user-invite')->show();
    }

    public function confirmDelete(int $id): void
    {
        $user = User::findOrFail($id);
        $this->authorize('delete', $user);

        $this->deletingId = $id;
        Flux::modal('user-delete')->show();
    }

    public function delete(): void
    {
        $user = User::findOrFail($this->deletingId);
        $this->authorize('delete', $user);

        try {
            $user->delete();
        } catch (QueryException $e) {
            Flux::toast(
                variant: 'danger',
                text: 'No se puede eliminar: el usuario tiene registros asociados (movimientos, órdenes, etc.). Considera deshabilitarlo en su lugar.',
            );

            return;
        }

        $this->deletingId = null;
        Flux::modal('user-delete')->close();
        Flux::toast(variant: 'success', text: "Usuario «{$user->name}» eliminado.");
    }

    public function confirmDisable(int $id): void
    {
        $user = User::findOrFail($id);
        $this->authorize('disable', $user);

        $this->disablingId = $id;
        Flux::modal('user-disable')->show();
    }

    public function toggleDisabled(): void
    {
        $user = User::findOrFail($this->disablingId);
        $this->authorize('disable', $user);

        if ($user->isDisabled()) {
            $user->update(['disabled_at' => null]);
            $message = "Usuario «{$user->name}» habilitado.";
        } else {
            $user->update(['disabled_at' => now()]);
            $message = "Usuario «{$user->name}» deshabilitado.";
        }

        $this->disablingId = null;
        Flux::modal('user-disable')->close();
        Flux::toast(variant: 'success', text: $message);
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'email']);
        $this->role = 'seller';
        $this->resetValidation();
    }

    public function roleLabel(string $value): string
    {
        return UserRole::from($value)->label();
    }
}; ?>

<section class="w-full p-6">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Usuarios</flux:heading>
                <flux:text class="mt-1">
                    Gestiona las cuentas del equipo. Al crear un usuario el sistema genera
                    un link de invitación para que defina su contraseña.
                </flux:text>
            </div>

            @can('create', App\Models\User::class)
                <flux:button variant="primary" icon="plus" wire:click="openCreate" data-test="new-user">
                    Nuevo usuario
                </flux:button>
            @endcan
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Buscar por nombre o email..."
                class="sm:flex-1"
            />

            <flux:select wire:model.live="roleFilter" class="sm:w-48">
                <flux:select.option value="">Todos los roles</flux:select.option>
                @foreach (App\Enums\UserRole::cases() as $r)
                    <flux:select.option :value="$r->value">{{ $r->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="statusFilter" class="sm:w-44">
                @foreach (static::STATUSES as $v => $l)
                    <flux:select.option :value="$v">{{ $l }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:table :paginate="$this->users">
            <flux:table.columns>
                <flux:table.column>Nombre</flux:table.column>
                <flux:table.column>Email</flux:table.column>
                <flux:table.column>Rol</flux:table.column>
                <flux:table.column>2FA</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column>Acciones</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->users as $u)
                    <flux:table.row :key="$u->id">
                        <flux:table.cell variant="strong">
                            {{ $u->name }}
                            @if ($u->id === auth()->id())
                                <flux:badge color="zinc" size="sm" inset="top bottom">tú</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500">{{ $u->email }}</flux:table.cell>
                        <flux:table.cell>
                            @php
                                $roleColors = [
                                    'admin' => 'red',
                                    'owner' => 'amber',
                                    'warehouse' => 'blue',
                                    'seller' => 'green',
                                ];
                            @endphp
                            <flux:badge :color="$roleColors[$u->role->value] ?? 'zinc'" size="sm" inset="top bottom">
                                {{ $u->role->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($u->two_factor_confirmed_at)
                                <flux:badge color="green" size="sm" inset="top bottom">Activo</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm" inset="top bottom">—</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($u->isDisabled())
                                <flux:badge color="zinc" size="sm" inset="top bottom">Deshabilitado</flux:badge>
                            @else
                                <flux:badge color="green" size="sm" inset="top bottom">Activo</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-1">
                                @can('create', App\Models\User::class)
                                    <flux:tooltip content="Editar">
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            icon="pencil-square"
                                            wire:click="openEdit({{ $u->id }})"
                                            inset="top bottom"
                                        />
                                    </flux:tooltip>
                                    <flux:tooltip content="Generar link de invitación">
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            icon="link"
                                            wire:click="regenerateInvite({{ $u->id }})"
                                            inset="top bottom"
                                        />
                                    </flux:tooltip>
                                @endcan
                                @can('disable', $u)
                                    <flux:tooltip :content="$u->isDisabled() ? 'Habilitar' : 'Deshabilitar'">
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            :icon="$u->isDisabled() ? 'lock-open' : 'lock-closed'"
                                            wire:click="confirmDisable({{ $u->id }})"
                                            inset="top bottom"
                                            :class="$u->isDisabled() ? 'text-amber-600 hover:text-amber-700' : ''"
                                        />
                                    </flux:tooltip>
                                @endcan
                                @can('delete', $u)
                                    <flux:tooltip content="Eliminar">
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            icon="trash"
                                            wire:click="confirmDelete({{ $u->id }})"
                                            inset="top bottom"
                                            class="text-red-600 hover:text-red-700"
                                        />
                                    </flux:tooltip>
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-500 py-8">
                            No hay usuarios que coincidan con los filtros.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Modal: alta / edición --}}
    <flux:modal name="user-form" class="md:w-[36rem]">
        <form wire:submit="save" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Editar usuario' : 'Nuevo usuario' }}</flux:heading>
                @unless ($editingId)
                    <flux:text class="mt-1">
                        Al guardar te mostramos un link de invitación para compartir con esta persona.
                        El link sirve para que defina su contraseña.
                    </flux:text>
                @endunless
            </div>

            <flux:input wire:model="name" label="Nombre completo" required maxlength="120" />
            <flux:input wire:model="email" type="email" label="Email" required maxlength="150" />
            <flux:select wire:model="role" label="Rol">
                @foreach (App\Enums\UserRole::cases() as $r)
                    <flux:select.option :value="$r->value">{{ $r->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="save-user">
                    Guardar
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal: link de invitación --}}
    <flux:modal name="user-invite" class="md:w-[40rem]">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Link de invitación</flux:heading>
                <flux:text class="mt-1">
                    Comparte este link con la persona. Sirve para que defina su contraseña por primera vez.
                    El link expira según la configuración de Fortify (60 minutos por defecto).
                    Puedes regenerarlo desde la tabla cuando quieras.
                </flux:text>
            </div>

            @if ($inviteLink)
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                    <flux:text class="text-xs uppercase tracking-wide text-zinc-500 mb-2">URL</flux:text>
                    <div class="break-all rounded bg-zinc-50 dark:bg-zinc-900 p-3 font-mono text-xs">
                        {{ $inviteLink }}
                    </div>
                    <div class="mt-3 flex justify-end">
                        <flux:button
                            type="button"
                            variant="ghost"
                            size="sm"
                            icon="clipboard-document"
                            x-on:click="navigator.clipboard.writeText('{{ $inviteLink }}'); $flux.toast({ text: 'Link copiado.', variant: 'success' })"
                        >
                            Copiar
                        </flux:button>
                    </div>
                </div>
            @endif

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="primary">Listo</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    {{-- Modal: deshabilitar / habilitar --}}
    <flux:modal name="user-disable" class="md:w-[32rem]">
        @php
            $target = $disablingId ? \App\Models\User::find($disablingId) : null;
        @endphp

        @if ($target)
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">
                        {{ $target->isDisabled() ? 'Habilitar' : 'Deshabilitar' }} usuario
                    </flux:heading>
                    <flux:text class="mt-2">
                        @if ($target->isDisabled())
                            <strong>{{ $target->name }}</strong> volverá a poder iniciar sesión.
                        @else
                            <strong>{{ $target->name }}</strong> no podrá iniciar sesión, pero su historial
                            (movimientos, órdenes, etc.) se mantiene intacto.
                        @endif
                    </flux:text>
                </div>

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancelar</flux:button>
                    </flux:modal.close>
                    <flux:button
                        wire:click="toggleDisabled"
                        :variant="$target->isDisabled() ? 'primary' : 'danger'"
                        data-test="confirm-toggle-disabled"
                    >
                        {{ $target->isDisabled() ? 'Habilitar' : 'Deshabilitar' }}
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Modal: eliminar --}}
    <flux:modal name="user-delete" class="md:w-[32rem]">
        @php
            $target = $deletingId ? \App\Models\User::find($deletingId) : null;
        @endphp

        @if ($target)
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">Eliminar usuario</flux:heading>
                    <flux:text class="mt-2">
                        Vas a eliminar a <strong>{{ $target->name }}</strong> permanentemente.
                        Si tiene registros (movimientos, órdenes), la operación fallará y conviene deshabilitarlo.
                    </flux:text>
                </div>

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancelar</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="delete" data-test="confirm-delete-user">
                        Eliminar
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</section>
