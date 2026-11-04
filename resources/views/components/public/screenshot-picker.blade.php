{{-- Pick up to six payment screenshots; each file uploads on its own into the component's $newScreenshot. --}}
@props(['screenshots', 'hint', 'id' => 'payment-screenshot'])

@php($screenshotCount = count($screenshots))
<div class="flex flex-col gap-3" @if ($errors->has('paymentScreenshots') || $errors->has('paymentScreenshots.*') || $errors->has('newScreenshot')) data-invalid @endif
    x-data="{
        uploading: false,
        async pick(input) {
            const files = [...input.files];
            input.value = '';
            this.uploading = true;
            for (const file of files) {
                await new Promise((done) => this.$wire.$upload('newScreenshot', file, done, done));
            }
            this.uploading = false;
        },
    }">
    @if ($screenshotCount > 0)
        <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3" data-test="payment-screenshots">
            @foreach ($screenshots as $index => $screenshot)
                <li class="flex flex-col gap-2 border border-hb-line bg-hb-surface p-2" wire:key="screenshot-{{ $index }}-{{ $screenshot->getFilename() }}">
                    @if (! $errors->has('paymentScreenshots.'.$index) && $screenshot->isPreviewable())
                        <img src="{{ $screenshot->temporaryUrl() }}" alt="{{ __('Payment screenshot :number', ['number' => $index + 1]) }}" class="h-32 w-full object-contain">
                    @endif
                    <div class="flex items-center justify-between gap-2 text-sm">
                        <span class="min-w-0 truncate text-hb-muted">{{ $screenshot->getClientOriginalName() }}</span>
                        <button type="button" wire:click="removeScreenshot({{ $index }})" class="shrink-0 text-hb-lavender hover:underline">{{ __('Remove') }}</button>
                    </div>
                    <x-public.error name="paymentScreenshots.{{ $index }}" />
                </li>
            @endforeach
        </ul>
    @endif

    @if ($screenshotCount < \App\Models\PaymentScreenshot::MAX_UPLOADS)
        <label for="{{ $id }}" class="relative flex {{ $screenshotCount ? 'min-h-24' : 'min-h-40' }} cursor-pointer flex-col items-center justify-center gap-2.5 border border-dashed border-[#6e6a75] bg-hb-surface p-6 text-center has-focus-visible:outline-2 has-focus-visible:outline-[#8e6bc8]">
            <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="#c9b6ea" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4" /><path d="m6 10 6-6 6 6" /><path d="M4 20h16" /></svg>
            <span class="font-semibold">
                {{ $screenshotCount ? __('Add another screenshot') : __('Upload payment screenshot') }}
            </span>
            <span class="max-w-md text-sm leading-relaxed text-hb-subtle">{{ $hint }}</span>
            <span x-show="uploading" x-cloak class="text-sm text-hb-lavender">{{ __('Uploading…') }}</span>
            <input id="{{ $id }}" type="file" multiple accept=".jpg,.jpeg,.png" class="sr-only" x-on:change.stop="pick($el)">
        </label>
    @else
        <p class="text-sm text-hb-subtle">{{ __('That’s the most screenshots you can upload (:max). Remove one to swap it.', ['max' => \App\Models\PaymentScreenshot::MAX_UPLOADS]) }}</p>
    @endif
    <x-public.error name="paymentScreenshots" />
    <x-public.error name="newScreenshot" />
</div>
