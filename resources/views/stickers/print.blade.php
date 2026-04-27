<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Imprimir etiquetas</title>
    <style>
        /* Tamaño físico: 50mm x 30mm por sticker. Pensado para impresora térmica
           con rollo de etiquetas 50x30. Se usa @page para fijar el formato por
           página y cada sticker se imprime en su propia página. */
        @page {
            size: 50mm 30mm;
            margin: 0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
            background: #f4f4f5;
        }

        .toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #18181b;
            color: #fff;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            z-index: 100;
        }

        .toolbar button,
        .toolbar a {
            background: #fff;
            color: #18181b;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .toolbar .ghost {
            background: transparent;
            color: #fff;
            border: 1px solid #52525b;
        }

        .toolbar h1 {
            font-size: 14px;
            font-weight: 600;
            flex: 1;
        }

        .preview {
            padding: 68px 20px 40px;
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            justify-content: center;
        }

        .sticker {
            width: 50mm;
            height: 30mm;
            background: #fff;
            padding: 2mm;
            display: flex;
            align-items: center;
            gap: 2mm;
            box-shadow: 0 0 0 1px #d4d4d8;
            page-break-after: always;
            break-after: page;
        }

        .sticker:last-child {
            page-break-after: auto;
            break-after: auto;
        }

        .qr {
            flex-shrink: 0;
            width: 24mm;
            height: 24mm;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qr canvas,
        .qr img {
            width: 100%;
            height: 100%;
        }

        .text {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .name {
            font-size: 9pt;
            font-weight: bold;
            line-height: 1.1;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .variant {
            font-size: 7pt;
            color: #333;
            margin-top: 0.8mm;
            line-height: 1.1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sku-code {
            font-size: 7pt;
            font-family: 'Courier New', monospace;
            margin-top: 1mm;
            font-weight: 600;
        }

        .empty {
            text-align: center;
            padding: 40px;
            color: #71717a;
        }

        @media print {
            .toolbar {
                display: none;
            }
            .preview {
                padding: 0;
                gap: 0;
                display: block;
            }
            .sticker {
                box-shadow: none;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <h1>Etiquetas ({{ count($stickers) }})</h1>
        <a href="javascript:history.back()" class="ghost">← Volver</a>
        <button onclick="window.print()">🖨️ Imprimir</button>
    </div>

    <div class="preview">
        @forelse ($stickers as $i => $sku)
            @php
                $url = $baseUrl.'/products/by-sku/'.$sku->internal_code;
            @endphp
            <div class="sticker">
                <div class="qr" data-qr="{{ $url }}"></div>
                <div class="text">
                    <div class="name">{{ $sku->product?->name ?? '—' }}</div>
                    @if ($sku->variant_name)
                        <div class="variant">{{ $sku->variant_name }}</div>
                    @endif
                    <div class="sku-code">{{ $sku->internal_code }}</div>
                </div>
            </div>
        @empty
            <div class="empty">
                No hay etiquetas para imprimir.
            </div>
        @endforelse
    </div>

    <script src="https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs/qrcode.min.js"></script>
    <script>
        // Genera QR en cada contenedor con data-qr="url".
        document.querySelectorAll('[data-qr]').forEach(function (el) {
            new QRCode(el, {
                text: el.dataset.qr,
                width: 200,
                height: 200,
                correctLevel: QRCode.CorrectLevel.M,
            });
        });
    </script>
</body>
</html>
