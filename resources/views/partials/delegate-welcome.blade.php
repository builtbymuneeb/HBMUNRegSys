{{-- Once per account: a campus ambassador whose delegate registration is verified learns the dashboard now has delegate features. --}}
<div x-data="{ key: 'hb-delegate-welcome-{{ $user->id }}', open: false }" x-init="open = ! localStorage.getItem(key)">
    {{-- Teleported to <body> so it sits above the phone tab bar. --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-end justify-center bg-black/50 sm:items-center" x-transition.opacity data-test="delegate-welcome">
            <div class="w-full max-w-lg rounded-t-3xl bg-white p-4 pb-[max(1.25rem,env(safe-area-inset-bottom))] shadow-2xl sm:rounded-3xl sm:p-6 dark:bg-zinc-900" x-transition:enter="transition duration-300" x-transition:enter-start="translate-y-8 opacity-0">
                <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-zinc-200 sm:hidden dark:bg-white/15"></div>
                <h2 class="mb-2 px-1 font-display text-2xl font-semibold text-zinc-900 dark:text-white">{{ __('You’re a delegate now too!') }}</h2>
                <p class="mb-4 px-1 text-[15px] text-zinc-600 dark:text-zinc-300">
                    {{ __('Your delegate registration is verified, so this dashboard now shows your delegate features: your committee allocation, sessions, study guides and announcements.') }}
                </p>
                <p class="mb-4 px-1 text-[15px] text-zinc-600 dark:text-zinc-300">
                    {{ __('Your campus ambassador portal is still here. Open it from the “Campus ambassador portal” link at the top of this page to keep doing tasks and earning points.') }}
                </p>
                <button type="button" class="w-full rounded-2xl bg-accent py-3.5 font-semibold text-accent-foreground"
                    x-on:click="localStorage.setItem(key, '1'); open = false" data-test="delegate-welcome-dismiss">
                    {{ __('Got it') }}
                </button>
            </div>
        </div>
    </template>
</div>
