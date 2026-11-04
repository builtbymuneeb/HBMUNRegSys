{{-- Asks the delegate to allow browser notifications, until they answer or dismiss it. --}}
<div x-data="{ state: window.hbNotifications.state() }" x-show="state === 'default'" x-cloak>
    <div class="hb-list flex items-center gap-3 p-3">
        <span class="hb-icon-tile"><flux:icon name="bell-alert" class="size-5" /></span>
        <span class="min-w-0 flex-1 text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Get notified about updates') }}</span>
        <flux:button size="sm" variant="primary" x-on:click="state = await window.hbNotifications.enable()">{{ __('Turn on') }}</flux:button>
        <button type="button" class="p-1 text-zinc-400" x-on:click="window.hbNotifications.dismiss(); state = 'dismissed'" aria-label="{{ __('Not now') }}"><flux:icon.x-mark variant="micro" /></button>
    </div>
</div>
