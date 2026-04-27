<?php

use App\Services\HomepageSettings;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Homepage')] class extends Component {
    use WithFileUploads;

    public ?TemporaryUploadedFile $hero = null;

    public ?TemporaryUploadedFile $hogar = null;

    public ?TemporaryUploadedFile $oficina = null;

    public ?TemporaryUploadedFile $barberias = null;

    public ?TemporaryUploadedFile $salones = null;

    public ?TemporaryUploadedFile $exterior = null;

    public ?TemporaryUploadedFile $comercios = null;

    public string $whatsappMain = '';

    public string $whatsappMainDisplay = '';

    public string $emailSales = '';

    public string $emailContracts = '';

    /** @var list<array<string, mixed>> */
    public array $stores = [];

    public function mount(): void
    {
        abort_unless((bool) auth()->user()?->canManageSystem(), 403);

        $settings = HomepageSettings::all();

        $this->whatsappMain = $settings['whatsapp_main'];
        $this->whatsappMainDisplay = $settings['whatsapp_main_display'];
        $this->emailSales = $settings['email_sales'];
        $this->emailContracts = $settings['email_contracts'];
        $this->stores = $settings['stores'];
    }

    public function addStore(): void
    {
        if (count($this->stores) >= 3) {
            return;
        }

        $this->stores[] = [
            'name' => '',
            'badge' => 'Tienda',
            'address' => '',
            'hours' => 'Lun – Sáb · 9:00 – 18:00',
            'phone' => '',
            'whatsapp' => '',
            'show_whatsapp' => false,
            'lat' => -15.4974,
            'lng' => -70.1313,
        ];
    }

    public function removeStore(int $index): void
    {
        if (count($this->stores) <= 1) {
            return;
        }

        unset($this->stores[$index]);
        $this->stores = array_values($this->stores);
    }

    /**
     * @return array<string, string>
     */
    private function imageMap(): array
    {
        return [
            'hero' => 'Hero-parallax.png',
            'hogar' => 'lineas/hogar.jpg',
            'oficina' => 'lineas/oficina.jpg',
            'barberias' => 'lineas/barberias.jpg',
            'salones' => 'lineas/salones.jpg',
            'exterior' => 'lineas/exterior.jpg',
            'comercios' => 'lineas/comercios.jpg',
        ];
    }

    public function save(): void
    {
        abort_unless((bool) auth()->user()?->canManageSystem(), 403);

        $rules = [
            'whatsappMain' => ['required', 'string', 'max:30'],
            'whatsappMainDisplay' => ['required', 'string', 'max:40'],
            'emailSales' => ['required', 'email', 'max:120'],
            'emailContracts' => ['nullable', 'email', 'max:120'],
            'stores' => ['required', 'array', 'min:1', 'max:3'],
            'stores.*.name' => ['required', 'string', 'max:80'],
            'stores.*.badge' => ['required', 'string', 'max:20'],
            'stores.*.address' => ['required', 'string', 'max:200'],
            'stores.*.hours' => ['required', 'string', 'max:80'],
            'stores.*.phone' => ['nullable', 'string', 'max:40'],
            'stores.*.whatsapp' => ['nullable', 'string', 'max:30'],
            'stores.*.show_whatsapp' => ['boolean'],
            'stores.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'stores.*.lng' => ['required', 'numeric', 'between:-180,180'],
        ];

        foreach (array_keys($this->imageMap()) as $key) {
            $rules[$key] = ['nullable', 'image', 'max:5120'];
        }

        $this->validate($rules);

        // Imágenes
        $imagesSaved = 0;

        foreach ($this->imageMap() as $property => $relative) {
            $file = $this->$property;

            if (! $file) {
                continue;
            }

            $target = public_path($relative);
            $dir = dirname($target);

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($target, $file->get());
            $imagesSaved++;
        }

        // Datos estructurados
        HomepageSettings::save([
            'whatsapp_main' => $this->whatsappMain,
            'whatsapp_main_display' => $this->whatsappMainDisplay,
            'email_sales' => $this->emailSales,
            'email_contracts' => $this->emailContracts,
            'stores' => array_map(fn (array $s): array => [
                'name' => $s['name'],
                'badge' => $s['badge'],
                'address' => $s['address'],
                'hours' => $s['hours'],
                'phone' => $s['phone'] ?? '',
                'whatsapp' => $s['whatsapp'] ?? '',
                'show_whatsapp' => (bool) ($s['show_whatsapp'] ?? false),
                'lat' => (float) $s['lat'],
                'lng' => (float) $s['lng'],
            ], $this->stores),
        ]);

        if ($imagesSaved > 0) {
            $this->reset(array_keys($this->imageMap()));
        }

        Flux::toast(
            variant: 'success',
            text: $imagesSaved > 0
                ? "Cambios guardados ({$imagesSaved} imágenes nuevas)."
                : 'Cambios guardados.'
        );
    }
}; ?>

