@props([
    'phone',
    'message',
    'label' => __('Send on WhatsApp'),
])

{{-- Opens WhatsApp on the person's number with the message typed in; staff press send themselves. --}}
@if ($link = \App\Support\WhatsApp::link($phone, $message))
    <flux:button
        :href="$link"
        target="_blank"
        rel="noopener"
        icon="chat-bubble-oval-left-ellipsis"
        :title="__('Opens WhatsApp with the message ready to send')"
        {{ $attributes->merge(['size' => 'sm']) }}
    >{{ $label }}</flux:button>
@endif
