@props([
    'href',
    'phone',
    'sent' => null,
    'label' => __('Send on WhatsApp'),
    'size' => 'sm',
])

{{--
    Opens WhatsApp (through a staff route that records the click) with the message typed in;
    staff press send themselves. Shows whether this message has been sent on WhatsApp yet.
--}}
@if (! \App\Support\WhatsApp::number($phone))
    <span {{ $attributes->class(['text-xs text-zinc-500 dark:text-zinc-400']) }}>{{ __('Number can’t open in WhatsApp') }}</span>
@else
<div {{ $attributes->class(['flex flex-wrap items-center gap-x-2 gap-y-1']) }} x-on:click="setTimeout(() => $wire.$refresh(), 1500)">
    <flux:button
        :href="$href"
        target="_blank"
        rel="noopener"
        :size="$size"
        icon="chat-bubble-oval-left-ellipsis"
        :title="__('Opens WhatsApp with the message ready to send')"
    >{{ $sent && $label === __('Send on WhatsApp') ? __('Send again') : $label }}</flux:button>

    @if ($sent)
        <span class="text-xs text-green-600 dark:text-green-400" title="{{ __('Opened by :name', ['name' => $sent->sender?->name ?? __('a former staff member')]) }}">
            ✓ {{ __('WhatsApp :time', ['time' => $sent->created_at->timezone('Asia/Karachi')->format('j M, g:i a')]) }}
        </span>
    @else
        <span class="text-xs text-amber-600 dark:text-amber-300">{{ __('Not on WhatsApp yet') }}</span>
    @endif
</div>
@endif
