{{-- Shared filter bar. $show lists the filters to render. --}}
<div class="grid gap-3 md:grid-cols-3 lg:grid-cols-6">
    @if (in_array('dates', $show))
        <flux:input wire:model.live="dateFrom" type="date" label="Desde" />
        <flux:input wire:model.live="dateTo" type="date" label="Hasta" />
    @endif
    @if (in_array('chamber', $show))
        <flux:select wire:model.live="chamberId" label="{{ $chamberLabel ?? 'Cámara' }}">
            <flux:select.option value="">Todas</flux:select.option>
            @foreach ($chambers as $chamber)
                <flux:select.option :value="$chamber->id">{{ $chamber->branch->name }} · {{ $chamber->name }}</flux:select.option>
            @endforeach
        </flux:select>
    @endif
    @if (in_array('category', $show))
        <flux:select wire:model.live="categoryId" label="Categoría">
            <flux:select.option value="">Todas</flux:select.option>
            @foreach ($categories as $category)
                <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
            @endforeach
        </flux:select>
    @endif
    @if (in_array('product', $show))
        <x-product-search :products="$products" placeholder="Todos los productos" emptyMessage="No hay productos con ese nombre." data-test="report-product-search" wire:key="report-product-search-{{ $categoryId }}" />
    @endif
    @if (in_array('user', $show))
        <flux:select wire:model.live="userId" label="Usuario">
            <flux:select.option value="">Todos</flux:select.option>
            @foreach ($users as $user)
                <flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>
            @endforeach
        </flux:select>
    @endif
</div>
