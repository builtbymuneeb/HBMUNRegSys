{{-- Asks the delegate to allow browser notifications, until they answer or dismiss it. --}}
<div x-data="{ state: window.hbNotifications.state() }" x-show="state === 'default'" x-cloak>
    <flux:callout icon="bell-alert" color="purple" inline>
        <flux:callout.heading>{{ __('Get notified about updates') }}</flux:callout.heading>
        <flux:callout.text>{{ __('Turn on notifications to hear about new announcements, study guides, your allocation and session changes, even when this tab is in the background.') }}</flux:callout.text>
        <x-slot name="actions">
            <flux:button size="sm" variant="primary" x-on:click="state = await window.hbNotifications.enable()">{{ __('Turn on') }}</flux:button>
            <flux:button size="sm" variant="ghost" x-on:click="window.hbNotifications.dismiss(); state = 'dismissed'">{{ __('Not now') }}</flux:button>
        </x-slot>
    </flux:callout>
</div>
