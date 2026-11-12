@php
    $hbPercent = \App\Enums\RegistrationType::homebridgeDiscountPercent();
    $hbExampleSize = config('conference.delegation.min_delegates');
    $hbExampleFull = \App\Enums\RegistrationType::InstitutionDelegation->amountDueFor($hbExampleSize);
    $hbExampleDiscounted = \App\Enums\RegistrationType::InstitutionDelegation->amountDueFor($hbExampleSize, $hbExampleSize);
@endphp

@if ($hbPercent > 0)
    {{-- Opens by itself on the landing page once a day per visitor; also opened by any button with onclick="document.getElementById('hb-discount-dialog').showModal()" --}}
    <dialog
        id="hb-discount-dialog"
        onclick="if (event.target === this) this.close()"
        class="m-auto w-[calc(100%-2rem)] max-w-md rounded-xs border border-hb-purple-line bg-[#120d18] p-0 text-white shadow-2xl backdrop:bg-black/75 backdrop:backdrop-blur-sm"
        aria-labelledby="hb-discount-dialog-title"
        data-test="hb-discount-dialog"
    >
        <div class="flex flex-col gap-5 p-6 sm:p-8">
            <div class="flex items-start justify-between gap-4">
                <div class="hb-eyebrow">{{ __('For Homebridge students') }}</div>
                <button type="button" onclick="this.closest('dialog').close()" class="-mt-3 -mr-3 flex size-10 shrink-0 items-center justify-center text-hb-soft hover:text-white" aria-label="{{ __('Close') }}">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                </button>
            </div>

            <div class="flex flex-col items-center gap-2 border border-hb-purple-line bg-hb-purple px-5 py-6 text-center">
                <div class="font-display text-[56px] leading-none font-bold">{{ $hbPercent }}%</div>
                <div class="text-sm tracking-[0.2em] text-[#dccff0] uppercase">{{ __('off') }}</div>
            </div>

            <div class="flex flex-col gap-2 text-center">
                <h2 id="hb-discount-dialog-title" class="font-display text-xl font-semibold sm:text-2xl">{{ __(':percent% off for HB delegations', ['percent' => $hbPercent]) }}</h2>
                <p class="leading-relaxed text-hb-soft">
                    {{ __('Register in a delegation and every Homebridge student gets :percent% off. If the whole team is from Homebridge, the delegation fee is :percent% off too.', ['percent' => $hbPercent]) }}
                </p>
                <p class="text-sm text-hb-lavender">
                    {{ __('A team of :count from HB pays PKR :discounted instead of PKR :full.', ['count' => $hbExampleSize, 'discounted' => number_format($hbExampleDiscounted), 'full' => number_format($hbExampleFull)]) }}
                </p>
            </div>

            <div class="grid gap-3">
                <a href="{{ route('register', ['type' => \App\Enums\RegistrationType::InstitutionDelegation->value]) }}" class="hb-button-primary min-h-13 px-5 text-center text-base">{{ __('Register a delegation') }}</a>
                <button type="button" onclick="this.closest('dialog').close()" class="hb-button-secondary min-h-12 px-5 text-center">{{ __('Maybe later') }}</button>
            </div>

            <p class="text-center text-xs text-hb-subtle">{{ __('Individual delegates pay the full fee. Tick “Homebridge student” for each HB member on the form.') }}</p>
        </div>
    </dialog>

    <script>
        (() => {
            const key = 'hb-discount-dialog-seen';
            const day = 24 * 60 * 60 * 1000;
            let seenAt = 0;
            try { seenAt = Number(localStorage.getItem(key)) || 0; } catch {}
            if (Date.now() - seenAt < day) return;

            setTimeout(() => {
                const dialog = document.getElementById('hb-discount-dialog');
                if (!dialog || dialog.open || document.querySelector('dialog[open]')) return;
                dialog.showModal();
                try { localStorage.setItem(key, String(Date.now())); } catch {}
            }, 1500);
        })();
    </script>
@endif
