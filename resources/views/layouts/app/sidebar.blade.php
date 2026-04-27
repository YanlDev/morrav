<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" :href="route('home')" />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group heading="Operación" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        Dashboard
                    </flux:sidebar.item>
                    @can('viewAny', App\Models\Movement::class)
                        <flux:sidebar.item icon="chart-bar" :href="route('stock.index')" :current="request()->routeIs('stock.*')" wire:navigate>
                            Stock
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="arrows-right-left" :href="route('movements.index')" :current="request()->routeIs('movements.*')" wire:navigate>
                            Movimientos
                        </flux:sidebar.item>
                    @endcan
                    @can('viewAny', App\Models\RepairOrder::class)
                        <flux:sidebar.item icon="wrench-screwdriver" :href="route('repair-orders.index')" :current="request()->routeIs('repair-orders.*')" wire:navigate>
                            Reparaciones
                        </flux:sidebar.item>
                    @endcan
                    @can('viewAny', App\Models\Product::class)
                        <flux:sidebar.item icon="cube" :href="route('products.index')" :current="request()->routeIs('products.*')" wire:navigate>
                            Productos
                        </flux:sidebar.item>
                    @endcan
                    @can('create', App\Models\Product::class)
                        <flux:sidebar.item icon="printer" :href="route('stickers.index')" :current="request()->routeIs('stickers.index')" wire:navigate>
                            Impresión
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>

                @php
                    $user = auth()->user();
                    $canManageHomepage = (bool) $user->canManageSystem();
                    $canManageUsers = $user->isAdmin();
                    $showConfig = $user->can('create', App\Models\Warehouse::class)
                        || $user->can('create', App\Models\Family::class)
                        || $user->can('create', App\Models\Attribute::class)
                        || $canManageHomepage
                        || $canManageUsers;
                @endphp

                @if ($showConfig)
                    <flux:sidebar.group
                        heading="Configuración"
                        icon="cog-6-tooth"
                        expandable
                        :expanded="request()->routeIs('warehouses.*', 'families.*', 'attributes.*', 'homepage.*', 'users.*')"
                        class="grid"
                    >
                        @if ($canManageUsers)
                            <flux:sidebar.item icon="users" :href="route('users.index')" :current="request()->routeIs('users.*')" wire:navigate>
                                Usuarios
                            </flux:sidebar.item>
                        @endif
                        @can('create', App\Models\Warehouse::class)
                            <flux:sidebar.item icon="building-storefront" :href="route('warehouses.index')" :current="request()->routeIs('warehouses.*')" wire:navigate>
                                Almacenes
                            </flux:sidebar.item>
                        @endcan
                        @can('create', App\Models\Family::class)
                            <flux:sidebar.item icon="squares-2x2" :href="route('families.index')" :current="request()->routeIs('families.*')" wire:navigate>
                                Familias
                            </flux:sidebar.item>
                        @endcan
                        @can('create', App\Models\Attribute::class)
                            <flux:sidebar.item icon="tag" :href="route('attributes.index')" :current="request()->routeIs('attributes.*')" wire:navigate>
                                Atributos
                            </flux:sidebar.item>
                        @endcan
                        @if ($canManageHomepage)
                            <flux:sidebar.item icon="photo" :href="route('homepage.index')" :current="request()->routeIs('homepage.*')" wire:navigate>
                                Homepage
                            </flux:sidebar.item>
                        @endif
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
