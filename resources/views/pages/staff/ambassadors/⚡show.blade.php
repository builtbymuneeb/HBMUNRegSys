<?php

use App\Actions\Ambassadors\EmailAmbassadors;
use App\Livewire\Forms\AmbassadorForm;
use App\Models\AuditLog;
use App\Models\CampusAmbassador;
use App\Models\Registration;
use App\Notifications\AmbassadorMessage;
use App\Support\WhatsApp;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Campus ambassador')] class extends Component {
    public CampusAmbassador $ambassador;

    public AmbassadorForm $form;

    public string $messageSubject = '';

    public string $messageBody = '';

    #[Computed]
    public function counts(): CampusAmbassador
    {
        return CampusAmbassador::whereKey($this->ambassador->id)->withReferralCounts()->firstOrFail();
    }

    /**
     * @return Collection<int, Registration>
     */
    #[Computed]
    public function registrations(): Collection
    {
        return $this->ambassador->registrations()->withCount('delegates')->latest()->get();
    }

    public function sendWelcomeEmail(EmailAmbassadors $emailAmbassadors): void
    {
        Gate::authorize('manage-ambassadors');

        if ($emailAmbassadors->welcome([$this->ambassador], Auth::user()) === 0) {
            Flux::toast(variant: 'danger', text: __('Add an email address first.'));

            return;
        }

        Flux::toast(variant: 'success', text: __('Welcome email on its way to :email.', ['email' => $this->ambassador->email]));
    }

    public function compose(): void
    {
        $this->resetValidation();
        Flux::modal('ambassador-message')->show();
    }

    public function sendMessageEmail(EmailAmbassadors $emailAmbassadors): void
    {
        Gate::authorize('manage-ambassadors');

        $validated = $this->validate([
            'messageSubject' => ['required', 'string', 'max:150'],
            'messageBody' => ['required', 'string', 'max:2000'],
        ], attributes: ['messageSubject' => __('subject'), 'messageBody' => __('message')]);

        if ($emailAmbassadors->message([$this->ambassador], $validated['messageSubject'], $validated['messageBody'], Auth::user()) === 0) {
            $this->addError('messageBody', __('This ambassador has no email address.'));

            return;
        }

        $this->reset('messageSubject', 'messageBody');
        Flux::modal('ambassador-message')->close();
        Flux::toast(variant: 'success', text: __('Email on its way to :email.', ['email' => $this->ambassador->email]));
    }

    public function edit(): void
    {
        $this->form->edit($this->ambassador);
        Flux::modal('ambassador-form')->show();
    }

    public function save(): void
    {
        Gate::authorize('manage-ambassadors');

        $this->ambassador = $this->form->save();
        unset($this->counts, $this->registrations);

        Flux::modal('ambassador-form')->close();
        Flux::toast(variant: 'success', text: __('Ambassador saved.'));
    }

    /**
     * Clear the flag once someone has verified the ambassador's details.
     */
    public function markChecked(): void
    {
        Gate::authorize('manage-ambassadors');

        AuditLog::record('ambassador_checked', $this->ambassador, Auth::user(), ['code' => $this->ambassador->code, 'flag_reason' => $this->ambassador->flag_reason]);
        $this->ambassador->update(['flag_reason' => null]);

        Flux::toast(variant: 'success', text: __('Marked as checked.'));
    }

    public function delete(): void
    {
        Gate::authorize('manage-ambassadors');

        AuditLog::record('ambassador_deleted', $this->ambassador, Auth::user(), ['code' => $this->ambassador->code, 'name' => $this->ambassador->name]);
        $this->ambassador->delete();

        Flux::toast(text: __('Ambassador removed. Their registrations keep the code they entered.'));
        $this->redirectRoute('staff.ambassadors', navigate: true);
    }
}; ?>

