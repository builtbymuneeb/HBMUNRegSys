<?php

use App\Actions\Registrations\SubmitRegistration;
use App\Enums\PortfolioType;
use App\Enums\RegistrationStatus;
use App\Enums\RegistrationType;
use App\Models\Committee;
use App\Models\Delegate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::public')] #[Title('Register')] class extends Component {
    use WithFileUploads;

    #[Url]
    public string $type = 'institution_delegation';

    /** @var array{organisation_name: string, city: string, name: string, cnic: string, phone: string, email: string} */
    public array $contact = [
        'organisation_name' => '',
        'city' => '',
        'name' => '',
        'cnic' => '',
        'phone' => '',
        'email' => '',
    ];

    public int $delegateCount = 3;

    /** @var list<array{name: string, cnic: string, email: string, phone: string, first_committee_id: string, second_committee_id: string, country_preference: string, personality_preference: string}> */
    public array $delegates = [];

    public $paymentScreenshot;

    public bool $confirmed = false;

    public ?string $submittedReference = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        if (! RegistrationType::tryFrom($this->type)) {
            $this->type = RegistrationType::InstitutionDelegation->value;
        }

        $this->syncDelegateCount();
    }

    /**
     * Switch between institution delegation, private delegate and private delegation.
     */
    public function updatedType(): void
    {
        if (! RegistrationType::tryFrom($this->type)) {
            $this->type = RegistrationType::InstitutionDelegation->value;
        }

        $this->resetValidation();
        $this->syncDelegateCount();
    }

    public function updatedDelegateCount(): void
    {
        $this->syncDelegateCount();
    }

    #[Computed]
    public function registrationType(): RegistrationType
    {
        return RegistrationType::from($this->type);
    }

    /**
     * @return Collection<int, Committee>
     */
    #[Computed]
    public function committees(): Collection
    {
        return Committee::ordered()->get();
    }

    #[Computed]
    public function amountDue(): int
    {
        return $this->registrationType->amountDueFor(count($this->delegates));
    }

    /**
     * Whether the delegate picked a committee where portfolios of this type are allocated.
     */
    public function delegateNeedsPortfolio(int $index, PortfolioType $portfolioType): bool
    {
        $chosen = array_filter([
            $this->delegates[$index]['first_committee_id'] ?? null,
            $this->delegates[$index]['second_committee_id'] ?? null,
        ]);

        return $this->committees
            ->whereIn('id', $chosen)
            ->contains('portfolio_type', $portfolioType);
    }

    /**
     * Validate and store the registration.
     */
    public function submit(SubmitRegistration $submitRegistration): void
    {
        $validated = $this->validate();
        $type = $this->registrationType;

        $delegates = array_map(fn (array $delegate) => [
            ...Arr::only($delegate, ['name', 'cnic', 'email', 'phone', 'first_committee_id', 'second_committee_id']),
            'country_preference' => $delegate['country_preference'] ?: null,
            'personality_preference' => $delegate['personality_preference'] ?: null,
        ], $validated['delegates']);

        $contact = $type->isDelegation()
            ? $validated['contact']
            : [...Arr::only($delegates[0], ['name', 'cnic', 'phone', 'email']), 'city' => $validated['contact']['city']];

        $registration = $submitRegistration->handle($type, $contact, $delegates, $this->paymentScreenshot);

        $this->submittedReference = $registration->reference;
        $this->js('window.scrollTo({ top: 0 })');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $type = $this->registrationType;
        $isDelegation = $type->isDelegation();
        $phone = ['required', 'string', 'regex:/^\+?[0-9\s\-]{10,15}$/'];
        $cnic = ['required', 'string', 'regex:/^\d{5}-?\d{7}-?\d$/'];

        return [
            'type' => ['required', Rule::enum(RegistrationType::class)],
            'contact.organisation_name' => [Rule::requiredIf($isDelegation), 'nullable', 'string', 'max:255'],
            'contact.city' => ['required', 'string', 'max:255'],
            'contact.name' => [Rule::requiredIf($isDelegation), 'nullable', 'string', 'max:255'],
            'contact.cnic' => $isDelegation ? $cnic : ['nullable'],
            'contact.phone' => $isDelegation ? $phone : ['nullable'],
            'contact.email' => [Rule::requiredIf($isDelegation), 'nullable', 'email', 'max:255'],
            'delegates' => ['required', 'array', 'min:'.$type->minimumDelegates(), 'max:'.$type->maximumDelegates()],
            'delegates.*.name' => ['required', 'string', 'max:255'],
            'delegates.*.cnic' => [...$cnic, 'distinct'],
            'delegates.*.email' => ['required', 'email', 'max:255', 'distinct:ignore_case', $this->notAlreadyRegistered(...)],
            'delegates.*.phone' => $phone,
            'delegates.*.first_committee_id' => ['required', Rule::exists('committees', 'id')],
            'delegates.*.second_committee_id' => ['required', Rule::exists('committees', 'id'), 'different:delegates.*.first_committee_id'],
            'delegates.*.country_preference' => ['nullable', 'string', 'max:255'],
            'delegates.*.personality_preference' => ['nullable', 'string', 'max:255'],
            'paymentScreenshot' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'confirmed' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'contact.organisation_name' => $this->type === RegistrationType::InstitutionDelegation->value ? 'institution name' : 'delegation name',
            'contact.city' => 'city',
            'contact.name' => 'full name',
            'contact.cnic' => 'CNIC / B-Form',
            'contact.phone' => 'phone number',
            'contact.email' => 'email',
            'delegates.*.name' => 'full name',
            'delegates.*.cnic' => 'CNIC / B-Form',
            'delegates.*.email' => 'email',
            'delegates.*.phone' => 'phone number',
            'delegates.*.first_committee_id' => '1st committee',
            'delegates.*.second_committee_id' => '2nd committee',
            'paymentScreenshot' => 'payment screenshot',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'delegates.*.second_committee_id.different' => 'Choose a different committee from the 1st preference.',
            'delegates.*.cnic.regex' => 'Enter a 13-digit CNIC / B-Form number, e.g. 35202-1234567-1.',
            'contact.cnic.regex' => 'Enter a 13-digit CNIC / B-Form number, e.g. 35202-1234567-1.',
            'delegates.*.email.distinct' => 'Each delegate needs their own email address.',
            'delegates.*.cnic.distinct' => 'Each delegate needs their own CNIC / B-Form number.',
            'confirmed.accepted' => 'Please confirm your details and payment amount.',
        ];
    }

    /**
     * Reject an email that already belongs to a pending or verified registration.
     */
    private function notAlreadyRegistered(string $attribute, mixed $value, Closure $fail): void
    {
        $alreadyRegistered = Delegate::query()
            ->where('email', $value)
            ->whereHas('registration', fn ($query) => $query->where('status', '!=', RegistrationStatus::Rejected))
            ->exists();

        if ($alreadyRegistered) {
            $fail('This email is already registered for the conference.');
        }
    }

    /**
     * Keep the delegate rows in step with the chosen registration type and count.
     */
    private function syncDelegateCount(): void
    {
        $type = $this->registrationType;

        $this->delegateCount = max($type->minimumDelegates(), min($type->maximumDelegates(), $this->delegateCount));

        $blankDelegate = [
            'name' => '',
            'cnic' => '',
            'email' => '',
            'phone' => '',
            'first_committee_id' => '',
            'second_committee_id' => '',
            'country_preference' => '',
            'personality_preference' => '',
        ];

        $this->delegates = array_slice(
            array_pad($this->delegates, $this->delegateCount, $blankDelegate),
            0,
            $this->delegateCount,
        );
    }
}; ?>

