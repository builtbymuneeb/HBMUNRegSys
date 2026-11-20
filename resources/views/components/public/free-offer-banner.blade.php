@props(['compact' => false])

@php($endsAt = \App\Support\FreeRegistrationOffer::endsAt())

{{-- Tonight's free-registration offer. Only rendered while it is on; the countdown runs in the browser. --}}
<div {{ $attributes->class('flex flex-col gap-3 border border-hb-purple-line bg-hb-purple p-5') }} data-test="free-offer-banner">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="hb-eyebrow">{{ __('Tonight only') }}</div>
        <div class="text-sm font-semibold text-[#dccff0]">
            {{ __('Ends in') }} <span data-free-offer-countdown data-ends-at="{{ $endsAt->toIso8601String() }}">{{ now()->diff($endsAt)->format('%hh %Im') }}</span>
        </div>
    </div>
    <div class="font-display text-2xl leading-tight font-bold sm:text-3xl">{{ __('Registration is FREE until :end', ['end' => \App\Support\FreeRegistrationOffer::endsLabel()]) }}</div>
    <p class="leading-relaxed text-[#dccff0]">{{ __('No fee, no payment, no screenshot. Fill in your details, confirm, and your seat is in.') }}</p>
    @unless ($compact)
        <div class="grid gap-3 sm:flex sm:flex-wrap">
            <a href="{{ route('register') }}" class="hb-button-primary min-h-13 px-8 text-base" data-test="free-offer-register">{{ __('Register free now') }}</a>
        </div>
    @endunless
</div>

@once
    <script>
        (() => {
            const tick = () => document.querySelectorAll('[data-free-offer-countdown]').forEach((el) => {
                const left = Math.max(0, new Date(el.dataset.endsAt) - Date.now());
                const h = Math.floor(left / 3600000), m = Math.floor(left / 60000) % 60, s = Math.floor(left / 1000) % 60;
                el.textContent = left === 0 ? @js(__('now')) : `${h}h ${String(m).padStart(2, '0')}m ${String(s).padStart(2, '0')}s`;
            });
            tick();
            setInterval(tick, 1000);
        })();
    </script>
@endonce
