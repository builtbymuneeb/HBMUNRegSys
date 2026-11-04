{{-- Theme tiles. Tapping one previews and saves it straight away. --}}
<div x-data="{ theme: localStorage.getItem('hb-theme') || @js(\App\Support\Themes::DEFAULT) }" x-on:hb-theme.window="theme = $event.detail" class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 {{ $class ?? '' }}" role="radiogroup" aria-label="{{ __('Theme') }}">
    @foreach (\App\Support\Themes::all() as $key => $theme)
        <button type="button" role="radio" x-bind:aria-checked="theme === @js($key)" x-on:click="theme = @js($key); window.hbApplyTheme(@js($key), true)" data-test="theme-{{ $key }}"
            class="relative flex flex-col gap-2 rounded-2xl border border-zinc-200 bg-white p-1.5 pb-2 text-center text-[13px] font-semibold text-zinc-800 transition dark:border-white/10 dark:bg-white/5 dark:text-white"
            x-bind:class="theme === @js($key) && 'ring-2 ring-accent border-transparent'">
            <span class="relative block h-24 overflow-hidden rounded-xl border border-black/10" style="background: {{ $theme['bg'] }}">
                <span class="absolute inset-x-1.5 top-1.5 h-9 rounded-md" style="background: {{ $theme['hero'] }}"></span>
                <span class="absolute inset-x-1.5 top-12 h-3.5 rounded" style="background: {{ $theme['surface'] }}"></span>
                <span class="absolute inset-x-1.5 top-[4.25rem] h-3.5 rounded" style="background: {{ $theme['surface'] }}"></span>
                <span class="absolute bottom-1.5 left-1.5 h-1.5 w-2/5 rounded" style="background: {{ $theme['accent'] }}"></span>
            </span>
            {{ $theme['name'] }}
            <span x-show="theme === @js($key)" x-cloak class="absolute top-3 right-3 grid size-5 place-items-center rounded-full bg-accent text-accent-foreground">
                <flux:icon.check variant="micro" class="size-3.5" />
            </span>
        </button>
    @endforeach
</div>
