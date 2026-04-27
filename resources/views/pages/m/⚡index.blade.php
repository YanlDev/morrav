<?php

use App\Models\Movement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.mobile')]
#[Title('Morrav')]
class extends Component {
    /**
     * Almacenes "tienda" disponibles donde el vendedor puede operar.
     */
    #[Computed]
    public function stores()
    {
        return Warehouse::query()
            ->where('type', 'store')
            ->where('active', true)
            ->orderBy('code')
            ->get();
    }

    /**
     * Cantidad de ventas que el usuario actual hizo hoy. Sirve de KPI
     * en la pantalla principal.
     */
    #[Computed]
    public function salesToday(): int
    {
        return Movement::query()
            ->where('type', 'sale')
            ->where('status', 'confirmed')
            ->where('created_by', Auth::id())
            ->whereDate('occurred_at', today())
            ->count();
    }
}; ?>

<div class="flex flex-col flex-1 px-5 py-6 gap-6">
    {{-- Cabecera con saludo + ventas del día --}}
    <header class="flex items-center justify-between">
        <div>
            <flux:text size="sm" class="text-zinc-500">Hola,</flux:text>
            <flux:heading size="lg">{{ auth()->user()->name }}</flux:heading>
        </div>

        <flux:dropdown align="end">
            <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
            <flux:menu>
                <flux:menu.item :href="route('dashboard')" icon="layout-grid">
                    Ver panel completo
                </flux:menu.item>
                <flux:menu.separator />
                <flux:menu.item href="/logout" icon="arrow-right-start-on-rectangle" data-flux-method="post">
                    Cerrar sesión
                </flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </header>

    {{-- Tarjeta de ventas del día --}}
    <div class="rounded-2xl bg-gradient-to-br from-[#8E1E3A] to-[#6e1530] text-white p-5 shadow-lg">
        <flux:text size="sm" class="text-white/80">Tus ventas de hoy</flux:text>
        <div class="mt-1 text-4xl font-bold">{{ $this->salesToday }}</div>
        <flux:text size="sm" class="text-white/70 mt-1">
            {{ now()->translatedFormat('l j \\d\\e F') }}
        </flux:text>
    </div>

    {{-- 4 botones de acción --}}
    <div class="grid grid-cols-2 gap-3">
        <a href="{{ route('m.sell') }}"
           class="flex flex-col items-center justify-center gap-3 rounded-2xl bg-white dark:bg-zinc-900 p-6 shadow-sm border border-zinc-200 dark:border-zinc-800 active:scale-[0.97] transition">
            <div class="size-14 rounded-full bg-[#8E1E3A]/10 flex items-center justify-center">
                <flux:icon.shopping-cart class="size-7 text-[#8E1E3A]" />
            </div>
            <span class="text-base font-semibold">Vender</span>
        </a>

        <a href="{{ route('m.lookup') }}"
           class="flex flex-col items-center justify-center gap-3 rounded-2xl bg-white dark:bg-zinc-900 p-6 shadow-sm border border-zinc-200 dark:border-zinc-800 active:scale-[0.97] transition">
            <div class="size-14 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                <flux:icon.magnifying-glass class="size-7 text-blue-600" />
            </div>
            <span class="text-base font-semibold">Consultar</span>
        </a>

        <a href="{{ route('m.damage') }}"
           class="flex flex-col items-center justify-center gap-3 rounded-2xl bg-white dark:bg-zinc-900 p-6 shadow-sm border border-zinc-200 dark:border-zinc-800 active:scale-[0.97] transition">
            <div class="size-14 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                <flux:icon.exclamation-triangle class="size-7 text-amber-600" />
            </div>
            <span class="text-base font-semibold">Reportar dañado</span>
        </a>

        <a href="{{ route('m.history') }}"
           class="flex flex-col items-center justify-center gap-3 rounded-2xl bg-white dark:bg-zinc-900 p-6 shadow-sm border border-zinc-200 dark:border-zinc-800 active:scale-[0.97] transition">
            <div class="size-14 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                <flux:icon.clock class="size-7 text-emerald-600" />
            </div>
            <span class="text-base font-semibold">Mis ventas</span>
        </a>
    </div>

    <div class="mt-auto text-center">
        <flux:text size="xs" class="text-zinc-400">
            Morrav Office S.A.C.
        </flux:text>
    </div>
</div>
