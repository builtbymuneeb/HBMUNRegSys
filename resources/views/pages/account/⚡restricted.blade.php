<?php

use App\Actions\Registrations\ModerateRegistration;
use App\Enums\RegistrationStatus;
use App\Models\Registration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::auth')] #[Title('Registration on hold')] class extends Component {
    public string $message = '';

    public function mount(): void
    {
        if (! $this->registration) {
            $this->redirectRoute('dashboard', navigate: true);
        }
    }

    #[Computed]
    public function registration(): ?Registration
    {
        return Auth::user()->restrictedRegistration();
    }

    public function appeal(ModerateRegistration $moderate): void
    {
        $registration = $this->registration;
        abort_unless($registration, 403);

        $this->validate(['message' => ['required', 'string', 'min:10', 'max:2000']], attributes: ['message' => 'appeal']);

        $key = 'appeal:'.Auth::id();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages(['message' => __('You’ve sent a few appeals already. Please wait for our team to reply.')]);
        }
        RateLimiter::hit($key, 3600);

        $moderate->appeal($registration, trim($this->message), Auth::user());

        $this->reset('message');
        unset($this->registration);
    }
}; ?>

<div class="mt-4 flex flex-col gap-6" data-test="restricted-page">
    @if ($registration = $this->registration)
        <div class="flex flex-col gap-2 text-center">
            <flux:heading size="lg">
                @switch($registration->status)
                    @case(RegistrationStatus::Blocked) {{ __('Your registration has been blocked') }} @break
                    @case(RegistrationStatus::Archived) {{ __('Your registration has been archived') }} @break
                    @default {{ __('Your registration has been removed') }}
                @endswitch
            </flux:heading>
            <flux:text>{{ __('Registration :reference', ['reference' => $registration->reference]) }}</flux:text>
        </div>

        @if ($registration->moderation_reason)
            <flux:callout icon="exclamation-triangle" color="red" :heading="__('Reason')" :text="$registration->moderation_reason" />
        @endif

        @if ($registration->appealed_at)
            <flux:callout icon="clock" color="amber" :heading="__('Appeal received')" data-test="appeal-received"
                :text="__('We got your appeal on :date. Our registration team will review it and email you.', ['date' => $registration->appealed_at->timezone('Asia/Karachi')->format('j M, g:i a')])" />
        @else
            <form wire:submit="appeal" class="flex flex-col gap-4">
                <flux:textarea wire:model="message" :label="__('Think this is a mistake? Appeal')" rows="5"
                    :placeholder="__('Tell us what happened. If you paid, include the date, amount and the account you paid from.')" />
                <flux:button type="submit" variant="primary">{{ __('Send appeal') }}</flux:button>
            </form>
        @endif
    @endif

    <form method="POST" action="{{ route('logout') }}" class="self-center">
        @csrf
        <flux:button variant="ghost" type="submit" class="text-sm">{{ __('Log out') }}</flux:button>
    </form>
</div>
