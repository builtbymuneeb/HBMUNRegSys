@props([
    'options' => null,
    'taken' => [],
    'placeholder' => __('Search for a country…'),
    'variant' => 'public',
])

@php
    $staff = $variant === 'staff';
@endphp

{{-- Searchable, strict portfolio picker: whatever is typed, the bound value is only ever a listed option or empty. Defaults to every country. --}}
<div
    x-data="{
        value: '',
        query: '',
        open: false,
        active: 0,
        options: @js(\App\Support\Countries::options($options)),
        taken: @js(array_values($taken)),
        normalise(text) {
            return (text || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
        },
        get filtered() {
            const query = this.normalise(this.query);

            if (! query || query === this.normalise(this.value)) {
                return this.options;
            }

            const starts = this.options.filter(option => option.terms.some(term => term.startsWith(query)));
            const contains = this.options.filter(option => ! starts.includes(option) && option.terms.some(term => term.includes(query)));

            return [...starts, ...contains];
        },
        isTaken(name) {
            return name !== this.value && this.taken.includes(name);
        },
        show() {
            this.open = true;
            this.active = Math.max(0, this.filtered.findIndex(option => option.name === this.value));
            this.$nextTick(() => this.scrollToActive());
        },
        choose(option) {
            if (! option || this.isTaken(option.name)) {
                return;
            }

            this.value = option.name;
            this.query = option.name;
            this.open = false;
        },
        clear() {
            this.value = '';
            this.query = '';
            this.$refs.input.focus();
        },
        close() {
            if (! this.open) {
                return;
            }

            this.open = false;
            this.query = this.value || '';
        },
        move(step) {
            if (! this.open) {
                return this.show();
            }

            const count = this.filtered.length;

            if (count) {
                this.active = (this.active + step + count) % count;
                this.$nextTick(() => this.scrollToActive());
            }
        },
        scrollToActive() {
            this.$refs.list?.querySelector('[data-active]')?.scrollIntoView({ block: 'nearest' });
        },
    }"
    x-modelable="value"
    x-init="query = value || ''; $watch('value', next => { if (! open) query = next || '' })"
    x-on:click.outside="close()"
    x-on:focusout="if (! $el.contains($event.relatedTarget)) close()"
    {{ $attributes->whereStartsWith('wire:model') }}
    {{ $attributes->whereDoesntStartWith('wire:model')->class(['relative']) }}
>
    <input
        x-ref="input"
        type="text"
        role="combobox"
        autocomplete="off"
        spellcheck="false"
        x-model="query"
        x-bind:aria-expanded="open"
        x-on:focus="show(); $el.select()"
        x-on:click="if (! open) show()"
        x-on:input="open = true; active = 0"
        x-on:keydown.arrow-down.prevent="move(1)"
        x-on:keydown.arrow-up.prevent="move(-1)"
        x-on:keydown.enter.prevent="open ? choose(filtered[active]) : show()"
        x-on:keydown.escape="if (open) { $event.preventDefault(); $event.stopPropagation(); close() }"
        x-on:keydown.tab="close()"
        placeholder="{{ $placeholder }}"
        @class([
            'hb-input pr-9' => ! $staff,
            'h-10 w-full rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white px-3 pr-9 text-base text-zinc-700 shadow-xs placeholder-zinc-400 sm:text-sm dark:border-white/10 dark:bg-white/10 dark:text-zinc-300 dark:placeholder-zinc-400' => $staff,
        ])
    >

    <button
        type="button"
        x-show="value"
        x-cloak
        x-on:click="clear()"
        aria-label="{{ __('Clear') }}"
        @class([
            'absolute top-0 right-0 flex h-full w-9 items-center justify-center',
            'text-hb-subtle hover:text-white' => ! $staff,
            'text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200' => $staff,
        ])
    >&times;</button>

    <ul
        x-ref="list"
        x-show="open"
        x-cloak
        role="listbox"
        @class([
            'absolute inset-x-0 top-full z-40 mt-1 max-h-64 overflow-y-auto py-1 shadow-lg',
            'rounded-xs border border-[#3a3542] bg-hb-field text-[15px] text-white' => ! $staff,
            'rounded-lg border border-zinc-200 bg-white text-sm text-zinc-800 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100' => $staff,
        ])
    >
        <template x-for="(option, index) in filtered" :key="option.name">
            <li
                role="option"
                x-bind:aria-selected="option.name === value"
                x-bind:aria-disabled="isTaken(option.name)"
                x-bind:data-active="index === active ? true : null"
                x-on:mousedown.prevent
                x-on:click.prevent="choose(option)"
                x-on:mousemove="active = index"
                class="flex cursor-pointer items-center justify-between gap-3 px-3.5 py-2"
                x-bind:class="{
                    '{{ $staff ? 'bg-zinc-100 dark:bg-zinc-600' : 'bg-[#2c2634]' }}': index === active && ! isTaken(option.name),
                    'cursor-not-allowed opacity-45': isTaken(option.name),
                    'font-semibold': option.name === value,
                }"
            >
                <span x-text="option.name"></span>
                <span x-show="isTaken(option.name)" class="text-xs">{{ __('taken') }}</span>
                <span x-show="option.name === value" aria-hidden="true">&check;</span>
            </li>
        </template>
        <li x-show="! filtered.length" @class(['px-3.5 py-2', 'text-hb-subtle' => ! $staff, 'text-zinc-500 dark:text-zinc-400' => $staff])>
            {{ __('Nothing on the list matches that.') }}
        </li>
    </ul>
</div>
