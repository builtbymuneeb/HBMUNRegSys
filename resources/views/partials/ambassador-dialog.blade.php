{{-- Opened by any button with onclick="document.getElementById('ambassador-dialog').showModal()" --}}
<dialog
    id="ambassador-dialog"
    onclick="if (event.target === this) this.close()"
    class="m-auto w-[calc(100%-2rem)] max-w-md rounded-xs border border-hb-line-strong bg-[#120d18] p-0 text-white shadow-2xl backdrop:bg-black/75 backdrop:backdrop-blur-sm"
    aria-labelledby="ambassador-dialog-title"
>
    <div class="flex flex-col gap-5 p-6 sm:p-8">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="hb-eyebrow">{{ __('Campus Ambassadors') }}</div>
                <h2 id="ambassador-dialog-title" class="mt-2 font-display text-xl font-semibold sm:text-2xl">{{ __('Which are you applying as?') }}</h2>
            </div>
            <button type="button" onclick="this.closest('dialog').close()" class="-mt-1 -mr-2 flex size-10 shrink-0 items-center justify-center text-hb-soft hover:text-white" aria-label="{{ __('Close') }}">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
            </button>
        </div>

        <div class="grid gap-3">
            <a href="{{ config('conference.ambassador_forms.private') }}" target="_blank" rel="noopener" class="hb-button-primary min-h-14 px-5 py-2.5 text-center text-base">{{ __('Apply as a Private Campus Ambassador') }}</a>
            <a href="{{ config('conference.ambassador_forms.hb') }}" target="_blank" rel="noopener" class="hb-button-secondary min-h-14 px-5 py-2.5 text-center text-base">{{ __('Apply as a HB Campus Ambassador') }}</a>
        </div>

        <p class="text-sm text-hb-subtle">{{ __('Each opens a short Google Form in a new tab.') }}</p>
    </div>
</dialog>
