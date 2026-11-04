<?php

namespace App\Actions\Ambassadors;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CampusAmbassador;
use App\Models\User;
use App\Notifications\AmbassadorPortalInvite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Gives a campus ambassador a login for their portal and emails them about it.
 * Someone who already has an account with the same email (say a delegate) keeps
 * it and their role; the portal is simply added to it. Calling it again resends the email.
 */
class GivePortalAccess
{
    /**
     * @throws ValidationException when the ambassador can't be given a login.
     */
    public function handle(CampusAmbassador $ambassador, User $by): User
    {
        $email = Str::lower(trim((string) $ambassador->email));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['portal' => __(':name needs a valid email address first.', ['name' => $ambassador->name])]);
        }

        $user = DB::transaction(function () use ($ambassador, $email, $by) {
            $user = $ambassador->user ?? User::where('email', $email)->first();

            if ($user === null) {
                $user = new User(['name' => $ambassador->name, 'email' => $email, 'password' => Str::password(32)]);
                $user->forceFill(['role' => UserRole::Ambassador, 'email_verified_at' => now(), 'password_set_at' => null])->save();
            } elseif (CampusAmbassador::where('user_id', $user->id)->whereKeyNot($ambassador->id)->exists()) {
                throw ValidationException::withMessages(['portal' => __('The account :email already belongs to another ambassador.', ['email' => $email])]);
            } elseif ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $ambassador->forceFill(['user_id' => $user->id, 'portal_invited_at' => now()])->saveQuietly();
            AuditLog::record('ambassador_portal_invited', $ambassador, $by, ['code' => $ambassador->code, 'user_id' => $user->id]);

            return $user;
        });

        $token = $user->password_set_at === null ? Password::broker()->createToken($user) : null;
        $user->notify(new AmbassadorPortalInvite($token));

        return $user;
    }
}
