<x-tickets.layout :title="'Ticket '.$entry->reference" heading="ENTRADA DE MERCANCÍA" :reference="$entry->reference">
    <div class="row"><span>Fecha</span><span>{{ $entry->occurred_at->format('d/m/Y H:i') }}</span></div>
    <div class="row"><span>Cámara</span><span>{{ $chamber->name }}</span></div>
    <div class="row"><span>Sucursal</span><span>{{ $chamber->branch->name }}</span></div>
    <div class="row"><span>Registró</span><span>{{ $registeredBy }}</span></div>
    @if ($entry->notes)
        <div class="small">Obs.: {{ $entry->notes }}</div>
    @endif
    <hr class="rule">

    @foreach ($groups as $group)
        <div class="group">
            <div class="name">{{ $group['product'] }}</div>
            <div class="row"><span>{{ number_format($group['boxes']) }} cajas x {{ $group['weightPerBox'] }} kg</span><span>{{ $group['kg'] }} kg</span></div>
        </div>
    @endforeach

    <hr class="rule">
    <div class="row total"><span>Cajas</span><span>{{ number_format($entry->total_boxes) }}</span></div>
    <div class="row total"><span>Peso total</span><span>{{ number_format((float) $entry->total_weight_kg, 3) }} kg</span></div>
</x-tickets.layout>
