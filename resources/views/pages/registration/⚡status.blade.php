<?php

use App\Actions\Registrations\ResubmitPayment;
use App\Enums\RegistrationStatus;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * The registration's reference number, an email on it, or the head's phone number.
     */
    #[Url(as: 'reference')]
    public string $identifier = '';

    #[Locked]
    public ?int $registrationId = null;

    /**
     * Which kind of detail found the registration: reference, email or phone.
     */
    #[Locked]
    public ?string $matchedBy = null;

    /**
     * A second, different detail, asked for before a new screenshot can replace the old one.
     */
    public string $confirmation = '';

    public $paymentScreenshot;

    public bool $resubmitted = false;

    /**
     * Find the registration matching the reference number, an email or the head's phone.
     */
    public function lookup(): void
    {
        $this->validate(
            ['identifier' => ['required', 'string', 'max:255']],
            attributes: ['identifier' => 'email, reference number or phone'],
        );

        $this->throttle('identifier');

        [$kind, $registration] = $this->find($this->identifier);

        if (! $registration) {
            $this->registrationId = null;

            throw ValidationException::withMessages([
                'identifier' => __('We couldn’t find a registration with that email, reference number or phone number.'),
            ]);
        }

        $this->registrationId = $registration->id;
        $this->matchedBy = $kind;
        $this->resubmitted = false;
    }

    #[Computed]
    public function registration(): ?Registration
    {
        return $this->registrationId ? Registration::withCount('delegates')->find($this->registrationId) : null;
    }

    /**
     * Upload a new payment screenshot for a rejected registration, once a second detail confirms who's asking.
     */
    public function resubmit(ResubmitPayment $resubmitPayment): void
    {
        $registration = $this->registration;

        abort_unless($registration?->isRejected(), 403);

        $this->validate([
            'confirmation' => ['required', 'string', 'max:255'],
            'paymentScreenshot' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], attributes: ['paymentScreenshot' => 'payment screenshot', 'confirmation' => 'second detail']);

        $this->throttle('confirmation');

        [$kind, $confirmed] = $this->find($this->confirmation, $registration);

        if (! $confirmed || $kind === $this->matchedBy) {
            throw ValidationException::withMessages([
                'confirmation' => $confirmed
                    ? __('Enter a different detail from the one you searched with.')
                    : __('That doesn’t match this registration.'),
            ]);
        }

        $resubmitPayment->handle($registration, $this->paymentScreenshot);

        $this->reset('paymentScreenshot', 'confirmation');
        $this->resubmitted = true;
        unset($this->registration);
    }

    public function startOver(): void
    {
        $this->reset();
    }

    /**
     * Work out which kind of detail was entered and find the registration it belongs to,
     * optionally only within one registration. Prefers the newest registration that isn't rejected.
     *
     * @return array{0: string, 1: Registration|null}
     */
    private function find(string $value, ?Registration $within = null): array
    {
        $value = trim($value);
        $digits = preg_replace('/\D/', '', $value) ?? '';

        [$kind, $constraint] = match (true) {
            str_contains($value, '@') => ['email', function (Builder $query) use ($value): void {
                $email = Str::lower($value);

                $query->whereRaw('lower(contact_email) = ?', [$email])
                    ->orWhereHas('delegates', fn (Builder $delegates) => $delegates->whereRaw('lower(email) = ?', [$email]));
            }],
            (bool) preg_match('/^[a-z]+\d*-\d+$/i', $value) => ['reference', fn (Builder $query) => $query->where('reference', Str::upper($value))],
            strlen($digits) >= 10 => ['phone', fn (Builder $query) => $query->whereRaw(
                "replace(replace(replace(replace(replace(contact_phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') like ?",
                ['%'.substr($digits, -10)],
            )],
            default => ['unknown', fn (Builder $query) => $query->whereRaw('1 = 0')],
        };

        $registration = Registration::query()
            ->when($within, fn (Builder $query) => $query->whereKey($within->id))
            ->where($constraint)
            ->orderByRaw('case when status = ? then 1 else 0 end', [RegistrationStatus::Rejected->value])
            ->latest('id')
            ->first();

        return [$kind, $registration];
    }

    private function throttle(string $field): void
    {
        $throttleKey = 'registration-status:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            throw ValidationException::withMessages([
                $field => __('Too many attempts. Please try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        RateLimiter::hit($throttleKey);
    }
}; ?>

<div class="mx-auto flex max-w-2xl flex-col gap-10 px-6 pt-16 pb-28">
    <div class="flex flex-col gap-4">
        <div class="hb-eyebrow">{{ __('Registration status') }}</div>
        <h1 class="font-display text-4xl leading-tight font-semibold">{{ __('Where’s my seat?') }}</h1>
        <p class="leading-relaxed text-hb-muted">{{ __('Enter any one of these: your reference number (e.g. HB26-0001), an email used in the registration, or the phone number of the delegation head or institution representative.') }}</p>
    </div>

    @if (! $this->registration)
        <form wire:submit="lookup" class="flex flex-col gap-5 border border-hb-line bg-hb-surface p-6" novalidate>
            <x-public.field :label="__('Email, reference number or head’s phone')" name="identifier">
                <input type="text" wire:model="identifier" class="hb-input" placeholder="HB26-0001 · name@gmail.com · 03XX XXXXXXX" autocomplete="off" autocapitalize="off" spellcheck="false">
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

                        <x-public.field :label="__('Confirm it’s you')" name="confirmation" :hint="match ($matchedBy) {
                            'reference' => __('Enter an email or the head’s phone number used in this registration.'),
                            'email' => __('Enter the reference number or the head’s phone number.'),
                            default => __('Enter the reference number or an email used in this registration.'),
                        }">
                            <input type="text" wire:model="confirmation" class="hb-input" autocomplete="off" autocapitalize="off" spellcheck="false">
                        </x-public.field>

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
