@php
    // Check the link as soon as the page opens, so nobody types a new password into a link that
    // has expired or been replaced by a newer email (each new link cancels the older ones).
    $resetEmail = (string) request('email');
    $resetBroker = \Illuminate\Support\Facades\Password::broker();
    $resetUser = $resetEmail !== '' ? $resetBroker->getUser(['email' => $resetEmail]) : null;
    $linkIsValid = $resetUser && $resetBroker->tokenExists($resetUser, (string) request()->route('token'));

    // Someone already logged in (often a campus ambassador with a separate delegate account)
    // is shown which account they are in before anything else, instead of being bounced away.
    $signedInUser = auth()->user();
    $sameAccount = $signedInUser && strcasecmp($signedInUser->email, $resetEmail) === 0;
    $resetErrors = $errors->all();

    // The link no longer works because it was just used: the password is already set. (Happens
    // when the form is sent twice; the first one works and the second finds the link used up.)
    // A successful reset deletes the account's link; asking for a new one creates another, so this
    // only shows when no link is waiting.
    $justSet = ! $linkIsValid
        && $resetUser?->password_set_at?->gt(now()->subMinutes(15))
        && \Illuminate\Support\Facades\DB::table(config('auth.passwords.users.table'))->where('email', $resetUser->email)->doesntExist();
@endphp

<x-layouts::auth :title="__('Reset password')">
    <div class="flex flex-col gap-6">
        @if ($signedInUser)
            <x-auth-header
                :title="$sameAccount ? __('You are already logged in') : __('You are logged in to a different account')"
                :description="__('Check the accounts below before you continue.')"
            />

            <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" data-test="signed-in-notice">
                <div>
                    <flux:text class="text-xs uppercase">{{ __('You are logged in as') }}</flux:text>
                    <flux:heading>{{ $signedInUser->name }}</flux:heading>
                    <flux:text>{{ $signedInUser->email }}</flux:text>
                </div>
                <flux:separator />
                <div>
                    <flux:text class="text-xs uppercase">{{ __('This password link is for') }}</flux:text>
                    <flux:heading>{{ $resetEmail !== '' ? $resetEmail : __('(no email in the link)') }}</flux:heading>
                </div>
            </div>

            <flux:text class="text-center">
                @if ($sameAccount)
                    {{ __('If you want to set a new password for this account, we will log you out first and then show you the form.') }}
                @else
                    {{ __('If you wish to set the password for :email, we will log you out of :current first and then show you the form.', ['email' => $resetEmail, 'current' => $signedInUser->email]) }}
                @endif
            </flux:text>

            <form method="POST" action="{{ route('logout') }}" class="flex flex-col gap-3">
                @csrf
                <input type="hidden" name="continue" value="{{ '/'.request()->path().(request()->getQueryString() ? '?'.request()->getQueryString() : '') }}">

                <flux:button variant="primary" type="submit" class="w-full" data-test="logout-and-continue-button">
                    {{ $sameAccount ? __('Log out and set a new password') : __('Log out and set password for :email', ['email' => $resetEmail]) }}
                </flux:button>

                <flux:button :href="route('dashboard')" variant="ghost" class="w-full" data-test="stay-logged-in-button">
                    {{ __('Stay logged in as :email', ['email' => $signedInUser->email]) }}
                </flux:button>
            </form>

            <x-help-popup
                mode="inline"
                :problem="$sameAccount ? 'I opened a password link while already logged in' : 'I opened a password link for a different account than the one I am logged in to'"
                :details="['Link is for' => $resetEmail]"
            />
        @elseif ($linkIsValid)
            <x-auth-header :title="__('Reset password')" :description="__('Please enter your new password below')" />

            <!-- Session Status -->
            <x-auth-session-status class="text-center" :status="session('status')" />

            <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-6">
                @csrf
                <!-- Token -->
                <input type="hidden" name="token" value="{{ request()->route('token') }}">

                <!-- Email Address -->
                <flux:input
                    name="email"
                    value="{{ request('email') }}"
                    :label="__('Email')"
                    type="email"
                    required
                    autocomplete="email"
                />

                <!-- Password -->
                <flux:input
                    name="password"
                    :label="__('Password')"
                    :description="__('At least 8 characters, with upper and lower case letters and a number.')"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="__('Password')"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                    viewable
                />

                <!-- Confirm Password -->
                <flux:input
                    name="password_confirmation"
                    :label="__('Confirm password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="__('Confirm password')"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                    viewable
                />

                <div class="flex items-center justify-end">
                    <flux:button type="submit" variant="primary" class="w-full" data-test="reset-password-button">
                        {{ __('Reset password') }}
                    </flux:button>
                </div>
            </form>

            @if ($resetErrors !== [])
                <x-help-popup problem="I can't set my password" :error="implode(' ', $resetErrors)" :details="['Email' => $resetEmail]" />
            @endif
        @elseif ($justSet)
            <x-auth-header :title="__('Your password is set')" :description="__('This link has already been used, so you are all done.')" />

            <flux:callout variant="success" icon="check-circle" data-test="password-already-set">
                <flux:callout.text>
                    {{ __('The password for :email was saved :time. Log in with that email and the password you just chose.', ['email' => $resetEmail, 'time' => $resetUser->password_set_at->gt(now()->subMinute()) ? __('just now') : $resetUser->password_set_at->diffForHumans()]) }}
                </flux:callout.text>
            </flux:callout>

            <flux:button :href="route('login')" variant="primary" class="w-full" data-test="go-to-login-button">
                {{ __('Log in') }}
            </flux:button>

            <x-help-popup mode="inline" problem="I just set my password but still can't log in" :details="['Email' => $resetEmail]" />
        @else
            <x-auth-header :title="__('This link has expired')" :description="__('No problem, we can send you a new one.')" />

            @if (session('status'))
                <flux:callout variant="success" icon="check-circle" data-test="new-link-sent">
                    <flux:callout.text>{{ __('A new link is on its way. Open the newest email from us (check spam too) and use that link.') }}</flux:callout.text>
                </flux:callout>
            @else
                <flux:text class="text-center">
                    {{ __('Password links work for :days days, and only the newest email we send works. Older emails stop working as soon as a new one is sent.', ['days' => intdiv(config('auth.passwords.users.expire'), 1440)]) }}
                </flux:text>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
                @csrf

                <flux:input
                    name="email"
                    value="{{ old('email', request('email')) }}"
                    :label="__('Email address')"
                    type="email"
                    required
                    autocomplete="email"
                />

                <flux:button variant="primary" type="submit" class="w-full" data-test="email-new-link-button">
                    {{ __('Email me a new link') }}
                </flux:button>
            </form>

            <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-400">
                <span>{{ __('Already set your password?') }}</span>
                <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
            </div>

            @if (session('status'))
                <x-help-popup mode="inline" problem="My password link expired. I asked for a new one but need help" :details="['Email' => $resetEmail]" />
            @else
                <x-help-popup
                    problem="My password link says it has expired"
                    :error="$resetErrors !== [] ? implode(' ', $resetErrors) : 'This link has expired (it is older than 7 days, or a newer email replaced it)'"
                    :details="['Email' => $resetEmail]"
                />
            @endif
        @endif
    </div>
</x-layouts::auth>
