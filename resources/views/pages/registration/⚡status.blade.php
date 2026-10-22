<?php

use App\Actions\Registrations\ResubmitPayment;
use App\Enums\RegistrationStatus;
use App\Models\Registration;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::public')] #[Title('Registration status')] class extends Component {
    use WithFileUploads;

    #[Url]
    public string $reference = '';

    public string $email = '';

    #[Locked]
    public ?int $registrationId = null;

    public $paymentScreenshot;

    public bool $resubmitted = false;

    /**
     * Find the registration matching the reference number and email.
     */
    public function lookup(): void
    {
        $this->validate([
            'reference' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $throttleKey = 'registration-status:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            throw ValidationException::withMessages([
                'reference' => __('Too many attempts. Please try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        RateLimiter::hit($throttleKey);

        $email = Str::lower(trim($this->email));

        $registration = Registration::query()
            ->where('reference', Str::upper(trim($this->reference)))
            ->where(fn ($query) => $query
                ->whereRaw('lower(contact_email) = ?', [$email])
                ->orWhereHas('delegates', fn ($delegates) => $delegates->whereRaw('lower(email) = ?', [$email])))
            ->first();

        if (! $registration) {
            $this->registrationId = null;

            throw ValidationException::withMessages([
                'reference' => __('We couldn’t find a registration with that reference number and email.'),
            ]);
        }

        $this->registrationId = $registration->id;
        $this->resubmitted = false;
    }

    #[Computed]
    public function registration(): ?Registration
    {
        return $this->registrationId ? Registration::withCount('delegates')->find($this->registrationId) : null;
    }

    /**
     * Upload a new payment screenshot for a rejected registration.
     */
    public function resubmit(ResubmitPayment $resubmitPayment): void
    {
        $registration = $this->registration;

        abort_unless($registration?->isRejected(), 403);

        $this->validate([
            'paymentScreenshot' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], attributes: ['paymentScreenshot' => 'payment screenshot']);

        $resubmitPayment->handle($registration, $this->paymentScreenshot);

        $this->reset('paymentScreenshot');
        $this->resubmitted = true;
        unset($this->registration);
    }

    public function startOver(): void
    {
        $this->reset();
    }
}; ?>

<div class="mx-auto flex max-w-2xl flex-col gap-10 px-6 pt-16 pb-28">
    <div class="flex flex-col gap-4">
        <div class="hb-eyebrow">{{ __('Registration status') }}</div>
        <h1 class="font-display text-4xl leading-tight font-semibold">{{ __('Where’s my seat?') }}</h1>
        <p class="leading-relaxed text-hb-muted">{{ __('Enter the reference number from your confirmation and the email used to register.') }}</p>
    </div>

    @if (! $this->registration)
        <form wire:submit="lookup" class="flex flex-col gap-5 border border-hb-line bg-hb-surface p-6" novalidate>
            <x-public.field :label="__('Reference number')" name="reference">
                <input type="text" wire:model="reference" class="hb-input uppercase" placeholder="HB26-0001" autocomplete="off">
            </x-public.field>
            <x-public.field :label="__('Email')" name="email">
                <input type="email" wire:model="email" class="hb-input" autocomplete="email">
            </x-public.field>
            <button type="submit" class="hb-button-primary self-start" data-test="check-status">
                {{ __('Check status') }}
            </button>
        </form>
    @else
        @php($registration = $this->registration)

        <section class="flex flex-col gap-6 border border-hb-line bg-hb-surface p-6" aria-live="polite">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="text-xs tracking-[0.18em] text-hb-subtle uppercase">{{ __('Reference') }}</div>
                    <div class="font-display text-2xl font-bold tracking-[0.08em]">{{ $registration->reference }}</div>
                </div>
                <span @class([
                    'rounded-xs px-3 py-1.5 text-sm font-semibold',
                    'bg-amber-300 text-amber-950' => $registration->status === RegistrationStatus::Pending,
                    'bg-emerald-300 text-emerald-950' => $registration->status === RegistrationStatus::Verified,
                    'bg-red-300 text-red-950' => $registration->status === RegistrationStatus::Rejected,
                ]) data-test="registration-status">{{ $registration->status->label() }}</span>
            </div>

            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div><dt class="text-hb-subtle">{{ __('Registered as') }}</dt><dd class="mt-1">{{ $registration->type->label() }}</dd></div>
                <div><dt class="text-hb-subtle">{{ __('Name') }}</dt><dd class="mt-1">{{ $registration->displayName() }}</dd></div>
                <div><dt class="text-hb-subtle">{{ __('Delegates') }}</dt><dd class="mt-1">{{ $registration->delegates_count }}</dd></div>
                <div><dt class="text-hb-subtle">{{ __('Amount') }}</dt><dd class="mt-1">PKR {{ number_format($registration->amount_due) }}</dd></div>
                <div><dt class="text-hb-subtle">{{ __('Submitted') }}</dt><dd class="mt-1">{{ $registration->created_at->timezone('Asia/Karachi')->format('j M Y, g:i a') }}</dd></div>
            </dl>

            @switch($registration->status)
                @case(RegistrationStatus::Pending)
                    <p class="leading-relaxed text-hb-muted">
                        {{ $resubmitted ? __('Thanks! Your new screenshot is with our registration team.') : __('Our registration team is verifying your payment.') }}
                        {{ __('You’ll receive an email once it’s done.') }}
                    </p>
                    @break

                @case(RegistrationStatus::Verified)
                    <p class="leading-relaxed text-hb-muted">{{ __('Your payment is verified. Each delegate has been emailed a link to set their password.') }}</p>
                    <a href="{{ route('login') }}" class="hb-button-primary self-start">{{ __('Log in') }}</a>
                    @break

                @case(RegistrationStatus::Rejected)
                    <div class="border border-red-400/50 bg-red-950/40 p-4">
                        <div class="text-sm font-semibold text-red-200">{{ __('We couldn’t verify your payment') }}</div>
                        <p class="mt-1.5 leading-relaxed text-red-100">{{ $registration->rejection_reason }}</p>
                    </div>

                    <form wire:submit="resubmit" class="flex flex-col gap-4">
                        <label for="new-payment-screenshot" class="flex min-h-32 cursor-pointer flex-col items-center justify-center gap-2 border border-dashed border-[#6e6a75] p-5 text-center has-focus-visible:outline-2 has-focus-visible:outline-[#8e6bc8]">
                            <span class="font-semibold">
                                {{ $paymentScreenshot && ! $errors->has('paymentScreenshot') ? $paymentScreenshot->getClientOriginalName() : __('Upload a new payment screenshot') }}
                            </span>
                            <span class="text-sm text-hb-subtle">{{ __('JPG, JPEG or PNG, up to 5 MB. Make sure it shows PKR :amount.', ['amount' => number_format($registration->amount_due)]) }}</span>
                            <span wire:loading wire:target="paymentScreenshot" class="text-sm text-hb-lavender">{{ __('Uploading…') }}</span>
                            <input id="new-payment-screenshot" type="file" wire:model="paymentScreenshot" accept=".jpg,.jpeg,.png" class="sr-only">
                        </label>
                        <x-public.error name="paymentScreenshot" />
                        <button type="submit" class="hb-button-primary self-start" wire:loading.attr="disabled" wire:target="resubmit,paymentScreenshot">
                            {{ __('Resubmit for verification') }}
                        </button>
                    </form>
                    @break
            @endswitch
        </section>

        <button type="button" wire:click="startOver" class="self-start text-sm text-hb-soft underline underline-offset-4 hover:text-white">
            {{ __('Check another registration') }}
        </button>
    @endif
</div>
