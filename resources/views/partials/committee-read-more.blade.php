{{-- Clicks bubble up to the committee card, which opens its dialog. --}}
@if (\App\Support\CommitteeDescriptions::for($committee->code))
    <button type="button" class="hb-card-cta" aria-haspopup="dialog">
        <span><span class="hb-card-cta-view">{{ __('View') }} </span>{{ __('details') }}</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
    </button>
@endif
