@props(['products'])

<div
    {{ $attributes }}
    x-data="{
        items: @js($products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'code' => $p->code])->values()),
        query: '',
        open: false,
        active: 0,
        get matches() {
            const q = this.query.trim().toLowerCase();
            return this.items.filter((i) => ! q || i.name.toLowerCase().includes(q) || i.code.toLowerCase().includes(q)).slice(0, 50);
        },
        pick(item) {
            this.query = item.name;
            this.open = false;
            $wire.set('productId', item.id);
        },
        typed() {
            this.open = true;
            this.active = 0;
            const exact = this.items.find((i) => i.name.toLowerCase() === this.query.trim().toLowerCase());
            $wire.set('productId', exact ? exact.id : null);
        },
        sync() {
            const current = this.items.find((i) => i.id === $wire.productId);
            this.query = current ? current.name : (this.open ? this.query : '');
        },
    }"
    x-effect="$wire.productId; sync()"
    @click.outside="open = false"
    class="relative"
>
    <flux:field>
        <flux:label>Producto</flux:label>
        <flux:input
            x-model="query"
            x-on:input="typed()"
            x-on:focus="open = true"
            x-on:keydown.down.prevent="active = Math.min(active + 1, matches.length - 1)"
            x-on:keydown.up.prevent="active = Math.max(active - 1, 0)"
            x-on:keydown.enter.prevent="matches[active] && pick(matches[active])"
            x-on:keydown.escape="open = false"
            icon="magnifying-glass"
            placeholder="Escribe para buscar un producto"
            autocomplete="off"
            data-test="product-search"
        />
    </flux:field>
    <ul
        x-show="open"
        x-cloak
        class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-zinc-200 bg-white py-1 text-sm shadow-lg dark:border-zinc-700 dark:bg-zinc-800"
    >
        <template x-for="(item, index) in matches" :key="item.id">
            <li
                x-on:click="pick(item)"
                x-on:mouseenter="active = index"
                :class="index === active ? 'bg-zinc-100 dark:bg-zinc-700' : ''"
                class="flex cursor-pointer items-center justify-between gap-3 px-3 py-2"
            >
                <span x-text="item.name" class="text-zinc-900 dark:text-white"></span>
                <span x-text="item.code" class="text-xs text-zinc-500"></span>
            </li>
        </template>
        <li x-show="matches.length === 0" class="px-3 py-2 text-zinc-500">No hay productos con ese nombre. Solo se pueden registrar cajas de productos existentes.</li>
    </ul>
</div>
