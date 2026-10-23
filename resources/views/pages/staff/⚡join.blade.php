<?php

use App\Actions\Staff\RequestStaffAccess;
use App\Concerns\PasswordValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::auth')] #[Title('Request staff access')] class extends Component {
    use PasswordValidationRules;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $requested = false;

    /**
     * Create the applicant account and notify the admins.
     */
    public function submit(RequestStaffAccess $requestStaffAccess): void
    {
        $throttleKey = 'staff-join:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => __('Too many requests. Please try again in :minutes minutes.', ['minutes' => ceil(RateLimiter::availableIn($throttleKey) / 60)]),
            ]);
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
        ]);

        RateLimiter::hit($throttleKey, 3600);

        $requestStaffAccess->handle($validated['name'], $validated['email'], $validated['password'], UserRole::Registrations);

        $this->reset('password', 'password_confirmation');
        $this->requested = true;
    }
}; ?>

<div class="flex flex-col gap-6">
    @if ($requested)
        <x-auth-header :title="__('Request sent')" :description="__('An admin will review your request. You’ll get an email once you’re approved, then you can log in.')" />
        <flux:button :href="route('login')" variant="primary" class="w-full">{{ __('Go to login') }}</flux:button>
    @else
        <x-auth-header :title="__('Join the organising team')" :description="__('Request access to the registrations team. An admin approves every request.')" />

        <form wire:submit="submit" class="flex flex-col gap-6">
            <flux:input wire:model="name" :label="__('Full name')" type="text" required autofocus autocomplete="name" />
            <flux:input wire:model="email" :label="__('Email address')" type="email" required autocomplete="email" placeholder="email@example.com" />

            <flux:input wire:model="password" :label="__('Password')" :description="__('At least 8 characters, with upper and lower case letters and a number.')" type="password" required autocomplete="new-password" viewable />
            <flux:input wire:model="password_confirmation" :label="__('Confirm password')" type="password" required autocomplete="new-password" viewable />

            <flux:button type="submit" variant="primary" class="w-full" data-test="request-staff-access">{{ __('Request access') }}</flux:button>
        </form>

        <div class="space-x-1 text-center text-sm text-zinc-600 rtl:space-x-reverse dark:text-zinc-400">
            <span>{{ __('Already approved?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    @endif
</div>
