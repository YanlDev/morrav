@props([
    'target' => 'lookupSku',
    'wireModel' => 'scannedCode',
    'placeholder' => 'SKU-000001',
    'fallbackHint' => '¿No funciona la cámara?',
])

<div class="flex-1 flex flex-col items-center justify-center gap-6 p-6">
    <div
        id="qr-reader"
        class="w-full max-w-sm aspect-square rounded-2xl overflow-hidden bg-zinc-900 hidden [&_video]:!w-full [&_video]:!h-full [&_video]:!object-cover"
    ></div>

    <div
        id="qr-placeholder"
        class="w-full max-w-sm aspect-square rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex flex-col items-center justify-center gap-3 border-2 border-dashed border-zinc-300 dark:border-zinc-700"
    >
        <flux:icon.qr-code class="size-16 text-zinc-400" />
        <flux:text size="sm" class="text-zinc-500">Toca para escanear</flux:text>
    </div>

    <flux:button variant="primary" id="start-scan-btn" class="w-full max-w-xs" icon="camera">
        Activar cámara
    </flux:button>

    <details class="w-full max-w-xs">
        <summary class="text-sm text-zinc-500 cursor-pointer text-center">{{ $fallbackHint }}</summary>
        <div class="mt-3 flex gap-2">
            <flux:input wire:model="{{ $wireModel }}" placeholder="{{ $placeholder }}" wire:keydown.enter="{{ $target }}" />
            <flux:button variant="primary" wire:click="{{ $target }}" icon="arrow-right" />
        </div>
    </details>
</div>

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
    (function () {
        const startBtn = document.getElementById('start-scan-btn');
        const reader = document.getElementById('qr-reader');
        const placeholder = document.getElementById('qr-placeholder');
        let html5QrCode = null;

        if (! startBtn) return;

        startBtn.addEventListener('click', async () => {
            if (html5QrCode) return;

            placeholder.classList.add('hidden');
            reader.classList.remove('hidden');
            startBtn.style.display = 'none';

            html5QrCode = new Html5Qrcode('qr-reader', {
                // Solo procesar QR — descartar barcodes 1D acelera ~3x.
                formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
                // Safari iOS tiene BarcodeDetector nativo (hardware) — ~50x más rápido.
                experimentalFeatures: { useBarCodeDetectorIfSupported: true },
                verbose: false,
            });

            // qrbox dinámico: 80% del lado más corto del viewfinder, cuadrado.
            const qrboxFn = (vw, vh) => {
                const min = Math.min(vw, vh);
                const size = Math.floor(min * 0.8);
                return { width: size, height: size };
            };

            try {
                await html5QrCode.start(
                    { facingMode: 'environment' },
                    {
                        fps: 15,
                        qrbox: qrboxFn,
                        aspectRatio: 1.0,
                        disableFlip: true,
                        videoConstraints: {
                            facingMode: 'environment',
                            width: { ideal: 1280 },
                            height: { ideal: 1280 },
                        },
                    },
                    async (decoded) => {
                        reader.style.outline = '4px solid #10b981';
                        await html5QrCode.stop();
                        html5QrCode.clear();
                        @this.set('{{ $wireModel }}', decoded);
                        @this.{{ $target }}();
                    },
                    () => {}
                );
            } catch (err) {
                placeholder.classList.remove('hidden');
                reader.classList.add('hidden');
                startBtn.style.display = '';
                alert('No se pudo acceder a la cámara: ' + err);
            }
        });
    })();
</script>
