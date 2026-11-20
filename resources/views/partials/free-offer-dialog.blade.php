@if (\App\Support\FreeRegistrationOffer::isActive())
    {{-- Tonight's free-registration offer. Opens by itself on the landing page once per visit. --}}
    <dialog
        id="free-offer-dialog"
        onclick="if (event.target === this) this.close()"
        class="m-auto w-[calc(100%-2rem)] max-w-md rounded-xs border border-hb-purple-line bg-[#120d18] p-0 text-white shadow-2xl backdrop:bg-black/75 backdrop:backdrop-blur-sm"
        aria-labelledby="free-offer-dialog-title"
        data-test="free-offer-dialog"
    >
        <div class="flex flex-col gap-5 p-6 sm:p-8">
            <div class="flex items-start justify-between gap-4">
                <div class="hb-eyebrow">{{ __('Tonight only') }}</div>
                <button type="button" onclick="this.closest('dialog').close()" class="-mt-3 -mr-3 flex size-10 shrink-0 items-center justify-center text-hb-soft hover:text-white" aria-label="{{ __('Close') }}">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                </button>
            </div>

            <div class="flex flex-col items-center gap-2 border border-hb-purple-line bg-hb-purple px-5 py-6 text-center">
                <div class="font-display text-[56px] leading-none font-bold">{{ __('FREE') }}</div>
                <div class="text-sm tracking-[0.2em] text-[#dccff0] uppercase">{{ __('registration') }}</div>
            </div>

            <div class="flex flex-col gap-2 text-center">
                <h2 id="free-offer-dialog-title" class="font-display text-xl font-semibold sm:text-2xl">{{ __('Register free until :end', ['end' => \App\Support\FreeRegistrationOffer::endsLabel()]) }}</h2>
                <p class="leading-relaxed text-hb-soft">{{ __('For the next few hours there is no fee: no payment and no screenshot. Delegations and individual delegates alike.') }}</p>
                <p class="text-sm text-hb-lavender">
                    {{ __('Ends in') }} <span data-free-offer-countdown data-ends-at="{{ \App\Support\FreeRegistrationOffer::endsAt()->toIso8601String() }}"></span>
                </p>
            </div>

            <div class="grid gap-3">
                <a href="{{ route('register') }}" class="hb-button-primary min-h-13 px-5 text-center text-base" data-test="free-offer-dialog-register">{{ __('Register free now') }}</a>
                <button type="button" onclick="this.closest('dialog').close()" class="hb-button-secondary min-h-12 px-5 text-center">{{ __('Maybe later') }}</button>
            </div>
        </div>
    </dialog>

    <script>
        (() => {
            const key = 'free-offer-dialog-seen';
            try { if (sessionStorage.getItem(key)) return; } catch {}

            setTimeout(() => {
                const dialog = document.getElementById('free-offer-dialog');
                if (!dialog || dialog.open || document.querySelector('dialog[open]')) return;
                dialog.showModal();
                try { sessionStorage.setItem(key, '1'); } catch {}
            }, 1200);
        })();
    </script>
@endif
