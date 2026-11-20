@props([
    'problem',
    'error' => null,
    'details' => [],
    // "popup" floats at the bottom of the screen; "inline" sits in the page as a quiet link.
    'mode' => 'popup',
])

@php
    $helpUrl = \App\Support\HelpWhatsApp::url($problem, $error, $details);
@endphp

@if ($mode === 'inline')
    <div class="text-center text-sm text-zinc-600 dark:text-zinc-400" data-test="help-inline">
        <span>{{ __('Stuck here?') }}</span>
        <flux:link :href="$helpUrl" target="_blank" rel="noopener" data-test="help-whatsapp-link">{{ __('Message us on WhatsApp') }}</flux:link>
    </div>
@else
    {{-- Room at the bottom so the page can scroll clear of the popup. --}}
    <div data-help-popup aria-hidden="true" class="h-48"></div>

    <div data-help-popup data-test="help-popup" role="dialog" aria-label="{{ __('Need help?') }}" class="fixed inset-x-0 bottom-0 z-50 flex justify-center p-4">
        <div class="w-full max-w-sm rounded-xl border border-zinc-200 bg-white p-4 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:heading>{{ __('Stuck here? We are here to help.') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Send us a WhatsApp message. We will fill in the problem and the error for you, so just tap send.') }}</flux:text>
                </div>
                <button type="button" class="text-zinc-400 hover:text-zinc-600" aria-label="{{ __('Close') }}" onclick="document.querySelectorAll('[data-help-popup]').forEach((el) => el.remove())" data-test="help-popup-close">
                    <flux:icon.x-mark class="size-5" />
                </button>
            </div>

            <flux:button :href="$helpUrl" target="_blank" rel="noopener" variant="primary" icon="chat-bubble-left-right" class="mt-3 w-full" data-test="help-whatsapp-button">
                {{ __('Send message on WhatsApp') }}
            </flux:button>
        </div>
    </div>
@endif
