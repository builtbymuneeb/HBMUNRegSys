<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        @php
            $loginErrors = $errors->all();
            $triedEmail = (string) old('email');
            // An account that exists but never had a password set can't log in yet: say so plainly.
            $noPasswordYet = $loginErrors !== [] && $triedEmail !== ''
                && \App\Models\User::where('email', $triedEmail)->whereNull('password_set_at')->exists();
        @endphp

        @if ($noPasswordYet)
            <flux:callout variant="warning" icon="key" data-test="no-password-yet">
                <flux:callout.heading>{{ __('This account has no password yet') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('Use the "Set your password" email we sent to :email (the newest one), or get a fresh link below.', ['email' => $triedEmail]) }}
                </flux:callout.text>
                <x-slot name="actions">
                    <flux:button size="sm" :href="route('password.request')">{{ __('Email me a password link') }}</flux:button>
                </x-slot>
            </flux:callout>
        @endif

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Not registered yet?') }}</span>
            <flux:link :href="route('register')">{{ __('Register for the conference') }}</flux:link>
        </div>

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('On the organising team?') }}</span>
            <flux:link :href="route('staff.join')" wire:navigate>{{ __('Request staff access') }}</flux:link>
        </div>

        @if ($loginErrors !== [])
            <x-help-popup
                :problem="$noPasswordYet ? 'I can\'t log in, my account has no password yet' : 'I can\'t log in'"
                :error="implode(' ', $loginErrors)"
                :details="['Email' => $triedEmail]"
            />
        @endif
    </div>
</x-layouts::auth>
