<?php

namespace App\Actions\Staff;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\StaffInvitation;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class InviteStaffMember
{
    /**
     * Add a team member directly and email them a link to set their password.
     */
    public function handle(string $name, string $email, UserRole $role, User $admin): User
    {
        $member = new User(['name' => $name, 'email' => $email, 'password' => Str::password(32)]);
        $member->forceFill(['role' => $role, 'email_verified_at' => now()])->save();

        AuditLog::record('staff_invited', $member, $admin, ['role' => $role->value]);

        $member->notify(new StaffInvitation(Password::broker()->createToken($member)));

        return $member;
    }
}
