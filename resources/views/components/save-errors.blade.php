@props(['fields'])

{{--
    Resumen de lo que impidió guardar, junto a los botones de registrar. En capturas largas los errores de cada
    campo (arriba en la página) quedan fuera de la vista y parecía que el botón no hacía nada. El mensaje de cada
    campo se queda en su campo; aquí solo se nombran los campos por corregir y se muestran los errores de la operación.
--}}
@php
    $operation = collect($errors->get('operation'))->unique()->values();
    $invalid = collect($fields)->filter(fn (string $label, string $key): bool => $errors->has($key))->values();
@endphp

@if ($operation->isNotEmpty() || $invalid->isNotEmpty())
    <div
        x-data
        x-init="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'center' }))"
        role="alert"
        class="flex items-start gap-3 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-800 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-200"
        data-test="save-errors"
    >
        <flux:icon name="exclamation-triangle" class="mt-0.5 size-5 shrink-0" />
        <div class="min-w-0 space-y-1">
            <div class="font-medium">No se pudo continuar.</div>
            @foreach ($operation as $message)
                <div>{{ $message }}</div>
            @endforeach
            @if ($invalid->isNotEmpty())
                <div>Corrige los campos marcados en rojo: {{ $invalid->implode(', ') }}.</div>
            @endif
        </div>
    </div>
@endif
