<x-tickets.layout title="Concentrado de solicitudes de salidas" heading="CONCENTRADO DE SOLICITUDES DE SALIDAS" :reference="$from->isSameDay($to) ? $from->format('d/m/Y') : $from->format('d/m/Y').' al '.$to->format('d/m/Y')">
    <div class="row"><span>Cámara</span><span>{{ $chamberLabel }}</span></div>
    <div class="row"><span>Sucursal</span><span>{{ $branchLabel }}</span></div>
    <div class="row"><span>Solicitudes</span><span>{{ number_format($requestsCount) }}</span></div>
    <div class="row"><span>Impreso</span><span>{{ now()->format('d/m/Y H:i') }}</span></div>
    <div class="row"><span>Imprimió</span><span>{{ $printedBy }}</span></div>
    <hr class="rule">

    @forelse ($branches as $branch => $products)
        <div class="group">
            <div class="name">SUCURSAL: {{ $branch }}</div>
            @foreach ($products as $product)
                <div>{{ $product->product }}</div>
                <div class="row"><span>{{ number_format($product->boxes) }} {{ $product->boxes == 1 ? 'caja' : 'cajas' }}</span><span>{{ number_format((float) $product->kg, 3) }} kg</span></div>
            @endforeach
            @if ($branches->count() > 1)
                <div class="row name"><span>Subtotal {{ number_format((int) $products->sum('boxes')) }} {{ $products->sum('boxes') == 1 ? 'caja' : 'cajas' }}</span><span>{{ number_format((float) $products->sum('kg'), 3) }} kg</span></div>
            @endif
        </div>
        @unless ($loop->last)
            <hr class="rule">
        @endunless
    @empty
        <div class="center">Sin solicitudes autorizadas en este periodo.</div>
    @endforelse

    <hr class="rule">
    <div class="row total"><span>Cajas</span><span>{{ number_format((int) $groups->sum('boxes')) }}</span></div>
    <div class="row total"><span>Peso total</span><span>{{ number_format((float) $groups->sum('kg'), 3) }} kg</span></div>
</x-tickets.layout>
