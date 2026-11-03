@props([
    'icon',
    'title',
    'description' => null,
    'mono' => false,
])

<div {{ $attributes->class('flex flex-wrap items-center justify-between gap-4') }}>
    <div class="flex min-w-0 items-center gap-4">
        <div class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-accent/10 text-accent-content">
            <flux:icon :name="$icon" class="size-8" />
        </div>

        <div class="min-w-0">
            <flux:heading size="xl" level="1" @class(['font-mono' => $mono])>{{ $title }}</flux:heading>
            @if ($description)
                <flux:text>{{ $description }}</flux:text>
            @endif
        </div>
    </div>

    @if (! $slot->isEmpty())
        <div class="flex flex-wrap items-center gap-2">
            {{ $slot }}
        </div>
    @endif
</div>
