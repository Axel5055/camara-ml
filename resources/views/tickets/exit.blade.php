<x-tickets.layout :title="'Ticket '.$exit->reference" heading="SALIDA DE MERCANCÍA" :reference="$exit->reference">
    <div class="row"><span>Fecha</span><span>{{ $exit->occurred_at->format('d/m/Y H:i') }}</span></div>
    <div class="row"><span>Origen</span><span>{{ $origin->name }} ({{ $origin->branch->name }})</span></div>
    <div class="row"><span>Destino</span><span>{{ $destBranch }}{{ $destChamber ? ' / '.$destChamber : '' }}</span></div>
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

    @if ($hasPartial)
        <div class="small">Incluye retiros parciales.</div>
    @endif

    <hr class="rule">
    <div class="row total"><span>Cajas</span><span>{{ number_format($exit->total_boxes) }}</span></div>
    <div class="row total"><span>Peso total</span><span>{{ number_format((float) $exit->total_weight_kg, 3) }} kg</span></div>
</x-tickets.layout>
