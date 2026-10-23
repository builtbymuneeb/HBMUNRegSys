<div
    x-data="{ offline: ! navigator.onLine, restored: false, timer: null }"
    x-on:offline.window="offline = true; restored = false; clearTimeout(timer)"
    x-on:online.window="offline = false; restored = true; clearTimeout(timer); timer = setTimeout(() => restored = false, 3000)"
    role="status"
    aria-live="polite"
    class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex justify-center px-4"
>
    <div x-show="offline" x-cloak x-transition.opacity class="pointer-events-auto flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-lg">
        <flux:icon.signal-slash variant="mini" />
        {{ __('No internet connection. Changes won’t be saved until you’re back online.') }}
    </div>
    <div x-show="restored" x-cloak x-transition.opacity class="pointer-events-auto flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-lg">
        <flux:icon.signal variant="mini" />
        {{ __('Back online.') }}
    </div>
</div>
