{{-- A committee's file, pulled out of its folder on the homepage (see hbOpenFile in welcome.blade.php). --}}
@php($about = \App\Support\CommitteeDescriptions::for($committee->code))
@if ($about)
    <dialog
        id="committee-dialog-{{ $committee->code }}"
        onclick="if (event.target === this) this.close()"
        class="hb-file-dialog m-auto max-h-[calc(100dvh-1rem)] w-[calc(100%-2rem)] max-w-2xl bg-transparent p-0 backdrop:bg-black/75 backdrop:backdrop-blur-sm"
        aria-labelledby="committee-dialog-{{ $committee->code }}-title"
    >
        <div class="hb-file-sheet flex flex-col gap-5 p-6 outline-none sm:p-9" tabindex="-1">
            <div class="flex items-center justify-between gap-4 border-b border-dashed border-[#1b1320]/30 pb-3">
                <div class="font-mono text-[10px] tracking-[0.2em] text-[#6b5f75] uppercase sm:text-[11px]">{{ __('Homebridge MUN') }} · {{ __('Committee file') }} {{ $committee->code === 'SPECIAL' ? __('Special') : $committee->code }}</div>
                <button type="button" onclick="this.closest('dialog').close()" class="-my-2 -mr-2 flex size-10 shrink-0 items-center justify-center text-[#3a3140] hover:text-black" aria-label="{{ __('Close') }}">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                </button>
            </div>

            <div class="flex items-start justify-between gap-4">
                <h2 id="committee-dialog-{{ $committee->code }}-title" class="font-display text-xl leading-snug font-semibold text-[#1b0a33] sm:text-2xl">{{ $about['title'] }}</h2>
                <span class="hb-stamp mt-1 hidden shrink-0 !bg-transparent font-display text-xs font-bold tracking-[0.25em] uppercase sm:inline-block" aria-hidden="true">{{ __('Open') }}</span>
            </div>

            @foreach ($about['paragraphs'] as $paragraph)
                <p class="leading-relaxed text-[#3a3140]">{{ $paragraph }}</p>
            @endforeach

            <a href="{{ route('register') }}" class="hb-file-button self-start">{{ __('Register a delegation') }}</a>
        </div>
    </dialog>
@endif
