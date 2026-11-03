<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $document['title'] }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #18181b; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        h2 { font-size: 12px; margin: 18px 0 6px; }
        .muted { color: #71717a; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 3px 8px 3px 0; vertical-align: top; }
        .meta td.label { color: #71717a; width: 120px; }
        .totals { margin: 12px 0 0; }
        .totals td { border: 1px solid #d4d4d8; padding: 8px 12px; width: 33%; }
        .totals .value { font-size: 15px; font-weight: bold; }
        .list { margin-top: 4px; }
        .list th { background: #f4f4f5; text-align: left; font-size: 9px; padding: 5px 6px; border-bottom: 1px solid #a1a1aa; }
        .list td { padding: 4px 6px; border-bottom: 1px solid #e4e4e7; }
        .end, .list th.end { text-align: right; }
        .note { margin-top: 4px; font-style: italic; color: #71717a; }
        .footer { margin-top: 20px; font-size: 8px; color: #a1a1aa; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="vertical-align: middle;">
                <h1>{{ $document['title'] }}</h1>
                <div class="muted">Generado el {{ $generatedAt }}</div>
            </td>
            <td style="width: 90px; text-align: right;">
                <img src="{{ $logo }}" alt="ML Grupo Comercial" style="width: 80px; height: 80px;">
            </td>
        </tr>
    </table>

    <table class="meta" style="margin-top: 10px;">
        @foreach (array_chunk($document['meta'], 2) as $pair)
            <tr>
                @foreach ($pair as [$label, $value])
                    <td class="label">{{ $label }}</td>
                    <td>{{ $value }}</td>
                @endforeach
            </tr>
        @endforeach
    </table>

    <table class="totals">
        <tr>
            @foreach ($document['totals'] as [$label, $value])
                <td><div class="muted">{{ $label }}</div><div class="value">{{ $value }}</div></td>
            @endforeach
        </tr>
    </table>

    @foreach ($sections as $section)
        <h2>{{ $section['title'] }}</h2>
        <table class="list">
            <thead>
                <tr>
                    @foreach ($section['columns'] as $column)
                        <th class="{{ $column['type'] === 'text' ? '' : 'end' }}">{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($section['shown'] as $row)
                    <tr>
                        @foreach ($section['columns'] as $i => $column)
                            @php($value = $row[$i])
                            <td class="{{ $column['type'] === 'text' ? '' : 'end' }}">
                                @if ($value === null)
                                    —
                                @elseif ($column['type'] === 'int')
                                    {{ number_format((int) $value) }}
                                @elseif ($column['type'] === 'weight')
                                    {{ number_format((float) $value, 3) }}
                                @elseif ($column['type'] === 'money')
                                    ${{ number_format((float) $value, 2) }}
                                @else
                                    {{ $value }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if ($section['count'] > $limit)
            <div class="note">Se muestran las primeras {{ number_format($limit) }} de {{ number_format($section['count']) }} filas. El archivo de Excel incluye todas.</div>
        @endif
    @endforeach

    <div class="footer">Sistema de cámaras de refrigeración e inventario</div>
</body>
</html>
