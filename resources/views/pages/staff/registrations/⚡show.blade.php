<?php

use App\Actions\Registrations\AddPaymentScreenshot;
use App\Actions\Registrations\RejectRegistration;
use App\Actions\Registrations\RemovePaymentScreenshot;
use App\Actions\Registrations\UpdateDelegate;
use App\Actions\Registrations\UpdatePaymentAmount;
use App\Actions\Registrations\VerifyRegistration;
use App\Enums\PortfolioType;
use App\Enums\RegistrationStatus;
use App\Models\AuditLog;
use App\Models\Committee;
use App\Models\Delegate;
use App\Models\PaymentScreenshot;
use App\Models\Registration;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Review registration')] class extends Component {
    use WithFileUploads;

    public Registration $registration;

    public string $rejectionReason = '';

    /**
     * The amount received, entered when verifying (fees can be discounted).
     */
    public string $amountPaid = '';

    /**
     * The corrected amount an admin enters for an already-verified registration.
     */
    public string $editedAmount = '';

    /**
     * A payment slip staff are adding, e.g. a second receipt sent over WhatsApp. Saved as soon as it uploads.
     */
    public $newSlip;

    #[Locked]
    public ?int $editingDelegateId = null;

    /** @var array{name: string, email: string, phone: string, cnic: string, first_committee_id: string, second_committee_id: string, first_preference: string, second_preference: string} */
    public array $delegateForm = [
        'name' => '',
        'email' => '',
        'phone' => '',
        'cnic' => '',
        'first_committee_id' => '',
        'second_committee_id' => '',
        'first_preference' => '',
        'second_preference' => '',
    ];

    /**
     * Mount the component.
     */
    public function mount(Registration $registration): void
    {
        $this->registration = $registration;
        $this->amountPaid = (string) $registration->amount_due;
    }

    /**
     * Verify the payment and send each delegate their set-password email.
     */
    public function verify(VerifyRegistration $verifyRegistration): void
    {
        Gate::authorize('review', $this->registration);

        if (! $this->registration->isPending()) {
            Flux::toast(variant: 'warning', text: __('This registration has already been reviewed.'));

            return;
        }

        $this->validate(
            ['amountPaid' => ['required', 'integer', 'min:0', 'max:10000000']],
            attributes: ['amountPaid' => 'amount received'],
        );

        $verifyRegistration->handle($this->registration, Auth::user(), (int) $this->amountPaid);
        unset($this->auditLogs);

        Flux::modal('verify-registration')->close();
        Flux::toast(variant: 'success', text: __(':reference verified. Set-password emails are on their way.', ['reference' => $this->registration->reference]));
    }

    /**
     * Open the admin's edit-amount form with the current amount received.
     */
    public function editAmount(): void
    {
        abort_unless($this->canEditAmount, 403);

        $this->resetValidation('editedAmount');
        $this->editedAmount = (string) $this->registration->amountReceived();
        Flux::modal('edit-amount')->show();
    }

    /**
     * Save a corrected amount received for a verified registration (admins only).
     */
    public function saveAmount(UpdatePaymentAmount $updatePaymentAmount): void
    {
        abort_unless($this->canEditAmount, 403);

        $this->validate(
            ['editedAmount' => ['required', 'integer', 'min:0', 'max:10000000']],
            attributes: ['editedAmount' => 'amount received'],
        );

        $updatePaymentAmount->handle($this->registration, Auth::user(), (int) $this->editedAmount);

        unset($this->auditLogs);
        Flux::modal('edit-amount')->close();
        Flux::toast(variant: 'success', text: __('Amount received updated to PKR :amount.', ['amount' => number_format((int) $this->editedAmount)]));
    }

    /**
     * Reject the payment with a reason the contact will see.
     */
    public function reject(RejectRegistration $rejectRegistration): void
    {
        Gate::authorize('review', $this->registration);

        $this->validate(['rejectionReason' => ['required', 'string', 'min:5', 'max:500']], attributes: ['rejectionReason' => 'reason']);

        if (! $this->registration->isPending()) {
            Flux::toast(variant: 'warning', text: __('This registration has already been reviewed.'));

            return;
        }

        $rejectRegistration->handle($this->registration, Auth::user(), $this->rejectionReason);

        $this->reset('rejectionReason');
        Flux::modal('reject-registration')->close();
        Flux::toast(variant: 'success', text: __(':reference rejected. The contact has been emailed.', ['reference' => $this->registration->reference]));
    }

    /**
     * Attach the slip that just uploaded to this registration.
     */
    public function updatedNewSlip(AddPaymentScreenshot $addPaymentScreenshot): void
    {
        Gate::authorize('review', $this->registration);

        $this->resetValidation('newSlip');

        $validator = Validator::make(
            ['newSlip' => $this->newSlip],
            ['newSlip' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120']],
            attributes: ['newSlip' => 'payment slip'],
        );

        if ($validator->fails()) {
            $this->addError('newSlip', $validator->errors()->first('newSlip'));
            $this->reset('newSlip');

            return;
        }

        $addPaymentScreenshot->handle($this->registration, $this->newSlip, Auth::user());

        $this->reset('newSlip');
        unset($this->paymentScreenshots);
        Flux::toast(variant: 'success', text: __('Payment slip added.'));
    }

    /**
     * Take a slip off this registration, as long as at least one is left.
     */
    public function removeSlip(int $screenshotId, RemovePaymentScreenshot $removePaymentScreenshot): void
    {
        Gate::authorize('review', $this->registration);

        $screenshot = $this->registration->paymentScreenshots()->findOrFail($screenshotId);

        if ($this->registration->paymentScreenshots()->count() <= 1) {
            Flux::toast(variant: 'warning', text: __('A registration needs at least one payment slip.'));

            return;
        }

        $removePaymentScreenshot->handle($screenshot, Auth::user());

        unset($this->paymentScreenshots);
        Flux::toast(variant: 'success', text: __('Payment slip removed.'));
    }

    /**
     * @return Collection<int, PaymentScreenshot>
     */
    #[Computed]
    public function paymentScreenshots(): Collection
    {
        return $this->registration->paymentScreenshots()->with('uploader')->get();
    }

    /**
     * Open the edit form for one of this registration's delegates.
     */
    public function editDelegate(int $delegateId): void
    {
        Gate::authorize('review', $this->registration);

        $delegate = $this->findDelegate($delegateId);
        $this->resetValidation();
        $this->editingDelegateId = $delegate->id;
        $this->delegateForm = [
            'name' => $delegate->name,
            'email' => $delegate->email,
            'phone' => $delegate->phone,
            'cnic' => $delegate->cnic,
            'first_committee_id' => (string) $delegate->first_committee_id,
            'second_committee_id' => (string) $delegate->second_committee_id,
            'first_preference' => (string) $delegate->preferenceFor($delegate->firstCommittee),
            'second_preference' => (string) $delegate->preferenceFor($delegate->secondCommittee),
        ];

        Flux::modal('edit-delegate')->show();
    }

    /**
     * Save the corrected details, resending the set-password email if the address changed.
     */
    public function saveDelegate(UpdateDelegate $updateDelegate): void
    {
        Gate::authorize('review', $this->registration);

        $delegate = $this->findDelegate((int) $this->editingDelegateId);

        $this->delegateForm['email'] = trim($this->delegateForm['email']);

        $validated = $this->validate([
            'delegateForm.name' => ['required', 'string', 'max:255'],
            'delegateForm.email' => [
                'required', 'email', 'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($delegate) {
                    $taken = Delegate::query()
                        ->whereKeyNot($delegate->id)
                        ->where('email', $value)
                        ->whereHas('registration', fn ($query) => $query->where('status', '!=', RegistrationStatus::Rejected))
                        ->exists();

                    if ($taken) {
                        $fail(__('Another delegate is already registered with this email.'));
                    }
                },
                Rule::unique(User::class, 'email')->ignore($delegate->user_id),
            ],
            'delegateForm.phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\s\-()]+$/'],
            'delegateForm.cnic' => ['required', 'string', 'regex:/^[0-9-]+$/', 'max:30'],
            'delegateForm.first_committee_id' => ['required', Rule::exists('committees', 'id')],
            'delegateForm.second_committee_id' => ['required', Rule::exists('committees', 'id'), 'different:delegateForm.first_committee_id'],
            'delegateForm.first_preference' => ['nullable', 'string', 'max:255', $this->onFormPreferenceList('first')],
            'delegateForm.second_preference' => ['nullable', 'string', 'max:255', $this->onFormPreferenceList('second')],
        ], attributes: [
            'delegateForm.name' => 'full name',
            'delegateForm.email' => 'email',
            'delegateForm.phone' => 'phone number',
            'delegateForm.cnic' => 'CNIC / B-Form',
            'delegateForm.first_committee_id' => '1st committee',
            'delegateForm.second_committee_id' => '2nd committee',
            'delegateForm.first_preference' => '1st committee preference',
            'delegateForm.second_preference' => '2nd committee preference',
        ], messages: [
            'delegateForm.email.unique' => __('This email already belongs to another account.'),
        ])['delegateForm'];

        $validated['first_preference'] = $validated['first_preference'] ?: null;
        $validated['second_preference'] = $validated['second_preference'] ?: null;

        $emailed = $updateDelegate->handle($delegate, Auth::user(), $validated);

        $this->registration->refresh();
        unset($this->delegates, $this->auditLogs);
        $this->editingDelegateId = null;

        Flux::modal('edit-delegate')->close();
        Flux::toast(variant: 'success', text: $emailed
            ? __('Saved. A new set-password email is on its way to :email.', ['email' => $delegate->email])
            : __(':name’s details are saved.', ['name' => $delegate->name]));
    }

    /**
     * Send a delegate a fresh set-password link, for when the first one was lost or expired.
     */
    public function resendSetPassword(int $delegateId, UpdateDelegate $updateDelegate): void
    {
        Gate::authorize('review', $this->registration);

        $delegate = $this->findDelegate($delegateId);

        if (! $delegate->user) {
            Flux::toast(variant: 'warning', text: __('This delegate has no account yet. Verify the registration first.'));

            return;
        }

        $updateDelegate->sendSetPasswordLink($delegate->user);
        AuditLog::record('set_password_resent', $this->registration, Auth::user(), ['reason' => $delegate->user->email]);
        unset($this->auditLogs);

        Flux::toast(variant: 'success', text: __('Set-password email sent to :email.', ['email' => $delegate->user->email]));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Committee>
     */
    #[Computed]
    public function committees(): Collection
    {
        return Committee::ordered()->get();
    }

    /**
     * The committee picked as the 1st or 2nd choice in the edit form.
     */
    public function formCommittee(string $slot): ?Committee
    {
        $id = $this->delegateForm[$slot.'_committee_id'] ?? null;

        return $id ? $this->committees->firstWhere('id', (int) $id) : null;
    }

    /**
     * The same choices the registration form offers for one committee choice: that committee's
     * public list, or null to type freely when the committee keeps its list secret.
     *
     * @return list<string>|null
     */
    public function formPreferenceOptions(string $slot): ?array
    {
        return $this->formCommittee($slot)?->publicPortfolioOptions();
    }

    /**
     * A new committee starts with a blank preference, as on the registration form.
     */
    public function updatedDelegateForm(mixed $value, string $key): void
    {
        if (in_array($key, ['first_committee_id', 'second_committee_id'], true)) {
            $this->delegateForm[str_replace('_committee_id', '', $key).'_preference'] = '';
        }
    }

    private function onFormPreferenceList(string $slot): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($slot): void {
            $options = $this->formPreferenceOptions($slot);

            if ($options !== null && ! in_array($value, $options, true)) {
                $fail(__('Choose the :attribute from the list.'));
            }
        };
    }

    private function findDelegate(int $delegateId): Delegate
    {
        return $this->registration->delegates()->with('user')->findOrFail($delegateId);
    }

    public function useReason(string $reason): void
    {
        $this->rejectionReason = $reason;
    }

    /**
     * @return Collection<int, Delegate>
     */
    #[Computed]
    public function delegates(): Collection
    {
        return $this->registration->delegates()->with(['firstCommittee', 'secondCommittee', 'user', 'whatsAppLogs.sender'])->get();
    }

    /**
     * @return Collection<int, AuditLog>
     */
    #[Computed]
    public function auditLogs(): Collection
    {
        return $this->registration->auditLogs()->with('user')->get();
    }

    #[Computed]
    public function canReview(): bool
    {
        return Auth::user()->can('review', $this->registration);
    }

    #[Computed]
    public function canEditAmount(): bool
    {
        return Auth::user()->isAdmin() && $this->registration->status === RegistrationStatus::Verified;
    }
}; ?>

