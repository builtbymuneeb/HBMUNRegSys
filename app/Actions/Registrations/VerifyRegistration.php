<?php

namespace App\Actions\Registrations;

use App\Enums\RegistrationStatus;
use App\Models\AuditLog;
use App\Models\Delegate;
use App\Models\Registration;
use App\Models\User;
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

        /** @var list<User> $newUsers */
        $newUsers = DB::transaction(function () use ($registration, $reviewer, $amountPaid) {
            $registration->update([
                'status' => RegistrationStatus::Verified,
                'amount_paid' => $amountPaid,
                'rejection_reason' => null,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $newUsers = $registration->delegates
                ->map(fn (Delegate $delegate) => $this->createAccountFor($delegate))
                ->filter()
                ->values()
                ->all();

            AuditLog::record('verified', $registration, $reviewer, [
                'reason' => 'PKR '.number_format($amountPaid).' received'
                    .($amountPaid !== $registration->amount_due ? ' (full fee PKR '.number_format($registration->amount_due).')' : ''),
                'amount_paid' => $amountPaid,
            ]);

            return $newUsers;
        });

        foreach ($newUsers as $user) {
            $user->notify(new SetPassword(Password::broker()->createToken($user)));
        }

        if (! $registration->delegates->contains('email', $registration->contact_email)) {
            Notification::route('mail', $registration->contact_email)
                ->notify(new RegistrationVerified($registration));
        }
    }

    /**
     * Link the delegate to a user account, returning the user when it is new.
     */
    private function createAccountFor(Delegate $delegate): ?User
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
        }

        $delegate->user()->associate($user)->save();

        return $isNew ? $user : null;
    }
}
