<?php

use App\Actions\Registrations\RejectRegistration;
use App\Actions\Registrations\VerifyRegistration;
use App\Models\AuditLog;
use App\Models\Delegate;
use App\Models\Registration;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Review registration')] class extends Component {
    public Registration $registration;

    public string $rejectionReason = '';

    /**
     * Mount the component.
     */
    public function mount(Registration $registration): void
    {
        $this->registration = $registration;
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

        $verifyRegistration->handle($this->registration, Auth::user());

        Flux::modal('verify-registration')->close();
        Flux::toast(variant: 'success', text: __(':reference verified. Set-password emails are on their way.', ['reference' => $this->registration->reference]));
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
            <div class="flex items-center justify-between gap-4">
                <flux:heading size="lg">{{ __('Payment') }}</flux:heading>
                <flux:heading size="lg">PKR {{ number_format($registration->amount_due) }}</flux:heading>
            </div>
            <a href="{{ route('staff.registrations.payment-screenshot', $registration) }}" target="_blank" class="block overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                <img src="{{ route('staff.registrations.payment-screenshot', $registration) }}" alt="{{ __('Payment screenshot for :reference', ['reference' => $registration->reference]) }}" class="max-h-[640px] w-full object-contain bg-zinc-100 dark:bg-zinc-900">
            </a>
            <flux:text class="text-xs">{{ __('Click the screenshot to open it full size.') }}</flux:text>
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
                        __('Institution / delegation') => $registration->organisation_name,
                        __('City') => $registration->city,
                        __('Name') => $registration->contact_name,
                        __('CNIC / B-Form') => $registration->contact_cnic,
                        __('Phone') => $registration->contact_phone,
                        __('Email') => $registration->contact_email,
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
                            </div>
                            <div>
                                <div>1. {{ $delegate->firstCommittee->code }} · 2. {{ $delegate->secondCommittee->code }}</div>
                                @if ($delegate->country_preference)
                                    <div class="text-zinc-500 dark:text-zinc-400">{{ __('Country') }}: {{ $delegate->country_preference }}</div>
                                @endif
                                @if ($delegate->personality_preference)
                                    <div class="text-zinc-500 dark:text-zinc-400">{{ __('Personality') }}: {{ $delegate->personality_preference }}</div>
                                @endif
                                <div class="break-words whitespace-pre-line text-zinc-500 dark:text-zinc-400">{{ __('Experience') }}: {{ $delegate->mun_experience ?: __('First MUN') }}</div>
                                @if ($delegate->user)
                                    <div class="mt-2 flex flex-col gap-1.5">
                                        @if ($delegate->user->password_set_at)
                                            <flux:badge size="sm" color="green" class="self-start" :title="__('Set :time', ['time' => $delegate->user->password_set_at->timezone('Asia/Karachi')->format('j M, g:i a')])">{{ __('Password set') }}</flux:badge>
                                        @else
                                            <flux:badge size="sm" color="amber" class="self-start" :title="__('They haven’t used the set-password email yet')">{{ __('Password not set') }}</flux:badge>
                                        @endif
                                        <x-whatsapp-button size="xs" :label="__('WhatsApp')" :href="route('staff.whatsapp.account', $delegate)" :phone="$delegate->phone" :sent="$delegate->accountWhatsApp()" />
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
                        {{ __('Confirm the screenshot shows PKR :amount received. :count delegate account(s) will be created and each delegate will be emailed a link to set their password.', ['amount' => number_format($registration->amount_due), 'count' => $this->delegates->count()]) }}
                    </flux:text>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" wire:click="verify" data-test="confirm-verify">{{ __('Verify payment') }}</flux:button>
                </div>
            </div>
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