<div class="flex w-full flex-col gap-6 p-6 lg:p-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-col gap-2">
            <flux:link :href="route('staff.registrations.index')" wire:navigate class="text-sm">← {{ __('All registrations') }}</flux:link>
            <div class="flex flex-wrap items-center gap-3">
                <flux:heading size="xl" level="1">{{ $registration->reference }}</flux:heading>
                <flux:badge :color="$registration->status->color()">{{ $registration->status->label() }}</flux:badge>
            </div>
            <flux:text>{{ $registration->type->label() }} · {{ $registration->displayName() }}</flux:text>
        </div>

        @if ($this->canReview && $registration->isPending())
            <div class="flex gap-2">
                <flux:modal.trigger name="reject-registration">
                    <flux:button variant="danger" icon="x-mark">{{ __('Reject') }}</flux:button>
                </flux:modal.trigger>
                <flux:modal.trigger name="verify-registration">
                    <flux:button variant="primary" icon="check" data-test="open-verify">{{ __('Verify payment') }}</flux:button>
                </flux:modal.trigger>
            </div>
        @endif
    </div>

    @if ($registration->isRejected())
        <flux:callout variant="danger" icon="exclamation-triangle" :heading="__('Rejected')" :text="$registration->rejection_reason" />
    @endif

    <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
        {{-- Payment --}}
        <flux:card class="flex flex-col gap-4">
            <div class="flex items-start justify-between gap-4">
                <flux:heading size="lg">{{ __('Payment') }}</flux:heading>
                <div class="flex flex-col items-end gap-1 text-right">
                    <div class="flex items-center gap-1">
                        <flux:heading size="lg" data-test="amount-received">PKR {{ number_format($registration->amountReceived()) }}</flux:heading>
                        @if ($this->canEditAmount)
                            <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="editAmount" data-test="edit-amount" :tooltip="__('Edit amount received')" />
                        @endif
                    </div>
                    @if ($registration->amount_paid === null)
                        <flux:text class="text-xs">{{ __('Full fee') }}</flux:text>
                    @elseif ($registration->amount_paid !== $registration->amount_due)
                        <flux:text class="flex items-center gap-2 text-xs">
                            {{ __('Received · full fee PKR :fee', ['fee' => number_format($registration->amount_due)]) }}
                            @if ($registration->discount() > 0)
                                <flux:badge size="sm" color="amber" inset="top bottom">{{ __('PKR :amount off', ['amount' => number_format($registration->discount())]) }}</flux:badge>
                            @endif
                        </flux:text>
                    @else
                        <flux:text class="text-xs">{{ __('Received') }}</flux:text>
                    @endif
                </div>
            </div>
            @foreach ($this->paymentScreenshots as $screenshot)
                <div class="flex flex-col gap-2" wire:key="slip-{{ $screenshot->id }}">
                    <a href="{{ route('staff.registrations.payment-screenshot', [$registration, $screenshot]) }}" target="_blank" class="block overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <img src="{{ route('staff.registrations.payment-screenshot', [$registration, $screenshot]) }}" alt="{{ __('Payment screenshot :number for :reference', ['number' => $loop->iteration, 'reference' => $registration->reference]) }}" class="max-h-[640px] w-full object-contain bg-zinc-100 dark:bg-zinc-900">
                    </a>
                    <div class="flex items-center justify-between gap-3">
                        <flux:text class="text-xs">
                            @if ($this->paymentScreenshots->count() > 1)
                                {{ __('Slip :number of :total', ['number' => $loop->iteration, 'total' => $this->paymentScreenshots->count()]) }} ·
                            @endif
                            {{ $screenshot->uploader ? __('Added by :name, :date', ['name' => $screenshot->uploader->name, 'date' => $screenshot->created_at?->format('j M, g:i a')]) : __('Uploaded by the registrant') }}
                        </flux:text>
                        @if ($this->canReview && $this->paymentScreenshots->count() > 1)
                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="removeSlip({{ $screenshot->id }})" wire:confirm="{{ __('Remove this payment slip from the registration?') }}">{{ __('Remove') }}</flux:button>
                        @endif
                    </div>
                </div>
            @endforeach
            <flux:text class="text-xs">{{ __('Click a screenshot to open it full size.') }}</flux:text>

            @if ($this->canReview)
                <div class="flex flex-col gap-2"
                    x-data="{
                        uploading: false,
                        async pick(input) {
                            const files = [...input.files];
                            input.value = '';
                            this.uploading = true;
                            for (const file of files) {
                                await new Promise((done) => this.$wire.$upload('newSlip', file, done, done));
                            }
                            this.uploading = false;
                        },
                    }">
                    <label class="inline-flex cursor-pointer items-center gap-2 self-start rounded-lg border border-zinc-200 px-3 py-2 text-sm font-medium hover:bg-zinc-50 has-focus-visible:outline-2 dark:border-zinc-700 dark:hover:bg-zinc-800">
                        <flux:icon.plus variant="mini" />
                        <span x-text="uploading ? @js(__('Uploading…')) : @js(__('Add payment slip'))">{{ __('Add payment slip') }}</span>
                        <input type="file" multiple accept=".jpg,.jpeg,.png" class="sr-only" x-on:change="pick($el)" data-test="add-slip">
                    </label>
                    <flux:text class="text-xs">{{ __('For a receipt the delegation sent separately. JPG, JPEG or PNG, up to 5 MB each.') }}</flux:text>
                    <flux:error name="newSlip" />
                </div>
            @endif
        </flux:card>

        <div class="flex flex-col gap-6">
            {{-- Contact --}}
            <flux:card class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading size="lg">{{ __('Contact') }}</flux:heading>
                    <x-whatsapp-button :href="route('staff.whatsapp.contact', $registration)" :phone="$registration->contact_phone" :sent="$registration->load('whatsAppLogs.sender')->contactWhatsApp()" />
                </div>
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    @foreach ([
                        ($registration->type->isIndividual() ? __('Institution') : __('Institution / delegation')) => $registration->organisation_name,
                        __('City') => $registration->city,
                        __('Name') => $registration->contact_name,
                        __('CNIC / B-Form') => $registration->contact_cnic,
                        __('Phone') => $registration->contact_phone,
                        __('Email') => $registration->contact_email,
                        __('Referral code') => $registration->referral_code ? $registration->referral_code.($registration->campusAmbassador ? ' · '.$registration->campusAmbassador->name : ' · '.__('no matching ambassador')) : null,
                        __('Submitted') => $registration->created_at->timezone('Asia/Karachi')->format('j M Y, g:i a'),
                        __('Reviewed') => $registration->reviewed_at ? $registration->reviewed_at->timezone('Asia/Karachi')->format('j M Y, g:i a').' · '.$registration->reviewer?->name : null,
                    ] as $label => $value)
                        @if ($value)
                            <div>
                                <dt class="text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
                                <dd class="mt-0.5 break-words text-zinc-800 dark:text-white">{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            </flux:card>

            {{-- Delegates --}}
            <flux:card class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Delegates (:count)', ['count' => $this->delegates->count()]) }}</flux:heading>
                <div class="flex flex-col divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($this->delegates as $delegate)
                        <div wire:key="delegate-{{ $delegate->id }}" class="grid gap-x-6 gap-y-2 py-3 text-sm first:pt-0 last:pb-0 sm:grid-cols-2">
                            <div>
                                <div class="font-medium text-zinc-800 dark:text-white">{{ $delegate->name }}</div>
                                <div class="text-zinc-500 dark:text-zinc-400">{{ $delegate->email }} · {{ $delegate->phone }}</div>
                                <div class="text-zinc-500 dark:text-zinc-400">{{ __('CNIC') }} {{ $delegate->cnic }}</div>
                                @if ($this->canReview)
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        <flux:button size="xs" icon="pencil-square" wire:click="editDelegate({{ $delegate->id }})" data-test="edit-delegate-{{ $delegate->id }}">{{ __('Edit details') }}</flux:button>
                                        @if ($delegate->user)
                                            <flux:button size="xs" icon="envelope" wire:click="resendSetPassword({{ $delegate->id }})" wire:confirm="{{ __('Send a new set-password email to :email?', ['email' => $delegate->user->email]) }}" data-test="resend-set-password-{{ $delegate->id }}">{{ __('Resend set-password email') }}</flux:button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <div>
                                <div>1. {{ $delegate->firstCommittee->code }} · 2. {{ $delegate->secondCommittee->code }}</div>
                                @foreach ([$delegate->firstCommittee, $delegate->secondCommittee] as $choice)
                                    @if ($preference = $delegate->preferenceFor($choice))
                                        <div class="text-zinc-500 dark:text-zinc-400">{{ $choice->code }}: {{ $preference }}</div>
                                    @endif
                                @endforeach
                                <div class="text-zinc-500 dark:text-zinc-400">{{ __('Experience') }}: {{ $delegate->mun_experience ? __($delegate->mun_experience->label()) : __('Not given') }}</div>
                                @if ($delegate->user)
                                    <div class="mt-2 flex flex-col gap-1.5">
                                        @if ($delegate->user->password_set_at)
                                            <flux:badge size="sm" color="green" class="self-start" :title="__('Set :time', ['time' => $delegate->user->password_set_at->timezone('Asia/Karachi')->format('j M, g:i a')])">{{ __('Password set') }}</flux:badge>
                                        @else
                                            <flux:badge size="sm" color="amber" class="self-start" :title="__('They haven’t used the set-password email yet')">{{ __('Password not set') }}</flux:badge>
                                        @endif
                                        <x-whatsapp-button size="xs" :label="__('WhatsApp')" :href="route('staff.whatsapp.account', $delegate)" :phone="$delegate->phone" :sent="$delegate->accountWhatsApp()" />
                                        @if (auth()->user()->canPreviewRoles())
                                            <form method="POST" action="{{ route('role-preview.store') }}">
                                                @csrf
                                                <input type="hidden" name="role" value="delegate">
                                                <input type="hidden" name="delegate" value="{{ $delegate->id }}">
                                                <flux:button size="xs" type="submit" icon="eye" data-test="view-as-delegate-{{ $delegate->id }}">{{ __('View their dashboard') }}</flux:button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </flux:card>

            {{-- Audit log --}}
            <flux:card class="flex flex-col gap-3">
                <flux:heading size="lg">{{ __('History') }}</flux:heading>
                <ul class="flex flex-col gap-2 text-sm">
                    @foreach ($this->auditLogs as $log)
                        <li wire:key="log-{{ $log->id }}" class="flex flex-wrap justify-between gap-2">
                            <span class="text-zinc-800 dark:text-white">
                                {{ str($log->action)->replace('_', ' ')->ucfirst() }}
                                @if ($log->user) {{ __('by :name', ['name' => $log->user->name]) }} @endif
                                @if ($log->properties['reason'] ?? null) <span class="text-zinc-500 dark:text-zinc-400">— {{ $log->properties['reason'] }}</span> @endif
                            </span>
                            <span class="text-zinc-500 dark:text-zinc-400">{{ $log->created_at->timezone('Asia/Karachi')->format('j M Y, g:i a') }}</span>
                        </li>
                    @endforeach
                </ul>
            </flux:card>
        </div>
    </div>

    @if ($this->canReview)
        <flux:modal name="verify-registration" class="md:w-md">
            <div class="flex flex-col gap-6">
                <div>
                    <flux:heading size="lg">{{ __('Verify :reference?', ['reference' => $registration->reference]) }}</flux:heading>
                    <flux:text class="mt-2">
                        {{ __('Enter the amount the screenshot shows was received. :count delegate account(s) will be created and each delegate will be emailed a link to set their password.', ['count' => $this->delegates->count()]) }}
                    </flux:text>
                </div>
                <flux:input wire:model="amountPaid" type="number" min="0" step="1" inputmode="numeric" :label="__('Amount received (PKR)')" :description="__('Full fee is PKR :fee. Change it if they got a discount.', ['fee' => number_format($registration->amount_due)])" data-test="amount-paid" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" wire:click="verify" data-test="confirm-verify">{{ __('Verify payment') }}</flux:button>
                </div>
            </div>
        </flux:modal>

        @if ($this->canEditAmount)
            <flux:modal name="edit-amount" class="md:w-md">
                <form wire:submit="saveAmount" class="flex flex-col gap-6">
                    <div>
                        <flux:heading size="lg">{{ __('Edit amount received') }}</flux:heading>
                        <flux:text class="mt-2">{{ __('Full fee is PKR :fee. The change is recorded in the history.', ['fee' => number_format($registration->amount_due)]) }}</flux:text>
                    </div>
                    <flux:input wire:model="editedAmount" type="number" min="0" step="1" inputmode="numeric" :label="__('Amount received (PKR)')" data-test="edited-amount" />
                    <div class="flex justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" variant="primary" data-test="save-amount">{{ __('Save') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        @endif

        <flux:modal name="edit-delegate" class="w-full md:w-xl">
            <form wire:submit="saveDelegate" class="flex flex-col gap-5">
                <div>
                    <flux:heading size="lg">{{ __('Edit delegate') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('A new email address gets a fresh set-password link.') }}</flux:text>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="delegateForm.name" :label="__('Full name')" class="sm:col-span-2" />
                    <flux:input wire:model="delegateForm.email" type="email" :label="__('Email')" class="sm:col-span-2" data-test="delegate-email" />
                    <flux:input wire:model="delegateForm.phone" :label="__('Phone')" />
                    <flux:input wire:model="delegateForm.cnic" :label="__('CNIC / B-Form')" />
                    <flux:select wire:model.live="delegateForm.first_committee_id" :label="__('1st committee')">
                        @foreach ($this->committees as $committee)
                            <flux:select.option :value="$committee->id">{{ $committee->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="delegateForm.second_committee_id" :label="__('2nd committee')">
                        @foreach ($this->committees as $committee)
                            <flux:select.option :value="$committee->id">{{ $committee->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @foreach (['first', 'second'] as $slot)
                        @if ($chosen = $this->formCommittee($slot))
                            @php($isCharacter = $chosen->portfolio_type === PortfolioType::Character)
                            @php($example = $chosen->code === 'SPECIAL' ? __('Tony Stark / Iron Man') : ($isCharacter ? __('Maryam Nawaz') : __('France')))
                            @php($pickerLabel = $chosen->code === 'SPECIAL' ? __('Marvel character') : __(':committee :kind', ['committee' => $chosen->code, 'kind' => $isCharacter ? __('personality') : __('country')]))
                            @php($options = $this->formPreferenceOptions($slot))
                            <flux:field wire:key="{{ $slot }}-pref-{{ $editingDelegateId }}-{{ $chosen->id }}">
                                <flux:label>{{ $pickerLabel }}</flux:label>
                                @if ($options === null)
                                    <flux:input wire:model="delegateForm.{{ $slot }}_preference" :placeholder="__('e.g. :example', ['example' => $example])" data-test="delegate-{{ $slot }}_preference" />
                                @else
                                    <x-portfolio-select wire:model="delegateForm.{{ $slot }}_preference" variant="staff" :options="$options" :placeholder="__('Search, e.g. :example', ['example' => $example])" data-test="delegate-{{ $slot }}_preference" />
                                @endif
                                <flux:error name="delegateForm.{{ $slot }}_preference" />
                            </flux:field>
                        @endif
                    @endforeach
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" data-test="save-delegate">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="reject-registration" class="md:w-lg">
            <form wire:submit="reject" class="flex flex-col gap-6">
                <div>
                    <flux:heading size="lg">{{ __('Reject :reference?', ['reference' => $registration->reference]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('The contact will be emailed this reason and can upload a new screenshot from the status page.') }}</flux:text>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ([
                        __('The screenshot is unclear. Please upload a clearer one.'),
                        __('The amount paid does not match your registration fee.'),
                        __('We could not find this transaction in our account.'),
                    ] as $quickReason)
                        <flux:button size="xs" type="button" wire:click="useReason(@js($quickReason))">{{ $quickReason }}</flux:button>
                    @endforeach
                </div>
                <flux:textarea wire:model="rejectionReason" :label="__('Reason')" rows="3" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger">{{ __('Reject payment') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
