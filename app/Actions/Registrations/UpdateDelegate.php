<?php

namespace App\Actions\Registrations;

use App\Models\AuditLog;
use App\Models\Delegate;
use App\Models\User;
use App\Notifications\SetPassword;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

class UpdateDelegate
{
    /**
     * The delegate fields staff may correct after registration.
     */
    public const FIELDS = [
        'name',
        'email',
        'phone',
        'cnic',
        'first_committee_id',
        'second_committee_id',
        'first_preference',
        'second_preference',
    ];

    /**
     * Correct a delegate's details. When the email changes on a delegate who already has an
     * account, the account moves to the new address, and if they have not set a password yet
     * a fresh set-password link is sent there. Returns whether that email was sent.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Delegate $delegate, User $editor, array $data): bool
    {
        $data = Arr::only($data, self::FIELDS);
        $registration = $delegate->registration;
        $oldEmail = $delegate->email;

        $user = DB::transaction(function () use ($delegate, $editor, $data, $registration, $oldEmail) {
            $delegate->fill($data);
            $changes = array_keys($delegate->getDirty());

            if ($changes === []) {
                return null;
            }

            $emailChanged = $delegate->isDirty('email');

            // The per-committee preferences now hold everything, so the old shared ones are retired.
            if (array_key_exists('first_preference', $data) || array_key_exists('second_preference', $data)) {
                $delegate->forceFill(['country_preference' => null, 'personality_preference' => null]);
            }

            $delegate->save();

            if ($emailChanged && $registration->contact_email === $oldEmail) {
                $registration->update(['contact_email' => $delegate->email]);
            }

            $user = $delegate->user;

            if ($user && ($emailChanged || $delegate->wasChanged('name'))) {
                $user->forceFill([
                    'email' => $delegate->email,
                    'name' => $delegate->name,
                ])->save();
            }

            $summary = collect($changes)
                ->map(fn (string $field) => $field === 'email'
                    ? __('email :old → :new', ['old' => $oldEmail, 'new' => $delegate->email])
                    : str($field)->replace(['_id', '_'], ['', ' ']))
                ->implode(', ');

            AuditLog::record('delegate_updated', $registration, $editor, [
                'delegate' => $delegate->name,
                'changed' => $changes,
                'reason' => "{$delegate->name}: {$summary}",
            ]);

            return $emailChanged ? $user : null;
        });

        if ($user && ! $user->password_set_at) {
            $this->sendSetPasswordLink($user);

            return true;
        }

        return false;
    }

    /**
     * Email the delegate a fresh link to set their password.
     */
    public function sendSetPasswordLink(User $user): void
    {
        $user->notify(new SetPassword(Password::broker()->createToken($user)));
    }
}
