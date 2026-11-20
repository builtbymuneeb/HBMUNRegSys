<?php

namespace App\Actions\Registrations;

use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Delegate;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\DelegateAccessAdded;
use App\Notifications\RegistrationVerified;
use App\Notifications\SetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class VerifyRegistration
{
    /**
     * Mark the registration's payment as verified, create a delegate account
     * for each delegate and email them a link to set their password.
     * $amountPaid is what was actually received; it defaults to the full fee.
     */
    public function handle(Registration $registration, User $reviewer, ?int $amountPaid = null): void
    {
        $amountPaid ??= $registration->amount_due;

        /** @var array{new: list<User>, existing: list<User>} $accounts */
        $accounts = DB::transaction(function () use ($registration, $reviewer, $amountPaid) {
            $registration->update([
                'status' => RegistrationStatus::Verified,
                'amount_paid' => $amountPaid,
                'rejection_reason' => null,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $accounts = ['new' => [], 'existing' => []];

            foreach ($registration->delegates as $delegate) {
                [$user, $isNew] = $this->createAccountFor($delegate);
                $accounts[$isNew ? 'new' : 'existing'][] = $user;
            }

            AuditLog::record('verified', $registration, $reviewer, [
                'reason' => 'PKR '.number_format($amountPaid).' received'
                    .($amountPaid !== $registration->amount_due ? ' (full fee PKR '.number_format($registration->amount_due).')' : ''),
                'amount_paid' => $amountPaid,
            ]);

            return $accounts;
        });

        foreach ($accounts['new'] as $user) {
            $user->notify(new SetPassword(Password::broker()->createToken($user)));
        }

        // People who already had an account (e.g. a campus ambassador) keep their password,
        // so they get a "log in as usual" email instead of a set-password link.
        foreach ($accounts['existing'] as $user) {
            $user->notify(new DelegateAccessAdded($registration));
        }

        if (! $registration->delegates->contains('email', $registration->contact_email)) {
            Notification::route('mail', $registration->contact_email)
                ->notify(new RegistrationVerified($registration));
        }
    }

    /**
     * Link the delegate to a user account.
     *
     * @return array{0: User, 1: bool} the user, and whether it needs a set-password link (new, or never set a password)
     */
    private function createAccountFor(Delegate $delegate): array
    {
        $user = User::firstOrNew(['email' => $delegate->email]);
        $isNew = ! $user->exists;

        if ($isNew) {
            $user->forceFill([
                'name' => $delegate->name,
                'password' => Str::password(32),
                'password_set_at' => null,
                'email_verified_at' => now(),
            ])->save();
        } elseif ($user->role === UserRole::Ambassador) {
            // A campus ambassador who registers as a delegate becomes a delegate, so they
            // get the delegate dashboard. Their ambassador portal stays linked to the account.
            $user->forceFill(['role' => UserRole::Delegate])->save();
        }

        $delegate->user()->associate($user)->save();

        // An existing account that never set a password (e.g. an ambassador who never opened
        // their portal email) can't "log in as usual", so it gets a set-password link too.
        return [$user, $isNew || $user->password_set_at === null];
    }
}