<div>
    @if ($submittedReference)
        <section class="mx-auto flex max-w-2xl flex-col items-center gap-6 px-6 py-28 text-center">
            <svg class="size-7" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 0 L14 10 L24 12 L14 14 L12 24 L10 14 L0 12 L10 10 Z" fill="#c9b6ea" /></svg>
            <h1 class="font-display text-4xl font-semibold">{{ __('Registration submitted') }}</h1>
            <p class="font-script text-2xl text-[#e6e1ec] italic">{{ __('Thank you for registering for Homebridge MUN ’26.') }}</p>
            <div class="flex flex-col gap-1.5 border border-hb-line-strong px-8 py-5">
                <div class="text-xs tracking-[0.18em] text-hb-subtle uppercase">{{ __('Your reference number') }}</div>
                <div class="font-display text-3xl font-bold tracking-[0.08em]" data-test="reference-number">{{ $submittedReference }}</div>
            </div>
            <p class="max-w-lg leading-relaxed text-hb-muted">
                {{ __('Our registration team will verify your payment. You’ll soon receive an email or WhatsApp message with a link to set your password and open your delegate dashboard. Keep your reference number to check your status.') }}
            </p>
            <a href="{{ route('registration.status', ['reference' => $submittedReference]) }}" class="hb-button-primary">{{ __('Track my status') }}</a>
        </section>
    @else
        <div class="mx-auto flex max-w-6xl flex-wrap items-start gap-12 px-6 pt-14 pb-24">
            {{-- Side panel --}}
            <aside class="flex min-w-0 flex-[1_1_300px] flex-col gap-6 lg:sticky lg:top-6">
                <div class="hb-eyebrow">{{ __('Delegate registration') }}</div>
                <h1 class="font-display text-4xl leading-tight font-semibold">{{ __('Claim your seat.') }}</h1>
                <p class="leading-relaxed text-hb-muted">
                    {{ __('Two days of diplomacy, debate, negotiation and collaboration. :dates, online.', ['dates' => config('conference.dates_label')]) }}
                </p>
                <div class="hb-art hb-art-library hidden w-full max-w-[300px] border border-hb-line-strong lg:block" aria-hidden="true"></div>
                <div class="flex flex-col gap-2.5 border border-hb-line bg-hb-surface p-5">
                    <div class="text-xs tracking-[0.18em] text-hb-subtle uppercase">{{ __('Amount due') }}</div>
                    <div class="font-display text-4xl font-bold" data-test="amount-due">PKR {{ number_format($this->amountDue) }}</div>
                    <div class="text-sm leading-relaxed text-hb-muted">
                        @if ($this->registrationType->isDelegation())
                            {{ __(':count delegates × PKR :fee + PKR :delegation delegation fee', ['count' => count($delegates), 'fee' => number_format(config('conference.fees.per_delegate')), 'delegation' => number_format(config('conference.fees.per_delegation'))]) }}
                        @else
                            {{ __('1 delegate × PKR :fee', ['fee' => number_format(config('conference.fees.per_delegate'))]) }}
                        @endif
                    </div>
                    <div class="border-t border-hb-line pt-2 text-xs leading-relaxed text-hb-subtle">
                        {{ __('Questions about the fee or an adjustment? Contact the registration team below.') }}
                    </div>
                </div>
            </aside>

            {{-- Form --}}
            <form wire:submit="submit" class="flex min-w-0 flex-[2_1_600px] flex-col gap-10" novalidate>
                {{-- 1. Registration type --}}
                <fieldset class="flex flex-col gap-4">
                    <legend class="hb-legend">1 · {{ __('How would you like to register?') }}</legend>
                    <div class="grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-3">
                        @foreach (RegistrationType::cases() as $option)
                            <label wire:key="type-{{ $option->value }}" @class([
                                'flex min-h-19 cursor-pointer flex-col justify-center rounded-xs border px-5 py-4 transition has-focus-visible:outline-2 has-focus-visible:outline-[#8e6bc8]',
                                'border-[#a98bd6] bg-hb-purple text-white' => $type === $option->value,
                                'border-[#3a3542] bg-hb-field text-hb-soft hover:border-[#6e6a75]' => $type !== $option->value,
                            ])>
                                <input type="radio" wire:model.live="type" value="{{ $option->value }}" class="sr-only">
                                <span class="font-semibold">{{ $option->label() }}</span>
                                <span class="mt-1 text-sm opacity-80">
                                    {{ $option->isDelegation() ? __(':min–:max members', ['min' => $option->minimumDelegates(), 'max' => $option->maximumDelegates()]) : __('Individual') }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                {{-- 2. Contact --}}
                <fieldset class="flex flex-col gap-4">
                    @if ($this->registrationType === RegistrationType::InstitutionDelegation)
                        <legend class="hb-legend">2 · {{ __('Institution information') }}</legend>
                    @elseif ($this->registrationType === RegistrationType::PrivateDelegation)
                        <legend class="hb-legend">2 · {{ __('Delegation head') }}</legend>
                    @else
                        <legend class="hb-legend">2 · {{ __('Your city') }}</legend>
                    @endif

                    <div class="grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-4">
                        @if ($this->registrationType->isDelegation())
                            <x-public.field :label="$this->registrationType === RegistrationType::InstitutionDelegation ? __('Institution / school / college name') : __('Delegation name')" name="contact.organisation_name">
                                <input type="text" wire:model.blur="contact.organisation_name" class="hb-input" autocomplete="organization">
                            </x-public.field>
                        @endif

                        <x-public.field :label="__('City')" name="contact.city">
                            <input type="text" wire:model.blur="contact.city" class="hb-input" placeholder="Lahore" autocomplete="address-level2">
                        </x-public.field>

                        @if ($this->registrationType->isDelegation())
                            @php($who = $this->registrationType === RegistrationType::InstitutionDelegation ? __('Representative’s') : __('Head’s'))
                            <x-public.field :label="$who.' '.__('full name')" name="contact.name">
                                <input type="text" wire:model.blur="contact.name" class="hb-input" autocomplete="name">
                            </x-public.field>
                            <x-public.field :label="$who.' '.__('CNIC / B-Form')" name="contact.cnic">
                                <input type="text" wire:model.blur="contact.cnic" class="hb-input" inputmode="numeric" placeholder="35202-1234567-1">
                            </x-public.field>
                            <x-public.field :label="$who.' '.__('phone (WhatsApp)')" name="contact.phone">
                                <input type="tel" wire:model.blur="contact.phone" class="hb-input" placeholder="03XX XXXXXXX" autocomplete="tel">
                            </x-public.field>
                            <x-public.field :label="$who.' '.__('email')" name="contact.email">
                                <input type="email" wire:model.blur="contact.email" class="hb-input" autocomplete="email">
                            </x-public.field>
                        @endif
                    </div>
                </fieldset>

                {{-- 3. Delegates --}}
                <fieldset class="flex flex-col gap-4">
                    <legend class="hb-legend">3 · {{ $this->registrationType->isDelegation() ? __('Delegates') : __('Your details & preferences') }}</legend>

                    @if ($this->registrationType->isDelegation())
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="text-sm text-hb-soft" id="delegate-count-label">{{ __('Number of delegates') }}</span>
                            <div class="flex gap-2" role="radiogroup" aria-labelledby="delegate-count-label">
                                @foreach (range($this->registrationType->minimumDelegates(), $this->registrationType->maximumDelegates()) as $count)
                                    <label wire:key="count-{{ $count }}" @class([
                                        'flex size-12 cursor-pointer items-center justify-center rounded-xs border text-base has-focus-visible:outline-2 has-focus-visible:outline-[#8e6bc8]',
                                        'border-white bg-white font-semibold text-hb-purple' => $delegateCount === $count,
                                        'border-[#3a3542] bg-hb-field text-white hover:border-[#6e6a75]' => $delegateCount !== $count,
                                    ])>
                                        <input type="radio" wire:model.live="delegateCount" value="{{ $count }}" class="sr-only">
                                        {{ $count }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <x-public.error name="delegates" />
                    @endif

                    @foreach ($delegates as $index => $delegate)
                        <div wire:key="delegate-{{ $index }}" class="flex flex-col gap-3.5 border border-hb-line bg-hb-surface p-5">
                            @if ($this->registrationType->isDelegation())
                                <div class="text-xs tracking-[0.18em] text-hb-lavender uppercase">{{ __('Delegate :number', ['number' => $index + 1]) }}</div>
                            @endif
                            <div class="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-3">
                                <x-public.field :label="__('Full name')" name="delegates.{{ $index }}.name">
                                    <input type="text" wire:model.blur="delegates.{{ $index }}.name" class="hb-input">
                                </x-public.field>
                                <x-public.field :label="__('CNIC / B-Form')" name="delegates.{{ $index }}.cnic">
                                    <input type="text" wire:model.blur="delegates.{{ $index }}.cnic" class="hb-input" inputmode="numeric" placeholder="35202-1234567-1">
                                </x-public.field>
                                <x-public.field :label="__('Email (Gmail)')" name="delegates.{{ $index }}.email">
                                    <input type="email" wire:model.blur="delegates.{{ $index }}.email" class="hb-input" placeholder="name@gmail.com">
                                </x-public.field>
                                <x-public.field :label="__('Phone (WhatsApp)')" name="delegates.{{ $index }}.phone">
                                    <input type="tel" wire:model.blur="delegates.{{ $index }}.phone" class="hb-input" placeholder="03XX XXXXXXX">
                                </x-public.field>
                                <x-public.field :label="__('1st committee')" name="delegates.{{ $index }}.first_committee_id">
                                    <select wire:model.live="delegates.{{ $index }}.first_committee_id" class="hb-input">
                                        <option value="">{{ __('Choose…') }}</option>
                                        @foreach ($this->committees as $committee)
                                            <option value="{{ $committee->id }}" wire:key="first-{{ $index }}-{{ $committee->id }}">{{ $committee->label() }}</option>
                                        @endforeach
                                    </select>
                                </x-public.field>
                                <x-public.field :label="__('2nd committee')" name="delegates.{{ $index }}.second_committee_id">
                                    <select wire:model.live="delegates.{{ $index }}.second_committee_id" class="hb-input">
                                        <option value="">{{ __('Choose…') }}</option>
                                        @foreach ($this->committees as $committee)
                                            <option value="{{ $committee->id }}" wire:key="second-{{ $index }}-{{ $committee->id }}" @disabled((string) $committee->id === (string) $delegate['first_committee_id'])>{{ $committee->label() }}</option>
                                        @endforeach
                                    </select>
                                </x-public.field>
                                @if ($this->delegateNeedsPortfolio($index, PortfolioType::Country))
                                    <x-public.field :label="__('Country preference')" name="delegates.{{ $index }}.country_preference" :hint="__('For UNW / UNSC')">
                                        <input type="text" wire:model.blur="delegates.{{ $index }}.country_preference" class="hb-input" placeholder="{{ __('e.g. France') }}">
                                    </x-public.field>
                                @endif
                                @if ($this->delegateNeedsPortfolio($index, PortfolioType::Character))
                                    <x-public.field :label="__('Personality preference')" name="delegates.{{ $index }}.personality_preference" :hint="__('For PNA / Special Committee')">
                                        <input type="text" wire:model.blur="delegates.{{ $index }}.personality_preference" class="hb-input" placeholder="{{ __('e.g. Maryam Nawaz') }}">
                                    </x-public.field>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </fieldset>

                {{-- 4. Payment --}}
                <fieldset class="flex flex-col gap-4">
                    <legend class="hb-legend">4 · {{ __('Payment & confirmation') }}</legend>
                    <dl class="grid grid-cols-[repeat(auto-fit,minmax(190px,1fr))] gap-4 border border-hb-purple-line bg-hb-purple p-6">
                        @foreach ([
                            __('Account title') => config('conference.payment.account_title'),
                            __('Service') => config('conference.payment.service'),
                            __('Account no. / IBAN') => config('conference.payment.account_number'),
                        ] as $label => $value)
                            <div>
                                <dt class="text-xs tracking-[0.16em] text-[#dccff0] uppercase">{{ $label }}</dt>
                                <dd class="mt-1 font-semibold break-words">{{ $value ?: __('To be announced') }}</dd>
                            </div>
                        @endforeach
                        <div>
                            <dt class="text-xs tracking-[0.16em] text-[#dccff0] uppercase">{{ __('Send exactly') }}</dt>
                            <dd class="mt-0.5 font-display text-2xl font-bold">PKR {{ number_format($this->amountDue) }}</dd>
                        </div>
                    </dl>

                    <div class="flex flex-col gap-2">
                        <label for="payment-screenshot" class="relative flex min-h-40 cursor-pointer flex-col items-center justify-center gap-2.5 border border-dashed border-[#6e6a75] bg-hb-surface p-6 text-center has-focus-visible:outline-2 has-focus-visible:outline-[#8e6bc8]">
                            <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="#c9b6ea" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4" /><path d="m6 10 6-6 6 6" /><path d="M4 20h16" /></svg>
                            <span class="font-semibold">
                                @if ($paymentScreenshot && ! $errors->has('paymentScreenshot'))
                                    {{ $paymentScreenshot->getClientOriginalName() }}
                                @else
                                    {{ __('Upload payment screenshot') }}
                                @endif
                            </span>
                            <span class="max-w-md text-sm leading-relaxed text-hb-subtle">{{ __('One clear screenshot showing the transaction confirmation and the amount paid. JPG, JPEG or PNG, up to 5 MB.') }}</span>
                            <span wire:loading wire:target="paymentScreenshot" class="text-sm text-hb-lavender">{{ __('Uploading…') }}</span>
                            <input id="payment-screenshot" type="file" wire:model="paymentScreenshot" accept=".jpg,.jpeg,.png" class="sr-only">
                        </label>
                        <x-public.error name="paymentScreenshot" />
                    </div>

                    @if (config('conference.contacts.phones') || config('conference.contacts.email'))
                        <div class="flex flex-col gap-3 border border-hb-line p-5">
                            <div class="font-display font-semibold">{{ __('Need help?') }}</div>
                            <dl class="grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-3 text-sm">
                                @foreach (config('conference.contacts.phones') as $phone)
                                    <div>
                                        <dt class="text-xs text-hb-subtle">{{ __('Registration Head :number', ['number' => $loop->iteration]) }}</dt>
                                        <dd class="mt-1"><a href="tel:{{ $phone }}" class="hover:text-hb-lavender">{{ $phone }}</a></dd>
                                    </div>
                                @endforeach
                                @if (config('conference.contacts.email'))
                                    <div>
                                        <dt class="text-xs text-hb-subtle">{{ __('Registration email') }}</dt>
                                        <dd class="mt-1 break-words"><a href="mailto:{{ config('conference.contacts.email') }}" class="hover:text-hb-lavender">{{ config('conference.contacts.email') }}</a></dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    @endif
                </fieldset>

                <div class="flex flex-col gap-5 border-t border-hb-line pt-5">
                    <div class="flex flex-col gap-2">
                        <label class="flex items-start gap-3 text-sm leading-relaxed text-hb-soft">
                            <input type="checkbox" wire:model="confirmed" class="mt-0.5 size-5 accent-[#8e6bc8]">
                            <span>{{ __('I confirm the details are correct and the amount paid matches my registration category.') }}</span>
                        </label>
                        <x-public.error name="confirmed" />
                    </div>

                    @if ($errors->any())
                        <p class="hb-error" role="alert">{{ __('Please fix the highlighted fields above.') }}</p>
                    @endif

                    <button type="submit" class="hb-button-primary min-h-13 self-start px-10 text-base" wire:loading.attr="disabled" wire:target="submit,paymentScreenshot" data-test="submit-registration">
                        <span wire:loading.remove wire:target="submit">{{ __('Submit registration') }}</span>
                        <span wire:loading wire:target="submit">{{ __('Submitting…') }}</span>
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
