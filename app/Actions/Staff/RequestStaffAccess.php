<?php

namespace App\Actions\Staff;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\StaffAccessRequested;
use Illuminate\Support\Facades\Notification;

class RequestStaffAccess
{
    /**
     * Create an account that waits for an admin to approve the requested role.
     */
    public function handle(string $name, string $email, string $password, UserRole $requestedRole): User
    {
        $applicant = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $applicant->forceFill([
            'role' => UserRole::Applicant,
            'requested_role' => $requestedRole,
            'email_verified_at' => now(),
        ])->save();

        AuditLog::record('staff_access_requested', $applicant, $applicant, ['role' => $requestedRole->value]);

        Notification::send(
            User::where('role', UserRole::Admin)->get(),
            new StaffAccessRequested($applicant),
        );

        return $applicant;
    }
}
