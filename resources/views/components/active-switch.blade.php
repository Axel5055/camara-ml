@props(['title' => 'Activo', 'description' => '', 'model' => 'is_active'])

<div class="flex items-center justify-between gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
    <div>
        <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ $title }}</div>
        @if ($description !== '')
            <flux:text class="text-xs">{{ $description }}</flux:text>
        @endif
    </div>
    <flux:switch wire:model="{{ $model }}" />
</div>
