<x-tickets.layout :title="'Ticket '.$transfer->reference" heading="TRANSFERENCIA ENTRE CÁMARAS" :reference="$transfer->reference">
    <div class="row"><span>Fecha</span><span>{{ $transfer->dispatched_at->format('d/m/Y H:i') }}</span></div>
    <div class="row"><span>Origen</span><span>{{ $origin->name }} ({{ $origin->branch->name }})</span></div>
    <div class="row"><span>Destino</span><span>{{ $dest->name }} ({{ $dest->branch->name }})</span></div>
    <div class="row"><span>Solicitante</span><span>{{ $exit->requester_name }}</span></div>
    <div class="row"><span>Registró</span><span>{{ $registeredBy }}</span></div>
    @if ($exit->notes)
        <div class="small">Obs.: {{ $exit->notes }}</div>
    @endif
    <hr class="rule">

    @foreach ($groups as $group)
        <div class="group">
            <div class="name">{{ $group->product }}</div>
            <div class="row"><span>{{ number_format($group->boxes) }} {{ $group->boxes == 1 ? 'caja' : 'cajas' }}</span><span>{{ number_format((float) $group->kg, 3) }} kg</span></div>
        </div>
    @endforeach

    <hr class="rule">
    <div class="row total"><span>Cajas</span><span>{{ number_format($groups->sum('boxes')) }}</span></div>
    <div class="row total"><span>Peso total</span><span>{{ number_format((float) $groups->sum('kg'), 3) }} kg</span></div>
</x-tickets.layout>
