@props(['title', 'heading', 'reference'])

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        @page { size: 72mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 3mm; width: 72mm; font: bold 13px/1.35 Arial, "Helvetica Neue", Helvetica, sans-serif; color: #000; background: #fff; -webkit-font-smoothing: none; text-rendering: geometricPrecision; }
        .center { text-align: center; }
        .title { font-size: 17px; font-weight: 900; }
        .logo { display: block; width: 23mm; height: auto; margin: 0 auto 2mm; }
        .rule { border: 0; border-top: 1px dashed #000; margin: 2mm 0; }
        .row { display: flex; justify-content: space-between; gap: 2mm; }
        .row span:last-child { text-align: right; }
        .group { margin-bottom: 1.5mm; }
        .name { font-size: 14px; font-weight: 900; word-break: break-word; }
        .total { font-size: 16px; font-weight: 900; }
        .small { font-size: 11px; }
        .actions { margin-top: 4mm; text-align: center; }
        @media print { .actions { display: none; } }
    </style>
</head>
<body>
    <div class="center"><img class="logo" src="{{ asset('images/ticket-logo.png') }}" alt="ML Grupo Comercial"></div>
    <div class="center">{{ $heading }}</div>
    <div class="center title">{{ $reference }}</div>
    <hr class="rule">

    {{ $slot }}

    <hr class="rule">
    <div class="center small">Ticket sin valor fiscal</div>
    @unless (request()->boolean('raw'))
        <div class="actions"><button type="button" onclick="window.print()">Imprimir</button></div>
    @endunless

    @if (request()->boolean('auto') && ! request()->boolean('raw'))
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 150));</script>
    @endif
</body>
</html>
