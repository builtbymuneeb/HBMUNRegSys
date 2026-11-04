{{-- Phone tab bar, like a native app. Hidden from tablet-landscape up, where the sidebar shows. --}}
<nav x-data="{ more: false, hash: location.hash }" x-on:hashchange.window="hash = location.hash" class="hb-tabbar fixed inset-x-0 bottom-0 z-40 border-t px-1 pt-1.5 backdrop-blur-xl lg:hidden" aria-label="{{ __('Main') }}" data-test="phone-tabs">
    <div class="mx-auto flex max-w-lg justify-around">
        @foreach ($tabs as $tab)
            @php($hashOnly = str_contains($tab['url'], '#') && request()->routeIs('dashboard'))
            <a href="{{ $hashOnly ? '#'.\Illuminate\Support\Str::after($tab['url'], '#') : $tab['url'] }}" @unless ($hashOnly) wire:navigate @endunless
                @if ($tab['key'] === 'home' && request()->routeIs('dashboard'))
                    x-bind:class="hash === '' || hash === '#' ? 'text-accent-content' : 'text-zinc-500 dark:text-zinc-400'" x-on:click="if (location.hash) { history.replaceState(null, '', location.pathname); hash = '' } window.scrollTo({ top: 0, behavior: 'smooth' })"
                @elseif ($hashOnly)
                    x-bind:class="hash === @js('#'.\Illuminate\Support\Str::after($tab['url'], '#')) ? 'text-accent-content' : 'text-zinc-500 dark:text-zinc-400'"
                @endif
                @class([
                    'relative flex w-16 flex-col items-center gap-0.5 rounded-xl py-1 text-[10.5px] font-semibold',
                    'text-accent-content' => $tab['current'] && ! ($tab['key'] === 'home' && request()->routeIs('dashboard')),
                    'text-zinc-500 dark:text-zinc-400' => ! $tab['current'] && ! $hashOnly,
                ])>
                <flux:icon :name="$tab['icon']" class="size-6" />
                {{ $tab['label'] }}
                @if ($tab['badge'])
                    <span class="absolute top-0 right-3 min-w-4 rounded-full bg-red-500 px-1 text-center text-[9.5px] leading-4 font-bold text-white">{{ $tab['badge'] > 99 ? '99+' : $tab['badge'] }}</span>
                @endif
            </a>
        @endforeach
        @if ($more)
            <button type="button" x-on:click="more = ! more" x-bind:aria-expanded="more" @class([
                'relative flex w-16 flex-col items-center gap-0.5 rounded-xl py-1 text-[10.5px] font-semibold',
                'text-accent-content' => collect($more)->contains('current', true),
                'text-zinc-500 dark:text-zinc-400' => ! collect($more)->contains('current', true),
            ]) data-test="phone-more">
                <flux:icon name="squares-2x2" class="size-6" />
                {{ __('More') }}
                @if (collect($more)->sum('badge'))
                    <span class="absolute top-0.5 right-4 size-2 rounded-full bg-red-500"></span>
                @endif
            </button>
        @endif
    </div>

    @if ($more)
        <template x-teleport="body">
            {{-- Sits above the page but below the tab bar, so tapping "More" again closes it. --}}
            <div x-show="more" x-cloak class="fixed inset-0 z-30 flex cursor-pointer items-end bg-black/50 pt-12 lg:hidden" x-on:click.self="more = false" x-on:keydown.escape.window="more = false" x-transition.opacity data-test="phone-more-sheet">
                <div class="max-h-full w-full cursor-auto overflow-y-auto rounded-t-3xl bg-white px-3 pt-2 pb-[calc(4.5rem+env(safe-area-inset-bottom))] dark:bg-zinc-900"
                    x-data="{ startY: null, dragY: 0 }"
                    x-on:touchstart="startY = $event.touches[0].clientY"
                    x-on:touchmove="if (startY !== null && $el.scrollTop <= 0) dragY = Math.max(0, $event.touches[0].clientY - startY)"
                    x-on:touchend="if (dragY > 80) more = false; startY = null; dragY = 0"
                    x-bind:style="dragY ? `transform: translateY(${dragY}px)` : ''"
                    x-transition:enter="transition duration-200" x-transition:enter-start="translate-y-8">
                    <div class="mx-auto mb-1 h-1.5 w-10 rounded-full bg-zinc-200 dark:bg-white/15"></div>
                    <div class="mb-2 flex items-center justify-between px-1">
                        <span class="text-[15px] font-semibold text-zinc-900 dark:text-white">{{ __('More') }}</span>
                        <button type="button" x-on:click="more = false" class="grid size-9 place-items-center rounded-full bg-zinc-100 text-zinc-600 dark:bg-white/10 dark:text-zinc-200" aria-label="{{ __('Close') }}" data-test="phone-more-close">
                            <flux:icon.x-mark class="size-5" />
                        </button>
                    </div>
                    <div class="hb-list">
                        @foreach ($more as $item)
                            <a href="{{ $item['url'] }}" wire:navigate x-on:click="more = false" class="hb-row text-[15px] font-medium text-zinc-800 dark:text-white">
                                <span class="hb-icon-tile"><flux:icon :name="$item['icon']" class="size-5" /></span>
                                <span class="flex-1">{{ $item['label'] }}</span>
                                @if ($item['badge'])
                                    <flux:badge size="sm" color="red">{{ $item['badge'] }}</flux:badge>
                                @endif
                                <flux:icon.chevron-right variant="micro" class="text-zinc-400" />
                            </a>
                        @endforeach
                        <a href="{{ route('home') }}" class="hb-row text-[15px] font-medium text-zinc-800 dark:text-white">
                            <span class="hb-icon-tile"><flux:icon name="globe-alt" class="size-5" /></span>
                            <span class="flex-1">{{ __('Conference website') }}</span>
                            <flux:icon.chevron-right variant="micro" class="text-zinc-400" />
                        </a>
                    </div>
                </div>
            </div>
        </template>
    @endif
</nav>
