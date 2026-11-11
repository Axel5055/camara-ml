@props([
    'sidebar' => false,
])

<a {{ $attributes->class('flex flex-1 items-center gap-3') }}>
    <x-app-logo-image class="size-11 shrink-0 p-0.5" />

    <span class="grid leading-tight">
        <span class="text-base font-semibold text-white">ML Grupo</span>
        <span class="text-[10px] font-medium uppercase tracking-[0.25em] text-zinc-400">Comercial</span>
        <span class="mt-0.5 text-[10px] text-zinc-500" data-test="app-version">v{{ config('app.version') }}</span>
    </span>
</a>
