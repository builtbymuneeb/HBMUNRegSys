<?php

use App\Actions\Registrations\SubmitRegistration;
use App\Enums\MunExperience;
use App\Enums\PortfolioType;
use App\Enums\RegistrationStatus;
use App\Enums\RegistrationType;
use App\Livewire\Concerns\CollectsPaymentScreenshots;
use App\Models\Committee;
use App\Models\Delegate;
use App\Models\PaymentScreenshot;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::public')] #[Title('Register')] class extends Component {
    use CollectsPaymentScreenshots;
    use WithFileUploads;

    /**
     * The steps of the form, in order. Review has no fields of its own.
     */
    public const STEPS = [
        1 => 'Details',
        2 => 'Delegates',
        3 => 'Review',
        4 => 'Payment',
    ];

    /**
     * What each step asks for, shown under its name so nobody wonders how long the form is.
     */
    public const STEP_HINTS = [
        1 => 'Contact details',
        2 => 'Names & committees',
        3 => 'Check everything',
        4 => 'Pay & upload',
    ];

    #[Url]
    public string $type = 'institution_delegation';

    public int $step = 1;

    /**
     * Steps the registrant has already been on and left. Nothing blocks moving on, so these
     * are the steps whose missing or wrong fields are flagged, here and on the step list.
     *
     * @var list<int>
     */
    public array $visited = [];

    /** @var array{organisation_name: string, city: string, name: string, cnic: string, phone: string, email: string, referral_code: string} */
    public array $contact = [
        'organisation_name' => '',
        'city' => '',
        'name' => '',
        'cnic' => '',
        'phone' => '',
        'email' => '',
        'referral_code' => '',
    ];

    /**
     * How many delegates are coming; null until the registrant picks a number.
     */
    public ?int $delegateCount = null;

    /** @var list<array{name: string, cnic: string, email: string, phone: string, first_committee_id: string, second_committee_id: string, first_preference: string, second_preference: string, mun_experience: string, is_homebridge: bool}> */
    public array $delegates = [];

    public bool $confirmed = false;

    /**
     * Whether submitting has been tried; only then is the payment step flagged too.
     */
    public bool $attemptedSubmit = false;

    public bool $restoredDraft = false;

    public ?string $submittedReference = null;

    /**
     * The referral code from a campus ambassador's link (/register?ref=HB05), kept through drafts and starting over.
     */
    #[Locked]
    public string $linkReferralCode = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->ensureKnownType();
        $this->syncDelegateCount();

        $this->linkReferralCode = Str::limit(Str::upper(preg_replace('/[^A-Za-z0-9-]/', '', (string) request()->query('ref'))), 50, '');
        $this->contact['referral_code'] = $this->linkReferralCode;
    }

    /**
     * Switch between the registration types that are open.
     */
    public function updatedType(): void
    {
        $this->ensureKnownType();
        $this->syncDelegateCount();
        $this->recheckIfFlagged();
    }

    public function updatedDelegateCount(): void
    {
        $this->syncDelegateCount();
        $this->recheckIfFlagged();
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
        return $this->registrationType->amountDueFor(count($this->delegates), $this->homebridgeCount);
    }

    /**
     * How many of the delegates said they are Homebridge students.
     */
    #[Computed]
    public function homebridgeCount(): int
    {
        return count(array_filter(array_column($this->delegates, 'is_homebridge')));
    }

    /**
     * What the total would be with no Homebridge discount, to show the saving.
     */
    #[Computed]
    public function amountBeforeDiscount(): int
    {
        return $this->registrationType->amountDueFor(count($this->delegates));
    }

    /**
     * The committee the delegate chose as their 1st or 2nd choice, once they have picked one.
     */
    public function chosenCommittee(int $index, string $slot): ?Committee
    {
        $id = $this->delegates[$index][$slot.'_committee_id'] ?? null;

        return $id ? $this->committees->firstWhere('id', (int) $id) : null;
    }

    /**
     * What the delegate may pick as their preference for one committee choice: that committee's
     * public list. Null means they type it freely, because the committee keeps its list secret
     * (the Special Committee's cast).
     *
     * @return list<string>|null
     */
    public function preferenceOptions(int $index, string $slot): ?array
    {
        return $this->chosenCommittee($index, $slot)?->publicPortfolioOptions();
    }

    /**
     * Move on to the next step. Nothing blocks this: anything missing is flagged instead,
     * and has to be fixed before the registration can be submitted.
     */
    public function next(): void
    {
        $this->goTo(min($this->step + 1, array_key_last(self::STEPS)));
    }

    public function back(): void
    {
        $this->goTo(max($this->step - 1, 1));
    }

    /**
     * Jump to any step, from the step list or "Edit"/"Fix" on the review step.
     */
    public function editStep(int $step): void
    {
        if (array_key_exists($step, self::STEPS)) {
            $this->goTo($step);
        }
    }

    /**
     * Once a flagged field changes, check the flagged steps again so fixed fields clear.
     */
    public function updated(string $property): void
    {
        // A preference belongs to the committee it was picked for, so a new committee starts blank.
        if (preg_match('/^delegates\.(\d+)\.(first|second)_committee_id$/', $property, $match)) {
            $this->delegates[(int) $match[1]][$match[2].'_preference'] = '';
        }

        if (! in_array($property, ['type', 'delegateCount', 'newScreenshot'], true)) {
            $this->recheckIfFlagged();
        }
    }

    /**
     * The steps that have something to fix, among those already left.
     *
     * @return list<int>
     */
    public function stepsWithIssues(): array
    {
        return collect($this->getErrorBag()->keys())
            ->map(fn (string $field) => $this->stepForField($field))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Labels for what is wrong on a step, e.g. "Delegate 2: email", for the review checklist.
     *
     * @return list<string>
     */
    public function issuesOnStep(int $step): array
    {
        $attributes = $this->validationAttributes();

        return collect($this->getErrorBag()->keys())
            ->filter(fn (string $field) => $this->stepForField($field) === $step)
            ->map(function (string $field) use ($attributes) {
                if (preg_match('/^delegates\.(\d+)\.(.+)$/', $field, $matches)) {
                    return __('Delegate :number: :field', [
                        'number' => $matches[1] + 1,
                        'field' => $attributes['delegates.*.'.$matches[2]] ?? $matches[2],
                    ]);
                }

                if (Str::startsWith($field, 'paymentScreenshots')) {
                    return Str::ucfirst($attributes['paymentScreenshots']);
                }

                return $field === 'delegates' ? __('Number of delegates') : Str::ucfirst($attributes[$field] ?? $field);
            })
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Fill the form from a draft the browser saved, landing on the furthest step
     * whose earlier steps are still valid. The payment screenshots are never saved.
     *
     * @param  array<string, mixed>  $draft
     */
    public function restoreDraft(array $draft): void
    {
        if ($this->submittedReference || $this->step !== 1) {
            return;
        }

        $this->type = is_string($draft['type'] ?? null) ? $draft['type'] : $this->type;
        $this->ensureKnownType();

        foreach (array_keys($this->contact) as $key) {
            $this->contact[$key] = $this->cleanString($draft['contact'][$key] ?? '');
        }
        $this->contact['referral_code'] = $this->contact['referral_code'] ?: $this->linkReferralCode;

        $draftDelegates = is_array($draft['delegates'] ?? null) ? $draft['delegates'] : [];
        $draftCount = $draft['delegateCount'] ?? ($draftDelegates === [] ? null : count($draftDelegates));
        $this->delegateCount = is_numeric($draftCount) ? (int) $draftCount : null;
        $this->delegates = [];
        $this->syncDelegateCount();

        foreach ($this->delegates as $index => $delegate) {
            foreach (array_keys($delegate) as $key) {
                $this->delegates[$index][$key] = $key === 'is_homebridge'
                    ? filter_var($draft['delegates'][$index][$key] ?? false, FILTER_VALIDATE_BOOL)
                    : $this->cleanString($draft['delegates'][$index][$key] ?? '');
            }
        }

        $this->step = max(1, min((int) ($draft['step'] ?? 1), array_key_last(self::STEPS)));
        $this->visited = range(1, $this->step - 1);
        $this->checkVisitedSteps();
        $this->restoredDraft = true;
    }

    /**
     * Throw away the draft and start again.
     */
    public function startOver(): void
    {
        $this->reset('contact', 'delegates', 'delegateCount', 'paymentScreenshots', 'newScreenshot', 'confirmed', 'restoredDraft', 'visited', 'attemptedSubmit');
        $this->type = RegistrationType::InstitutionDelegation->value;
        $this->contact['referral_code'] = $this->linkReferralCode;
        $this->resetValidation();
        $this->syncDelegateCount();
        $this->goTo(1);
        $this->dispatch('registration-draft-cleared');
    }

    /**
     * Validate and store the registration.
     */
    public function submit(SubmitRegistration $submitRegistration): void
    {
        try {
            $validated = $this->validate();
        } catch (ValidationException $exception) {
            // Everything counts as seen now, so every step's problems are flagged.
            $this->visited = array_keys(self::STEPS);
            $this->attemptedSubmit = true;
            $this->step = $this->stepForErrors(array_keys($exception->errors()));
            $this->focusFirstError();

            throw $exception;
        }

        $delegates = array_map(fn (array $delegate) => [
            ...Arr::only($delegate, ['name', 'cnic', 'email', 'phone', 'first_committee_id', 'second_committee_id']),
            'first_preference' => $delegate['first_preference'] ?: null,
            'second_preference' => $delegate['second_preference'] ?: null,
            'mun_experience' => MunExperience::tryFrom($delegate['mun_experience'] ?? ''),
            'is_homebridge' => ! $this->registrationType->isIndividual() && (bool) ($delegate['is_homebridge'] ?? false),
        ], $validated['delegates']);

        $contact = [
            ...$validated['contact'],
            'referral_code' => Str::upper(trim($validated['contact']['referral_code'] ?? '')) ?: null,
        ];

        $registration = $submitRegistration->handle($this->registrationType, $contact, $delegates, $this->paymentScreenshots);

        $this->submittedReference = $registration->reference;
        $this->dispatch('registration-draft-cleared');
        $this->js('window.scrollTo({ top: 0 })');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $type = $this->registrationType;
        // Any country's format, since delegates can join from abroad.
        $phone = ['required', 'string', 'max:30', 'regex:/^[0-9+\s\-()]+$/'];
        $cnic = ['required', 'string', 'regex:/^\d+$/', 'max:30'];

        return [
            'type' => ['required', Rule::enum(RegistrationType::class)],
            'contact.organisation_name' => ['required', 'string', 'max:255'],
            'contact.city' => ['required', 'string', 'max:255'],
            'contact.name' => ['required', 'string', 'max:255'],
            'contact.cnic' => $cnic,
            'contact.phone' => $phone,
            'contact.email' => ['required', 'email', 'max:255'],
            'contact.referral_code' => ['nullable', 'string', 'max:50'],
            'delegates' => ['required', 'array', 'min:'.$type->minimumDelegates(), 'max:'.$type->maximumDelegates()],
            'delegates.*.name' => ['required', 'string', 'max:255'],
            'delegates.*.cnic' => [...$cnic, 'distinct'],
            'delegates.*.email' => ['required', 'email', 'max:255', 'distinct:ignore_case', $this->notAlreadyRegistered(...)],
            'delegates.*.phone' => $phone,
            'delegates.*.first_committee_id' => ['required', Rule::exists('committees', 'id')],
            'delegates.*.second_committee_id' => ['required', Rule::exists('committees', 'id'), 'different:delegates.*.first_committee_id'],
            'delegates.*.first_preference' => ['nullable', 'string', 'max:255', $this->onPreferenceList('first')],
            'delegates.*.second_preference' => ['nullable', 'string', 'max:255', $this->onPreferenceList('second')],
            'delegates.*.mun_experience' => ['nullable', Rule::enum(MunExperience::class)],
            'delegates.*.is_homebridge' => ['boolean'],
            ...$this->paymentScreenshotsRules(),
            'confirmed' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'contact.organisation_name' => match ($this->registrationType) {
                RegistrationType::InstitutionDelegation => 'institution name',
                RegistrationType::PrivateDelegate => 'institution name',
                default => 'delegation name',
            },
            'contact.city' => 'city',
            'contact.name' => 'full name',
            'contact.cnic' => 'CNIC / B-Form',
            'contact.phone' => 'phone number',
            'contact.email' => 'email',
            'contact.referral_code' => 'referral code',
            'delegates.*.name' => 'full name',
            'delegates.*.cnic' => 'CNIC / B-Form',
            'delegates.*.email' => 'email',
            'delegates.*.phone' => 'phone number',
            'delegates.*.first_committee_id' => '1st committee',
            'delegates.*.second_committee_id' => '2nd committee',
            'delegates.*.first_preference' => '1st committee preference',
            'delegates.*.second_preference' => '2nd committee preference',
            'delegates.*.mun_experience' => 'previous MUN experience',
            'paymentScreenshots' => 'payment screenshot',
            'paymentScreenshots.*' => 'payment screenshot',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'delegates.*.second_committee_id.different' => 'Choose a different committee from the 1st preference.',
            'delegates.*.cnic.regex' => 'The ID number can only contain digits.',
            'contact.phone.regex' => 'The phone number can only contain digits, spaces, dashes, brackets and a +.',
            'delegates.*.phone.regex' => 'The phone number can only contain digits, spaces, dashes, brackets and a +.',
            'contact.cnic.regex' => 'The ID number can only contain digits.',
            'delegates.*.email.distinct' => 'Each delegate needs their own email address.',
            'delegates.*.cnic.distinct' => 'Each delegate needs their own CNIC / B-Form number.',
            'paymentScreenshots.max' => 'You can upload up to :max screenshots.',
            'confirmed.accepted' => 'Please confirm your details and payment amount.',
            'delegates.required' => 'Choose how many delegates are in your delegation.',
        ];
    }

    /**
     * Validate only the fields that belong to one step.
     */
    private function validateStep(int $step): void
    {
        $rules = Arr::where(
            $this->rules(),
            fn ($rule, string $field) => $this->stepForField($field) === $step,
        );

        if ($rules !== []) {
            $this->validate($rules);
        }
    }

    private function stepForField(string $field): int
    {
        return match (true) {
            $field === 'type', Str::startsWith($field, 'contact.') => 1,
            $field === 'delegates', Str::startsWith($field, 'delegates.') => 2,
            default => 4,
        };
    }

    /**
     * The earliest step that has one of the given fields.
     *
     * @param  list<string>  $fields
     */
    private function stepForErrors(array $fields): int
    {
        return min(array_map($this->stepForField(...), $fields) ?: [$this->step]);
    }

    private function goTo(int $step): void
    {
        if (! in_array($this->step, $this->visited, true)) {
            $this->visited[] = $this->step;
        }

        // Review and payment look over everything, so steps 1 and 2 count as seen from there.
        if ($step >= 3) {
            $this->visited = array_values(array_unique([...$this->visited, 1, 2]));
        }

        $this->step = $step;
        $this->fillPrivateDelegateFromContact();
        $this->checkVisitedSteps();
        $this->js("document.getElementById('registration-steps')?.scrollIntoView({ behavior: 'smooth', block: 'start' })");
    }

    private function recheckIfFlagged(): void
    {
        if ($this->getErrorBag()->isNotEmpty()) {
            $this->checkVisitedSteps();
        }
    }

    /**
     * Flag the problems on every step already left, without stopping the registrant.
     * The payment step is only checked once submitting has been tried.
     */
    private function checkVisitedSteps(): void
    {
        $this->resetValidation();
        $errors = [];

        foreach ($this->attemptedSubmit ? $this->visited : array_diff($this->visited, [4]) as $step) {
            try {
                $this->validateStep($step);
            } catch (ValidationException $exception) {
                $errors += $exception->errors();
            }
        }

        foreach ($errors as $field => $messages) {
            foreach ($messages as $message) {
                $this->addError($field, $message);
            }
        }
    }

    /**
     * After the page shows the step with the problem, scroll to and focus its first flagged field.
     */
    private function focusFirstError(): void
    {
        $this->js(<<<'JS'
            setTimeout(() => {
                const field = document.querySelector('[data-invalid]');
                if (! field) return;
                field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                field.querySelector('input:not([type=hidden]), select, textarea')?.focus({ preventScroll: true });
            }, 50);
        JS);
    }

    private function cleanString(mixed $value): string
    {
        return is_scalar($value) ? Str::limit(trim((string) $value), 255, '') : '';
    }

    /**
     * Reject an email that already belongs to a pending or verified registration.
     */
    /**
     * A preference must be on the list the delegate was shown, when they were shown one.
     */
    private function onPreferenceList(string $slot): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($slot): void {
            $options = $this->preferenceOptions((int) explode('.', $attribute)[1], $slot);

            if ($options !== null && ! in_array($value, $options, true)) {
                $fail(__('Choose your :attribute from the list.'));
            }
        };
    }

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
     * Fall back to an institution delegation when the chosen type is unknown.
     */
    private function ensureKnownType(): void
    {
        if (! RegistrationType::tryFrom($this->type)) {
            $this->type = RegistrationType::InstitutionDelegation->value;
        }
    }

    /**
     * A private delegate is also the contact, so their delegate row starts with the details
     * they already gave on step 1. Anything they have typed into the row is kept.
     */
    private function fillPrivateDelegateFromContact(): void
    {
        if (! $this->registrationType->isIndividual() || ! isset($this->delegates[0])) {
            return;
        }

        foreach (['name', 'cnic', 'email', 'phone'] as $key) {
            if (blank($this->delegates[0][$key])) {
                $this->delegates[0][$key] = $this->contact[$key];
            }
        }
    }

    /**
     * Keep the delegate rows in step with the chosen registration type and count.
     */
    private function syncDelegateCount(): void
    {
        $type = $this->registrationType;

        // With only one possible size there is nothing to choose.
        if ($type->minimumDelegates() === $type->maximumDelegates()) {
            $this->delegateCount = $type->minimumDelegates();
        }

        if ($this->delegateCount === null) {
            $this->delegates = [];

            return;
        }

        $this->delegateCount = max($type->minimumDelegates(), min($type->maximumDelegates(), $this->delegateCount));

        $blankDelegate = [
            'name' => '',
            'cnic' => '',
            'email' => '',
            'phone' => '',
            'first_committee_id' => '',
            'second_committee_id' => '',
            'first_preference' => '',
            'second_preference' => '',
            'mun_experience' => '',
            'is_homebridge' => false,
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
        @php($isInstitution = $this->registrationType === RegistrationType::InstitutionDelegation)
        @php($isIndividual = $this->registrationType->isIndividual())
        <div class="mx-auto flex max-w-6xl flex-wrap items-start gap-12 px-6 pt-14 pb-24"
            x-data="registrationDraft"
            x-on:input.debounce.400ms="save()"
            x-on:change="save()">
            {{-- Side panel --}}
            <aside class="flex min-w-0 flex-[1_1_300px] flex-col gap-6 lg:sticky lg:top-24">
                <div class="hb-eyebrow">{{ __('Delegate registration') }}</div>
                <h1 class="font-display text-4xl leading-tight font-semibold">{{ __('Claim your seat.') }}</h1>
                <p class="leading-relaxed text-hb-muted">
                    {{ __('Two days of diplomacy, debate, negotiation and collaboration. :dates, online.', ['dates' => config('conference.dates_label')]) }}
                </p>
                <div class="hb-art hb-art-library hidden w-full max-w-[300px] border border-hb-line-strong lg:block" aria-hidden="true"></div>
                @php($feePerDelegate = number_format(config('conference.fees.per_delegate')))
                @php($feePerDelegation = number_format(config('conference.fees.per_delegation')))
                @php($hbPercent = RegistrationType::homebridgeDiscountPercent())
                @php($hbSaving = $this->amountBeforeDiscount - $this->amountDue)
                {{-- Phones get the total inline on the delegates step instead, so the form comes first. --}}
                <div class="hidden flex-col gap-2.5 border border-hb-line bg-hb-surface p-5 lg:flex">
                    @if ($isIndividual)
                        <div class="text-xs tracking-[0.18em] text-hb-subtle uppercase">{{ __('Amount due') }}</div>
                        <div class="font-display text-4xl font-bold" data-test="amount-due">PKR {{ number_format($this->amountDue) }}</div>
                        <div class="text-sm leading-relaxed text-hb-muted">
                            {{ __('Individual delegate fee. No delegation fee.') }}
                        </div>
                    @elseif ($step === 1)
                        <div class="text-xs tracking-[0.18em] text-hb-subtle uppercase">{{ __('Fees') }}</div>
                        <div class="text-sm leading-relaxed text-hb-soft">
                            {{ __('PKR :fee per delegate, plus PKR :delegation per delegation.', ['fee' => $feePerDelegate, 'delegation' => $feePerDelegation]) }}
                        </div>
                        <div class="text-sm leading-relaxed text-hb-lavender">{{ __('Homebridge students in a delegation get :percent% off.', ['percent' => $hbPercent]) }}</div>
                        <div class="text-sm leading-relaxed text-hb-muted">
                            {{ __('You’ll choose how many delegates (:min–:max) in the next step, and see your total there.', ['min' => $this->registrationType->minimumDelegates(), 'max' => $this->registrationType->maximumDelegates()]) }}
                        </div>
                    @elseif ($delegateCount === null)
                        <div class="text-xs tracking-[0.18em] text-hb-subtle uppercase">{{ __('Amount due') }}</div>
                        <div class="text-sm leading-relaxed text-hb-muted">{{ __('Choose the number of delegates to see your total.') }}</div>
                    @else
                        <div class="text-xs tracking-[0.18em] text-hb-subtle uppercase">{{ __('Amount due') }}</div>
                        <div class="font-display text-4xl font-bold" data-test="amount-due">PKR {{ number_format($this->amountDue) }}</div>
                        <div class="text-sm leading-relaxed text-hb-muted">
                            {{ __(':count delegates × PKR :fee + PKR :delegation delegation fee', ['count' => count($delegates), 'fee' => $feePerDelegate, 'delegation' => $feePerDelegation]) }}
                        </div>
                        @if ($hbSaving > 0)
                            <div class="text-sm leading-relaxed text-hb-lavender" data-test="homebridge-saving">{{ __('Includes :percent% Homebridge discount (−PKR :saving).', ['percent' => $hbPercent, 'saving' => number_format($hbSaving)]) }}</div>
                        @endif
                    @endif
                    <div class="border-t border-hb-line pt-2 text-xs leading-relaxed text-hb-subtle">
                        {{ __('Questions about the fee or an adjustment? Contact the registration team on the payment step.') }}
                    </div>
                </div>
            </aside>

            <div class="flex min-w-0 flex-[2_1_600px] flex-col gap-8">
                {{-- Progress --}}
                @php($stepsWithIssues = $this->stepsWithIssues())
                <ol id="registration-steps" class="grid scroll-mt-6 grid-cols-4 gap-2" aria-label="{{ __('Registration steps') }}">
                    @foreach ($this::STEPS as $number => $label)
                        @php($flagged = in_array($number, $stepsWithIssues, true) && $number !== $step)
                        @php($done = in_array($number, $visited, true) && ! in_array($number, $stepsWithIssues, true))
                        <li wire:key="step-{{ $number }}">
                            <button type="button" wire:click="editStep({{ $number }})" @disabled($number === $step)
                                @if ($number === $step) aria-current="step" @endif
                                data-test="step-tab-{{ $number }}"
                                @class([
                                    'flex w-full flex-col items-start gap-1 border-t-2 pt-3 text-left transition',
                                    'border-white text-white' => $number === $step,
                                    'border-amber-300 text-amber-200 hover:text-white' => $flagged,
                                    'border-[#a98bd6] text-hb-lavender hover:text-white' => $number !== $step && ! $flagged && $done,
                                    'border-[#3a3542] text-hb-subtle hover:text-hb-soft' => $number !== $step && ! $flagged && ! $done,
                                ])>
                                <span class="flex items-center gap-1.5 font-display text-xs tracking-[0.2em]">
                                    {{ __('Step :number', ['number' => $number]) }}
                                    @if ($flagged)
                                        <span class="inline-flex size-4 items-center justify-center rounded-full bg-amber-300 text-[10px] font-bold tracking-normal text-black" aria-hidden="true">!</span>
                                        <span class="sr-only">{{ __('(needs attention)') }}</span>
                                    @elseif ($done && $number !== $step)
                                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
                                    @endif
                                </span>
                                <span class="text-sm font-semibold">{{ __($label) }}</span>
                                <span class="hidden text-xs font-normal opacity-75 sm:block">{{ __($this::STEP_HINTS[$number]) }}</span>
                            </button>
                        </li>
                    @endforeach
                </ol>

                @if ($step === 1 && $visited === [] && ! $restoredDraft)
                    <p class="-mt-3 text-sm leading-relaxed text-hb-muted">{{ __('Four short steps. You can move between them freely and fill things in later; we’ll point out anything missing before you submit.') }}</p>
                @endif

                @php($otherFlagged = array_values(array_diff($stepsWithIssues, [$step, 3])))
                @if ($otherFlagged !== [] && $step !== 3)
                    <div class="flex flex-wrap items-center justify-between gap-3 border border-amber-300/40 bg-amber-300/10 px-4 py-3 text-sm text-amber-100" role="status" data-test="pending-issues">
                        <span>
                            @php($flaggedNames = collect($otherFlagged)->map(fn ($n) => __('Step :number (:label)', ['number' => $n, 'label' => __($this::STEPS[$n])]))->join(', ', ' '.__('and').' '))
                            {{ $attemptedSubmit
                                ? __(':steps also need fixing before you can submit.', ['steps' => $flaggedNames])
                                : __('Some details on :steps still need to be filled in. You can carry on and come back to them.', ['steps' => $flaggedNames]) }}
                        </span>
                        <button type="button" wire:click="editStep({{ $otherFlagged[0] }})" class="font-semibold text-amber-200 underline-offset-4 hover:underline">{{ __('Fix now') }}</button>
                    </div>
                @endif

                @if ($restoredDraft)
                    <div class="flex flex-wrap items-center justify-between gap-3 border border-hb-purple-line bg-hb-purple/40 px-4 py-3 text-sm" role="status">
                        <span>{{ __('We restored the details you entered earlier on this device. The payment screenshot needs to be uploaded again.') }}</span>
                        <button type="button" wire:click="startOver" wire:confirm="{{ __('Clear everything and start over?') }}" class="font-semibold text-hb-lavender underline-offset-4 hover:underline">{{ __('Start over') }}</button>
                    </div>
                @endif

                <form wire:submit="{{ $step === 4 ? 'submit' : 'next' }}" class="flex flex-col gap-10" novalidate>
                    @if ($step === 1)
                        {{-- 1. Registration type and contact --}}
                        <fieldset class="flex flex-col gap-4">
                            <legend class="hb-legend">{{ __('How would you like to register?') }}</legend>
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
                                            {{ $option->isIndividual()
                                                ? __('1 person · register on your own')
                                                : __(':min–:max members', ['min' => $option->minimumDelegates(), 'max' => $option->maximumDelegates()]) }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <fieldset class="flex flex-col gap-4">
                            <legend class="hb-legend">{{ $isInstitution ? __('Institution information') : ($isIndividual ? __('Your details') : __('Delegation head')) }}</legend>

                            @if ($isIndividual)
                                <p class="-mt-1 text-sm leading-relaxed text-hb-muted">{{ __('Register on your own, without a delegation, for PKR :fee.', ['fee' => number_format(RegistrationType::PrivateDelegate->amountDueFor(1))]) }}</p>
                            @endif

                            <div class="grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-4">
                                <x-public.field :label="$isInstitution || $isIndividual ? __('Institution / school / college name') : __('Delegation name')" name="contact.organisation_name">
                                    <input type="text" wire:model="contact.organisation_name" class="hb-input" autocomplete="organization">
                                </x-public.field>

                                <x-public.field :label="__('City')" name="contact.city">
                                    <input type="text" wire:model="contact.city" class="hb-input" placeholder="Lahore" autocomplete="address-level2">
                                </x-public.field>

                                @php($who = $isInstitution ? __('Representative’s') : ($isIndividual ? __('Your') : __('Head’s')))
                                <x-public.field :label="$who.' '.__('full name')" name="contact.name">
                                    <input type="text" wire:model="contact.name" class="hb-input" autocomplete="name">
                                </x-public.field>
                                <x-public.field :label="$who.' '.__('CNIC / B-Form')" name="contact.cnic">
                                    <input type="text" wire:model="contact.cnic" class="hb-input" inputmode="numeric" x-mask="{{ str_repeat('9', 30) }}" placeholder="{{ __('Numbers only') }}">
                                </x-public.field>
                                <x-public.field :label="$who.' '.__('phone (WhatsApp)')" name="contact.phone">
                                    <input type="tel" wire:model="contact.phone" class="hb-input" inputmode="tel" placeholder="03XX XXXXXXX / +44 7700 900123" autocomplete="tel">
                                </x-public.field>
                                <x-public.field :label="$who.' '.__('email')" name="contact.email">
                                    <input type="email" wire:model="contact.email" class="hb-input" autocomplete="email">
                                </x-public.field>
                                <x-public.field :label="__('Referral code (optional)')" name="contact.referral_code" :hint="__('If a delegate referred you, enter their referral code.')">
                                    <input type="text" wire:model="contact.referral_code" class="hb-input uppercase" maxlength="50" autocomplete="off" autocapitalize="characters" spellcheck="false">
                                </x-public.field>
                            </div>
                        </fieldset>
                    @elseif ($step === 2)
                        {{-- 2. Delegates --}}
                        <fieldset class="flex flex-col gap-4">
                            <legend class="hb-legend">{{ $isIndividual ? __('Delegate details & committees') : __('Delegates') }}</legend>

                            @if ($isIndividual)
                                <p class="text-sm leading-relaxed text-hb-muted">{{ __('We’ve filled in the details from step 1. Choose your two committee preferences.') }}</p>
                            @else
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
                            @endif
                            @if ($delegateCount === null)
                                <p class="text-sm leading-relaxed text-hb-muted">{{ __('Pick how many delegates are coming, then fill in each one’s details.') }}</p>
                            @else
                                <div class="flex items-baseline justify-between gap-3 border border-hb-line bg-hb-surface px-4 py-3 lg:hidden">
                                    <span class="text-xs tracking-[0.16em] text-hb-subtle uppercase">{{ __('Total') }}</span>
                                    <span class="text-right">
                                        <span class="font-display text-xl font-bold">PKR {{ number_format($this->amountDue) }}</span>
                                        @if ($hbSaving > 0)
                                            <span class="block text-xs text-hb-lavender">{{ __(':percent% Homebridge discount (−PKR :saving)', ['percent' => $hbPercent, 'saving' => number_format($hbSaving)]) }}</span>
                                        @endif
                                    </span>
                                </div>
                            @endif
                            <div @error('delegates') data-invalid @enderror>
                                <x-public.error name="delegates" />
                            </div>

                            @foreach ($delegates as $index => $delegate)
                                <div wire:key="delegate-{{ $index }}" class="flex flex-col gap-3.5 border border-hb-line bg-hb-surface p-4 sm:p-5">
                                    <div class="text-xs tracking-[0.18em] text-hb-lavender uppercase">{{ $isIndividual ? __('Delegate') : __('Delegate :number', ['number' => $index + 1]) }}</div>
                                    <div class="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-3">
                                        <x-public.field :label="__('Full name')" name="delegates.{{ $index }}.name">
                                            <input type="text" wire:model="delegates.{{ $index }}.name" class="hb-input">
                                        </x-public.field>
                                        <x-public.field :label="__('CNIC / B-Form')" name="delegates.{{ $index }}.cnic">
                                            <input type="text" wire:model="delegates.{{ $index }}.cnic" class="hb-input" inputmode="numeric" x-mask="{{ str_repeat('9', 30) }}" placeholder="{{ __('Numbers only') }}">
                                        </x-public.field>
                                        <x-public.field :label="__('Email (Gmail)')" name="delegates.{{ $index }}.email">
                                            <input type="email" wire:model="delegates.{{ $index }}.email" class="hb-input" placeholder="name@gmail.com">
                                        </x-public.field>
                                        <x-public.field :label="__('Phone (WhatsApp)')" name="delegates.{{ $index }}.phone">
                                            <input type="tel" wire:model="delegates.{{ $index }}.phone" class="hb-input" inputmode="tel" placeholder="03XX XXXXXXX / +44 7700 900123">
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
                                        @foreach (['first', 'second'] as $slot)
                                            @if ($chosen = $this->chosenCommittee($index, $slot))
                                                @php($isCharacter = $chosen->portfolio_type === PortfolioType::Character)
                                                @php($example = $chosen->code === 'SPECIAL' ? __('Tony Stark / Iron Man') : ($isCharacter ? __('Maryam Nawaz') : __('France')))
                                                @php($pickerLabel = $chosen->code === 'SPECIAL' ? __('Marvel character') : __(':committee :kind', ['committee' => $chosen->code, 'kind' => $isCharacter ? __('personality') : __('country')]))
                                                <x-public.field :label="$pickerLabel" name="delegates.{{ $index }}.{{ $slot }}_preference" :hint="$slot === 'first' ? __('1st choice') : __('2nd choice')">
                                                    @if (($options = $this->preferenceOptions($index, $slot)) !== null)
                                                        <x-portfolio-select wire:model="delegates.{{ $index }}.{{ $slot }}_preference" wire:key="{{ $slot }}-pref-{{ $index }}-{{ $chosen->id }}" :options="$options" :placeholder="__('Search, e.g. :example', ['example' => $example])" />
                                                    @else
                                                        <input type="text" wire:model="delegates.{{ $index }}.{{ $slot }}_preference" wire:key="{{ $slot }}-pref-{{ $index }}-{{ $chosen->id }}" class="hb-input" placeholder="{{ __('e.g. :example', ['example' => $example]) }}">
                                                    @endif
                                                </x-public.field>
                                            @endif
                                        @endforeach
                                        <x-public.field :label="__('Previous MUN experience (optional)')" name="delegates.{{ $index }}.mun_experience" class="col-span-full">
                                            <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="{{ __('Previous MUN experience') }}">
                                                @foreach (MunExperience::cases() as $level)
                                                    <label wire:key="experience-{{ $index }}-{{ $level->value }}" class="flex h-11 cursor-pointer items-center rounded-xs border border-[#3a3542] bg-hb-field px-4 text-sm text-white hover:border-[#6e6a75] has-checked:border-white has-checked:bg-white has-checked:font-semibold has-checked:text-hb-purple has-focus-visible:outline-2 has-focus-visible:outline-[#8e6bc8]">
                                                        <input type="radio" wire:model="delegates.{{ $index }}.mun_experience" name="delegates-{{ $index }}-mun-experience" value="{{ $level->value }}" class="sr-only">
                                                        {{ __($level->label()) }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        </x-public.field>
                                        @unless ($isIndividual)
                                        <label class="col-span-full flex cursor-pointer items-start gap-3 rounded-xs border border-[#3a3542] bg-hb-field px-4 py-3 text-sm has-checked:border-[#a98bd6] has-checked:bg-hb-purple/40 has-focus-visible:outline-2 has-focus-visible:outline-[#8e6bc8]">
                                            <input type="checkbox" wire:model.live="delegates.{{ $index }}.is_homebridge" class="mt-0.5 size-5 shrink-0 accent-[#a98bd6]" data-test="homebridge-{{ $index }}">
                                            <span class="flex flex-col gap-0.5">
                                                <span class="font-semibold text-white">{{ __('Homebridge student') }}</span>
                                                <span class="text-hb-muted">{{ __(':percent% off for Homebridge students in a delegation. The registration team checks this before verifying.', ['percent' => $hbPercent]) }}</span>
                                            </span>
                                        </label>
                                        @endunless
                                    </div>
                                </div>
                            @endforeach
                        </fieldset>
                    @elseif ($step === 3)
                        {{-- 3. Review --}}
                        <div class="flex flex-col gap-6" data-test="review-step" data-clarity-mask="true">
                            <div>
                                <h2 class="hb-legend">{{ __('Check your details') }}</h2>
                                <p class="text-sm leading-relaxed text-hb-muted">{{ __('Make sure everything is spelled correctly. Names and emails are used for certificates and logins.') }}</p>
                            </div>

                            @foreach ([1, 2] as $reviewStep)
                                @if ($issues = $this->issuesOnStep($reviewStep))
                                    <div wire:key="review-issues-{{ $reviewStep }}" class="flex flex-col gap-2 border border-amber-300/40 bg-amber-300/10 p-4 text-sm" data-test="review-issues-{{ $reviewStep }}">
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="font-semibold text-amber-100">{{ __('Still needed on Step :number (:label)', ['number' => $reviewStep, 'label' => __($this::STEPS[$reviewStep])]) }}</span>
                                            <button type="button" wire:click="editStep({{ $reviewStep }})" class="font-semibold text-amber-200 underline-offset-4 hover:underline">{{ __('Fix') }}</button>
                                        </div>
                                        <ul class="list-disc pl-5 text-amber-100/90">
                                            @foreach ($issues as $issue)
                                                <li>{{ $issue }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            @endforeach

                            <section class="flex flex-col gap-3 border border-hb-line bg-hb-surface p-5">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="font-display font-semibold">{{ $isInstitution ? __('Institution') : ($isIndividual ? __('Your details') : __('Delegation head')) }}</h3>
                                    <button type="button" wire:click="editStep(1)" class="text-sm text-hb-lavender hover:underline">{{ __('Edit') }}</button>
                                </div>
                                <dl class="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-3 text-sm">
                                    @foreach ([
                                        __('Registration type') => $this->registrationType->label(),
                                        ($isInstitution || $isIndividual ? __('Institution') : __('Delegation name')) => $contact['organisation_name'],
                                        __('City') => $contact['city'],
                                        __('Full name') => $contact['name'],
                                        __('CNIC / B-Form') => $contact['cnic'],
                                        __('Phone') => $contact['phone'],
                                        __('Email') => $contact['email'],
                                        __('Referral code') => Str::upper(trim($contact['referral_code'] ?? '')) ?: __('None'),
                                    ] as $label => $value)
                                        <div class="min-w-0">
                                            <dt class="text-xs text-hb-subtle">{{ $label }}</dt>
                                            <dd @class(['mt-0.5 font-medium break-words', 'text-amber-200' => blank($value)])>{{ filled($value) ? $value : __('Missing') }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </section>

                            <section class="flex flex-col gap-3 border border-hb-line bg-hb-surface p-5">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="font-display font-semibold">{{ $delegates === [] ? __('Delegates') : (count($delegates) === 1 ? __('1 delegate') : __(':count delegates', ['count' => count($delegates)])) }}</h3>
                                    <button type="button" wire:click="editStep(2)" class="text-sm text-hb-lavender hover:underline">{{ __('Edit') }}</button>
                                </div>
                                @if ($delegates === [])
                                    <p class="text-sm text-amber-200">{{ __('Number of delegates not chosen yet.') }}</p>
                                @endif
                                @foreach ($delegates as $index => $delegate)
                                    @php($first = $this->committees->firstWhere('id', (int) $delegate['first_committee_id']))
                                    @php($second = $this->committees->firstWhere('id', (int) $delegate['second_committee_id']))
                                    <div wire:key="review-{{ $index }}" class="flex flex-col gap-1 border-t border-hb-line pt-3 text-sm first-of-type:border-0 first-of-type:pt-0">
                                        <div class="font-semibold">{{ $index + 1 }}. {{ $delegate['name'] ?: __('(name missing)') }}</div>
                                        <div class="break-words text-hb-muted">{{ $delegate['email'] }} · {{ $delegate['phone'] }} · {{ $delegate['cnic'] }}</div>
                                        <div class="text-hb-soft">
                                            {{ __('1st: :committee', ['committee' => $first?->code]) }}@if ($first && $delegate['first_preference']) ({{ $delegate['first_preference'] }})@endif
                                            · {{ __('2nd: :committee', ['committee' => $second?->code]) }}@if ($second && $delegate['second_preference']) ({{ $delegate['second_preference'] }})@endif
                                        </div>
                                        @if ($experience = MunExperience::tryFrom($delegate['mun_experience'] ?? ''))
                                            <div class="text-hb-muted">{{ __('Experience: :experience', ['experience' => __($experience->label())]) }}</div>
                                        @endif
                                        @if (! $isIndividual && ($delegate['is_homebridge'] ?? false))
                                            <div class="text-hb-lavender">{{ __('Homebridge student · :percent% off', ['percent' => $hbPercent]) }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </section>

                            <div class="flex items-center justify-between gap-3 border border-hb-purple-line bg-hb-purple p-5">
                                <span class="text-sm tracking-[0.12em] uppercase">{{ __('Amount to pay') }}</span>
                                <span class="text-right">
                                    <span class="font-display text-2xl font-bold">{{ $delegates === [] ? '—' : 'PKR '.number_format($this->amountDue) }}</span>
                                    @if ($hbSaving > 0)
                                        <span class="block text-xs text-[#dccff0]">{{ __('After :percent% Homebridge discount (−PKR :saving)', ['percent' => $hbPercent, 'saving' => number_format($hbSaving)]) }}</span>
                                    @endif
                                </span>
                            </div>
                        </div>
                    @else
                        {{-- 4. Payment --}}
                        <fieldset class="flex flex-col gap-4">
                            <legend class="hb-legend">{{ __('Payment & confirmation') }}</legend>
                            <dl class="grid grid-cols-[repeat(auto-fit,minmax(190px,1fr))] gap-4 border border-hb-purple-line bg-hb-purple p-6">
                                @foreach ([
                                    __('Account title') => config('conference.payment.account_title'),
                                    __('Service') => config('conference.payment.service'),
                                    __('Account no. / IBAN') => config('conference.payment.account_number'),
                                ] as $label => $value)
                                    <div>
                                        <dt class="text-xs tracking-[0.16em] text-[#dccff0] uppercase">{{ $label }}</dt>
                                        @if ($value && $loop->last)
                                            {{-- The account number is the one people retype into their banking app, so it gets a copy button. --}}
                                            <dd class="mt-1 flex items-start gap-2" x-data="{
                                                copied: false,
                                                async copy() {
                                                    const text = @js(preg_replace('/\s+/', '', $value));
                                                    try {
                                                        await navigator.clipboard.writeText(text);
                                                    } catch {
                                                        const field = Object.assign(document.createElement('textarea'), { value: text });
                                                        document.body.append(field);
                                                        field.select();
                                                        document.execCommand('copy');
                                                        field.remove();
                                                    }
                                                    this.copied = true;
                                                    clearTimeout(this.timer);
                                                    this.timer = setTimeout(() => this.copied = false, 2000);
                                                },
                                            }">
                                                <span class="min-w-0 font-semibold break-all">{{ $value }}</span>
                                                <button type="button" x-on:click="copy()" class="-mt-0.5 shrink-0 rounded-xs p-1 text-[#dccff0] transition hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-[#dccff0]" x-bind:aria-label="copied ? @js(__('Copied')) : @js(__('Copy account number'))" title="{{ __('Copy account number') }}">
                                                    <svg x-show="! copied" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="1.5" /><path d="M5 15H4.5A1.5 1.5 0 0 1 3 13.5v-9A1.5 1.5 0 0 1 4.5 3h9A1.5 1.5 0 0 1 15 4.5V5" /></svg>
                                                    <svg x-show="copied" x-cloak class="size-5 text-green-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
                                                </button>
                                                <span class="sr-only" role="status" x-text="copied ? @js(__('Account number copied')) : ''"></span>
                                            </dd>
                                        @else
                                            <dd class="mt-1 font-semibold break-words">{{ $value ?: __('To be announced') }}</dd>
                                        @endif
                                    </div>
                                @endforeach
                                <div>
                                    <dt class="text-xs tracking-[0.16em] text-[#dccff0] uppercase">{{ __('Send exactly') }}</dt>
                                    @if ($delegates === [])
                                        <dd class="mt-1 text-sm">{{ __('Choose the number of delegates on Step 2 first.') }}</dd>
                                    @else
                                        <dd class="mt-0.5 font-display text-2xl font-bold">PKR {{ number_format($this->amountDue) }}</dd>
                                    @endif
                                </div>
                            </dl>

                            <x-public.screenshot-picker :screenshots="$paymentScreenshots" :hint="__('A clear screenshot of each transaction, showing the confirmation and the amount paid. Paid in more than one transfer? Upload one screenshot per receipt, up to :max. JPG, JPEG or PNG, up to 5 MB each.', ['max' => PaymentScreenshot::MAX_UPLOADS])" />

                            @if (config('conference.contacts.heads') || config('conference.contacts.email'))
                                <div class="flex flex-col gap-3 border border-hb-line p-5">
                                    <div class="font-display font-semibold">{{ __('Need help?') }}</div>
                                    <dl class="grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-3 text-sm">
                                        @foreach (config('conference.contacts.heads') as $head)
                                            <div>
                                                <dt class="text-xs text-hb-subtle">{{ __('Registration Head :number', ['number' => $loop->iteration]) }}</dt>
                                                @if ($head['name'])
                                                    <dd class="mt-1">{{ $head['name'] }}</dd>
                                                @endif
                                                <dd @class(['mt-1' => ! $head['name']])>
                                                    <a href="{{ \App\Support\WhatsApp::link($head['phone'], __('Hi, I am looking for help with my :conference registration.', ['conference' => config('conference.name')])) ?? 'tel:'.$head['phone'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 hover:text-hb-lavender" title="{{ __('Message on WhatsApp') }}">
                                                        <svg class="size-4 shrink-0 text-[#5fd68a]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.4.1-.6.3-.2.2-.8.8-.8 2s.9 2.3 1 2.5c.1.2 1.7 2.6 4.1 3.6 1.5.7 2.1.7 2.9.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.1-1.2l-.4-.2Z"/></svg>
                                                        {{ $head['phone'] }}
                                                    </a>
                                                </dd>
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

                        <div class="flex flex-col gap-2" @error('confirmed') data-invalid @enderror>
                            <label class="flex items-start gap-3 text-sm leading-relaxed text-hb-soft">
                                <input type="checkbox" wire:model="confirmed" class="mt-0.5 size-5 accent-[#8e6bc8]">
                                <span>{{ __('I confirm the details are correct and the amount paid matches my registration category.') }}</span>
                            </label>
                            <x-public.error name="confirmed" />
                        </div>
                    @endif

                    <div class="flex flex-col gap-4 border-t border-hb-line pt-5">
                        @if (in_array($step, $stepsWithIssues, true) && $step !== 3)
                            <p class="hb-error" role="alert">
                                {{ $step === 4 || $attemptedSubmit ? __('Please fix the highlighted fields above before submitting.') : __('Some fields above are missing or need a fix. You can continue and come back to them.') }}
                            </p>
                        @endif

                        <div class="flex flex-wrap items-center gap-3">
                            @if ($step > 1)
                                <button type="button" wire:click="back" class="hb-button-secondary min-h-13">{{ __('Back') }}</button>
                            @endif

                            @if ($step < 4)
                                <button type="submit" class="hb-button-primary min-h-13 px-10 text-base" wire:loading.attr="disabled" wire:target="next" data-test="next-step">
                                    <span wire:loading.remove wire:target="next">{{ $step === 3 ? ($stepsWithIssues === [] ? __('Looks good, continue to payment') : __('Continue to payment')) : __('Continue') }}</span>
                                    <span wire:loading wire:target="next">{{ __('Loading…') }}</span>
                                </button>
                            @else
                                <button type="submit" class="hb-button-primary min-h-13 px-10 text-base" wire:loading.attr="disabled" wire:target="submit,newScreenshot" data-test="submit-registration">
                                    <span wire:loading.remove wire:target="submit">{{ __('Submit registration') }}</span>
                                    <span wire:loading wire:target="submit">{{ __('Submitting…') }}</span>
                                </button>
                            @endif
                        </div>

                        <p class="text-xs leading-relaxed text-hb-subtle">{{ __('Your answers are saved on this device as you go, so you can reload without losing them. They’re cleared once you submit.') }}</p>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

@script
<script>
    const KEY = 'hbmun-registration-draft-v1';
    const MAX_AGE = 1000 * 60 * 60 * 24 * 7;

    const clearDraft = () => {
        try {
            localStorage.removeItem(KEY);
        } catch (e) {}
    };

    // Listened for on the component, because the form is gone once the success screen shows.
    $wire.on('registration-draft-cleared', clearDraft);

    const hasAnswers = (draft) => [
        ...Object.values(draft.contact ?? {}),
        ...(draft.delegates ?? []).flatMap((delegate) => Object.values(delegate ?? {})),
    ].some((value) => typeof value === 'string' && value.trim() !== '');

    Alpine.data('registrationDraft', () => ({
        last: null,

        init() {
            let draft = null;

            try {
                draft = JSON.parse(localStorage.getItem(KEY) ?? 'null');
            } catch (e) {}

            if (draft && Date.now() - (draft.savedAt ?? 0) < MAX_AGE && hasAnswers(draft)) {
                this.$wire.restoreDraft(draft);
            } else {
                clearDraft();
            }

            // Also catch step changes and anything an input event missed.
            this.timer = setInterval(() => this.save(), 2000);
        },

        destroy() {
            clearInterval(this.timer);
        },

        save() {
            const draft = {
                type: this.$wire.type,
                step: this.$wire.step,
                contact: this.$wire.contact,
                delegateCount: this.$wire.delegateCount,
                delegates: this.$wire.delegates,
            };
            const json = JSON.stringify(draft);

            if (json === this.last || !hasAnswers(draft)) {
                return;
            }

            this.last = json;

            try {
                localStorage.setItem(KEY, JSON.stringify({ ...draft, savedAt: Date.now() }));
            } catch (e) {}
        },
    }));
</script>
@endscript
