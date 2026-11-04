{{-- First visit: ask which look they like. Never shown again once a theme is saved. --}}
<div x-data="{ open: ! localStorage.getItem('hb-theme') }" x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-end justify-center bg-black/50 sm:items-center" x-transition.opacity data-test="theme-welcome">
    <div class="w-full max-w-lg rounded-t-3xl bg-white p-4 pb-[max(1.25rem,env(safe-area-inset-bottom))] shadow-2xl sm:rounded-3xl sm:p-6 dark:bg-zinc-900" x-transition:enter="transition duration-300" x-transition:enter-start="translate-y-8 opacity-0">
        <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-zinc-200 sm:hidden dark:bg-white/15"></div>
        <h2 class="mb-4 px-1 font-display text-2xl font-semibold text-zinc-900 dark:text-white">{{ __('Choose your look') }}</h2>
        @include('partials.theme-picker')
        <button type="button" class="mt-4 w-full rounded-2xl bg-accent py-3.5 font-semibold text-accent-foreground"
            x-on:click="if (! localStorage.getItem('hb-theme')) window.hbApplyTheme(document.documentElement.dataset.theme, true); open = false">
            {{ __('Continue') }}
        </button>
    </div>
</div>