<section class="w-full p-6">
    <div class="mx-auto flex max-w-5xl flex-col gap-6">
        <div>
            <flux:heading size="xl">Homepage</flux:heading>
            <flux:text class="mt-1">
                Edita el contenido de la página pública. Los cambios se reflejan al instante para los visitantes.
            </flux:text>
        </div>

        <form wire:submit="save" class="flex flex-col gap-6">

            {{-- ============ HERO ============ --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900/40">
                <flux:heading size="lg">Imagen de fondo del hero</flux:heading>
                <flux:text class="mt-1">Foto principal a pantalla completa al entrar al sitio.</flux:text>

                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <div class="min-w-0">
                        <flux:label>Imagen actual</flux:label>
                        <div class="mt-1 aspect-[16/9] overflow-hidden rounded-md border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900">
                            @if ($hero && $hero->isPreviewable())
                                <img src="{{ $hero->temporaryUrl() }}" alt="Vista previa" class="h-full w-full object-cover">
                            @elseif (file_exists(public_path('Hero-parallax.png')))
                                <img src="/Hero-parallax.png?v={{ filemtime(public_path('Hero-parallax.png')) }}" alt="Hero actual" class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full items-center justify-center text-sm text-zinc-500">Sin imagen</div>
                            @endif
                        </div>
                    </div>

                    <div class="flex min-w-0 flex-col gap-2">
                        <flux:label>Subir nueva imagen</flux:label>
                        <div class="flex min-w-0 items-center gap-2">
                            <label class="inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-md border border-zinc-300 bg-zinc-50 px-3 py-1.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                <flux:icon.arrow-up-tray class="size-4" />
                                Elegir archivo
                                <input type="file" wire:model="hero" accept="image/jpeg,image/png,image/webp" class="sr-only">
                            </label>
                            <span class="min-w-0 flex-1 truncate text-sm text-zinc-500" title="{{ $hero?->getClientOriginalName() }}">
                                {{ $hero?->getClientOriginalName() ?? 'Sin archivo seleccionado' }}
                            </span>
                        </div>
                        <flux:text size="sm" class="text-zinc-500">JPG, PNG o WEBP. Máximo 5 MB. Recomendado: 2400×1500 px.</flux:text>
                        @error('hero')
                            <flux:text size="sm" class="text-red-600">{{ $message }}</flux:text>
                        @enderror
                        <div wire:loading wire:target="hero" class="text-xs text-zinc-500">Subiendo…</div>
                    </div>
                </div>
            </div>

            {{-- ============ LÍNEAS DE TRABAJO ============ --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900/40">
                <flux:heading size="lg">Imágenes de líneas de trabajo</flux:heading>
                <flux:text class="mt-1">
                    Una imagen por cada categoría. Aparecen en la sección «Lo que fabricamos y suministramos».
                </flux:text>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        'hogar' => 'Hogar',
                        'oficina' => 'Oficina',
                        'barberias' => 'Barberías',
                        'salones' => 'Salones',
                        'exterior' => 'Exterior',
                        'comercios' => 'Comercios',
                    ] as $key => $label)
                        @php
                            $current = $this->{$key};
                            $relative = "lineas/{$key}.jpg";
                            $exists = file_exists(public_path($relative));
                        @endphp
                        <div class="flex gap-4 rounded-md border border-zinc-200 p-3 dark:border-zinc-700">
                            <div class="aspect-[4/3] w-32 shrink-0 overflow-hidden rounded bg-zinc-100 dark:bg-zinc-900">
                                @if ($current && $current->isPreviewable())
                                    <img src="{{ $current->temporaryUrl() }}" class="h-full w-full object-cover" alt="Vista previa de {{ $label }}">
                                @elseif ($exists)
                                    <img src="/{{ $relative }}?v={{ filemtime(public_path($relative)) }}" class="h-full w-full object-cover" alt="Imagen actual de {{ $label }}">
                                @else
                                    <div class="flex h-full items-center justify-center px-2 text-center text-xs text-zinc-500">Sin imagen<br>(usa CDN)</div>
                                @endif
                            </div>
                            <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                                <flux:label>{{ $label }}</flux:label>
                                <div class="flex min-w-0 items-center gap-2">
                                    <label class="inline-flex shrink-0 cursor-pointer items-center gap-1 rounded-md border border-zinc-300 bg-zinc-50 px-2.5 py-1 text-xs font-medium text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                        <flux:icon.arrow-up-tray class="size-3.5" />
                                        Elegir
                                        <input type="file" wire:model="{{ $key }}" accept="image/jpeg,image/png,image/webp" class="sr-only">
                                    </label>
                                    <span class="min-w-0 flex-1 truncate text-xs text-zinc-500" title="{{ $current?->getClientOriginalName() }}">
                                        {{ $current?->getClientOriginalName() ?? 'Sin archivo' }}
                                    </span>
                                </div>
                                @error($key)
                                    <flux:text size="xs" class="text-red-600">{{ $message }}</flux:text>
                                @enderror
                                <div wire:loading wire:target="{{ $key }}" class="text-xs text-zinc-500">Subiendo…</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- ============ DATOS DE CONTACTO ============ --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900/40">
                <flux:heading size="lg">Datos de contacto</flux:heading>
                <flux:text class="mt-1">
                    Aparecen en el botón flotante, sección de contacto y footer del sitio público.
                </flux:text>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <flux:input
                        wire:model="whatsappMain"
                        label="WhatsApp (formato internacional, sin +)"
                        description="Se usa para los enlaces. Ej: 51999000111"
                        placeholder="51999000111"
                    />
                    <flux:input
                        wire:model="whatsappMainDisplay"
                        label="WhatsApp (cómo se muestra)"
                        description="Texto visible en el sitio. Ej: +51 999 000 111"
                        placeholder="+51 999 000 111"
                    />
                    <flux:input
                        wire:model="emailSales"
                        type="email"
                        label="Email de ventas"
                        placeholder="ventas@morravoffice.com"
                    />
                    <flux:input
                        wire:model="emailContracts"
                        type="email"
                        label="Email de contratos (opcional)"
                        placeholder="contratos@morravoffice.com"
                    />
                </div>
            </div>

            {{-- ============ TIENDAS ============ --}}
            <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900/40">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <flux:heading size="lg">Tiendas</flux:heading>
                        <flux:text class="mt-1">
                            Mínimo 1, máximo 3. Aparecen en la sección «Tres puntos de atención» y en el mapa.
                        </flux:text>
                    </div>
                    <flux:button
                        type="button"
                        variant="primary"
                        size="sm"
                        icon="plus"
                        wire:click="addStore"
                        :disabled="count($stores) >= 3"
                    >
                        Agregar tienda
                    </flux:button>
                </div>

                @error('stores')
                    <flux:text size="sm" class="mt-2 text-red-600">{{ $message }}</flux:text>
                @enderror

                <div class="mt-5 flex flex-col gap-4">
                    @foreach ($stores as $i => $store)
                        <div class="rounded-md border border-zinc-200 p-4 dark:border-zinc-700" wire:key="store-{{ $i }}">
                            <div class="mb-4 flex items-center justify-between">
                                <flux:heading size="sm">Tienda #{{ $i + 1 }}</flux:heading>
                                <flux:button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    icon="trash"
                                    wire:click="removeStore({{ $i }})"
                                    :disabled="count($stores) <= 1"
                                >
                                    Quitar
                                </flux:button>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <flux:input
                                    wire:model="stores.{{ $i }}.name"
                                    label="Nombre"
                                    placeholder="TIENDA CENTRAL"
                                />
                                <flux:input
                                    wire:model="stores.{{ $i }}.badge"
                                    label="Etiqueta"
                                    description="Principal, Showroom, Almacén..."
                                    placeholder="Principal"
                                />
                                <flux:input
                                    wire:model="stores.{{ $i }}.address"
                                    label="Dirección"
                                    placeholder="Jr. San Román 845, Cercado"
                                    class="md:col-span-2"
                                />
                                <flux:input
                                    wire:model="stores.{{ $i }}.hours"
                                    label="Horario"
                                    placeholder="Lun – Sáb · 9:00 – 19:00"
                                />
                                <flux:input
                                    wire:model="stores.{{ $i }}.phone"
                                    label="Teléfono"
                                    placeholder="051 32 1234"
                                />
                                <div class="flex flex-col gap-2">
                                    <flux:input
                                        wire:model="stores.{{ $i }}.whatsapp"
                                        label="WhatsApp de la tienda"
                                        description="Sin +. Ej: 51999000111"
                                        placeholder="51999000111"
                                    />
                                    <flux:switch
                                        wire:model.live="stores.{{ $i }}.show_whatsapp"
                                        label="Mostrar botón de WhatsApp en el sitio"
                                    />
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <flux:input
                                        wire:model="stores.{{ $i }}.lat"
                                        label="Latitud"
                                        type="number"
                                        step="0.0001"
                                    />
                                    <flux:input
                                        wire:model="stores.{{ $i }}.lng"
                                        label="Longitud"
                                        type="number"
                                        step="0.0001"
                                    />
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <flux:text size="sm" class="text-zinc-500">
                    Los campos de imagen vacíos no se modifican.
                </flux:text>
                <flux:button type="submit" variant="primary" icon="cloud-arrow-up" data-test="save-homepage">
                    Guardar cambios
                </flux:button>
            </div>

        </form>
    </div>
</section>
