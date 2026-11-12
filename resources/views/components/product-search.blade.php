@props([
    'products',
    'model' => 'productId',
    'label' => 'Producto',
    'placeholder' => 'Escribe para buscar un producto',
    'emptyMessage' => 'No hay productos con ese nombre. Solo se pueden registrar cajas de productos existentes.',
    'dataTest' => 'product-search',
])

<div
    {{ $attributes }}
    data-items="{{ json_encode($products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'code' => (string) ($p->code ?? '')])->values()) }}"
    x-data="{
        model: @js($model),
        items: @js($products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'code' => (string) ($p->code ?? '')])->values()),
        query: '',
        open: false,
        active: 0,
        refreshing: false,
        async refresh() {
            if (this.refreshing) { return; }
            this.refreshing = true;
            try {
                await $wire.$refresh();
                this.items = JSON.parse(this.$root.dataset.items);
                this.sync();
            } finally {
                this.refreshing = false;
            }
        },
        get matches() {
            const words = this.normalize(this.query).split(/\s+/).filter(Boolean);
            return this.items.filter((i) => {
                const text = this.normalize(i.name + ' ' + i.code);
                return words.every((word) => text.includes(word));
            }).slice(0, 50);
        },
        normalize(text) {
            return String(text).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
        },
        pick(item) {
            this.query = item.name;
            this.open = false;
            $wire.set(this.model, item.id);
        },
        typed() {
            this.open = true;
            this.active = 0;
            const exact = this.items.find((i) => i.name.toLowerCase() === this.query.trim().toLowerCase());
            const next = exact ? exact.id : null;
            if ($wire[this.model] !== next) { $wire.set(this.model, next); }
        },
        sync() {
            const current = this.items.find((i) => i.id === $wire[this.model]);
            this.query = current ? current.name : (this.open ? this.query : '');
        },
    }"
    x-effect="$wire[model]; sync()"
    @click.outside="open = false"
    class="relative"
>
    <flux:field>
        <flux:label>{{ $label }}</flux:label>
        <div class="flex items-center gap-2">
        <flux:input
            class="flex-1"
            x-model="query"
            x-on:input="typed()"
            x-on:focus="open = true"
            x-on:keydown.down.prevent="active = Math.min(active + 1, matches.length - 1)"
            x-on:keydown.up.prevent="active = Math.max(active - 1, 0)"
            x-on:keydown.enter.prevent="matches[active] && pick(matches[active])"
            x-on:keydown.escape="open = false"
            icon="magnifying-glass"
            placeholder="{{ $placeholder }}"
            autocomplete="off"
            data-test="{{ $dataTest }}"
        />
        <flux:button type="button" icon="arrow-path" x-on:click="refresh()" x-bind:disabled="refreshing"
            x-bind:class="refreshing ? '[&_svg]:animate-spin' : ''"
            title="Actualizar la lista de productos" aria-label="Actualizar la lista de productos" data-test="{{ $dataTest }}-refresh" />
        </div>
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
        <li x-show="matches.length === 0" class="px-3 py-2 text-zinc-500">{{ $emptyMessage }}</li>
    </ul>
</div>
