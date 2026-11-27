@php
    $reading = $slot['reading'];
@endphp

@if ($reading === null)
    @if ($slot['status'] === 'missing')
        <flux:badge size="sm" color="zinc" data-test="temperature-missing">Sin registro</flux:badge>
    @elseif ($slot['status'] === 'pending')
        <span class="text-sm text-zinc-500" data-test="temperature-pending">Pendiente</span>
    @else
        <span class="text-sm text-zinc-400">—</span>
    @endif
@else
    <div class="space-y-1" data-test="temperature-reading">
        <div class="flex flex-wrap items-center gap-2">
            @if ($reading->effectiveTemperature() === null)
                <flux:badge size="sm" color="amber">No se pudo medir</flux:badge>
            @else
                <span class="text-lg font-semibold tabular-nums {{ $reading->level()->textClass() }}">{{ $reading->effectiveTemperature() }} °C</span>
            @endif
            @if ($reading->level()?->isAlert())
                <flux:badge size="sm" :color="$reading->level()->color()" data-test="temperature-level-{{ $reading->level()->value }}">{{ $reading->level()->label() }}</flux:badge>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-x-1.5 text-sm text-zinc-600 dark:text-zinc-300" data-test="temperature-time">
            <flux:icon.clock class="size-4 shrink-0" />
            <span>Registrada a las <span class="font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $reading->recorded_at->format('H:i') }}</span></span>
            @unless ($ownOnly)
                <span class="text-zinc-500">· {{ $reading->user->name }}</span>
            @endunless
        </div>
        @if ($reading->unmeasured_reason !== null)
            <div class="break-words text-xs text-zinc-600 dark:text-zinc-300">Motivo: {{ $reading->unmeasured_reason }}</div>
        @endif
        @if ($reading->corrections->isNotEmpty())
            <div class="space-y-0.5 border-s-2 border-sky-500/50 ps-2 text-xs text-zinc-500" data-test="temperature-corrections">
                <div>Capturado: {{ $reading->temperature === null ? 'no se pudo medir' : $reading->temperature.' °C' }}</div>
                @foreach ($reading->corrections as $correction)
                    <div class="break-words">Corregido a {{ $correction->temperature }} °C por {{ $correction->user->name }} ({{ $correction->created_at->format('d/m/Y H:i') }}): {{ $correction->reason }}</div>
                @endforeach
            </div>
        @endif
        @if ($canCorrect)
            <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="askToCorrect({{ $reading->id }})" data-test="temperature-correct">Corregir</flux:button>
        @endif
    </div>
@endif