<div class="flex w-full flex-col gap-6 p-6 lg:p-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:link :href="route('staff.ambassadors')" wire:navigate class="text-sm">← {{ __('Campus ambassadors') }}</flux:link>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <flux:heading size="xl" level="1">{{ $ambassador->name }}</flux:heading>
                <flux:badge color="purple">{{ $ambassador->code }}</flux:badge>
                @if ($ambassador->needsCheck())
                    <flux:badge color="amber" icon="exclamation-triangle">{{ __('Needs check') }}</flux:badge>
                @endif
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:dropdown>
                <flux:button icon="envelope" icon:trailing="chevron-down">{{ __('Email') }}</flux:button>
                <flux:menu>
                    <flux:menu.item icon="sparkles" wire:click="sendWelcomeEmail" :disabled="blank($ambassador->email)"
                        wire:confirm="{{ $ambassador->welcome_emailed_at ? __('They were already sent the welcome email. Send it again?') : __('Send the welcome email with their code and registration link?') }}">{{ __('Send welcome email') }}</flux:menu.item>
                    <flux:menu.item icon="pencil" wire:click="compose" :disabled="blank($ambassador->email)">{{ __('Write an email…') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
            <flux:dropdown>
                <flux:button icon="chat-bubble-oval-left-ellipsis" icon:trailing="chevron-down">{{ __('WhatsApp') }}</flux:button>
                <flux:menu>
                    <flux:menu.item icon="sparkles" :href="route('staff.whatsapp.ambassador.welcome', $ambassador)" target="_blank" :disabled="! WhatsApp::number($ambassador->phone)"
                        x-on:click="setTimeout(() => $wire.$refresh(), 1500)">{{ __('Welcome message') }}</flux:menu.item>
                    <flux:menu.item icon="pencil" wire:click="compose" :disabled="! WhatsApp::number($ambassador->phone)">{{ __('Write a message…') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
            <flux:button icon="pencil-square" wire:click="edit">{{ __('Edit') }}</flux:button>
            <flux:button icon="trash" variant="ghost" wire:click="delete" wire:confirm="{{ __('Remove this ambassador? Their registrations keep the code they entered, but stop counting for anyone.') }}" :aria-label="__('Remove')" />
        </div>
    </div>

    @if ($ambassador->needsCheck())
        <flux:callout icon="exclamation-triangle" color="amber" :heading="__('Needs checking')">
            <flux:callout.text class="whitespace-pre-line">{{ $ambassador->flag_reason }}</flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" icon="check" wire:click="markChecked">{{ __('Mark as checked') }}</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="edit">{{ __('Fix details') }}</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <flux:card class="flex flex-col gap-1">
            <flux:text>{{ __('Registrations') }}</flux:text>
            <flux:heading size="xl">{{ $this->counts->registrations_count }}</flux:heading>
            <flux:text size="sm" class="text-zinc-400">{{ __(':count verified', ['count' => $this->counts->verified_registrations_count]) }}</flux:text>
        </flux:card>
        <flux:card class="flex flex-col gap-1">
            <flux:text>{{ __('Delegates') }}</flux:text>
            <flux:heading size="xl">{{ $this->counts->delegates_count }}</flux:heading>
            <flux:text size="sm" class="text-zinc-400">{{ __('across all their registrations') }}</flux:text>
        </flux:card>
        <flux:card class="flex flex-col gap-1">
            <flux:text>{{ __('Verified delegates') }}</flux:text>
            <flux:heading size="xl">{{ $this->counts->verified_delegates_count }}</flux:heading>
            <flux:text size="sm" class="text-zinc-400">{{ __('payment checked') }}</flux:text>
        </flux:card>
    </div>

    <flux:card>
        <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                __('Referral code') => $ambassador->code,
                __('Email') => $ambassador->email,
                __('Phone') => $ambassador->phone,
                __('Institution') => $ambassador->institution,
                __('City') => $ambassador->city,
                __('Added') => $ambassador->created_at->timezone('Asia/Karachi')->format('j M Y'),
                __('Registration link') => $ambassador->registrationLink(),
            ] as $label => $value)
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
                    <dd class="mt-0.5 break-words text-zinc-800 dark:text-white">{{ $value ?: '—' }}</dd>
                </div>
            @endforeach
            <div>
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Welcome email') }}</dt>
                <dd class="mt-0.5">
                    @if ($ambassador->welcome_emailed_at)
                        <span class="text-green-600 dark:text-green-400">✓ {{ __('Sent :time', ['time' => $ambassador->welcome_emailed_at->timezone('Asia/Karachi')->format('j M, g:i a')]) }}</span>
                    @else
                        <span class="text-amber-600 dark:text-amber-300">{{ __('Not sent yet') }}</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('WhatsApp welcome') }}</dt>
                <dd class="mt-0.5">
                    <x-whatsapp-button size="xs" :label="__('Welcome message')" :href="route('staff.whatsapp.ambassador.welcome', $ambassador)" :phone="$ambassador->phone" :sent="$ambassador->load('whatsAppLogs.sender')->welcomeWhatsApp()" />
                </dd>
            </div>
            @if ($ambassador->notes)
                <div class="sm:col-span-2 lg:col-span-3">
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Notes') }}</dt>
                    <dd class="mt-0.5 break-words whitespace-pre-line text-zinc-800 dark:text-white">{{ $ambassador->notes }}</dd>
                </div>
            @endif
        </dl>
    </flux:card>

    <div class="flex flex-col gap-3">
        <flux:heading size="lg">{{ __('Registrations using this code') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Reference') }}</flux:table.column>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Code entered') }}</flux:table.column>
                <flux:table.column>{{ __('Delegates') }}</flux:table.column>
                <flux:table.column>{{ __('Submitted') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->registrations as $registration)
                    <flux:table.row :key="$registration->id">
                        <flux:table.cell variant="strong">{{ $registration->reference }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium text-zinc-800 dark:text-white">{{ $registration->displayName() }}</div>
                            <div class="text-xs">{{ $registration->city }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $registration->referral_code }}</flux:table.cell>
                        <flux:table.cell>{{ $registration->delegates_count }}</flux:table.cell>
                        <flux:table.cell>{{ $registration->created_at->timezone('Asia/Karachi')->format('j M, g:i a') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$registration->status->color()" inset="top bottom">{{ $registration->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @can('view', $registration)
                                <flux:button size="sm" :href="route('staff.registrations.show', $registration)" wire:navigate>{{ __('View') }}</flux:button>
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center">{{ __('Nobody has registered with this code yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    @include('partials.ambassador-form')

    <flux:modal name="ambassador-message" class="w-full md:max-w-xl">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Message :name', ['name' => $ambassador->name]) }}</flux:heading>
                <flux:text class="mt-1">{{ __('Send it by email, or open WhatsApp with it typed in. It starts with “Hello :name,”.', ['name' => $ambassador->firstName()]) }}</flux:text>
            </div>
            <flux:input wire:model="messageSubject" :label="__('Subject (email only)')" />
            <flux:textarea wire:model.live.debounce.400ms="messageBody" :label="__('Message')" rows="7"
                :description="__('You can use :placeholders.', ['placeholders' => collect(AmbassadorMessage::PLACEHOLDERS)->map(fn ($meaning, $tag) => $tag.' ('.$meaning.')')->implode(', ')])" />
            <div class="flex flex-wrap gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                @if (WhatsApp::number($ambassador->phone) && filled(trim($messageBody)))
                    <flux:button icon="chat-bubble-oval-left-ellipsis" target="_blank" rel="noopener"
                        :href="route('staff.whatsapp.ambassador.message', ['ambassador' => $ambassador, 'text' => $messageBody])"
                        x-on:click="setTimeout(() => $wire.$refresh(), 1500)">{{ __('Open in WhatsApp') }}</flux:button>
                @endif
                @if ($ambassador->email)
                    <flux:button variant="primary" icon="paper-airplane" wire:click="sendMessageEmail">{{ __('Send email') }}</flux:button>
                @endif
            </div>
        </div>
    </flux:modal>
</div>
