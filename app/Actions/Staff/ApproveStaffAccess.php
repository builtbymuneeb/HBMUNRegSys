<?php

namespace App\Actions\Staff;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\StaffAccessApproved;
use InvalidArgumentException;

class ApproveStaffAccess
{
    /**
     * Give an applicant a staff role (the one they asked for, unless the admin picks another).
     */
    public function handle(User $applicant, User $admin, ?UserRole $role = null): void
    {
        if (! $applicant->isAwaitingApproval()) {
            throw new InvalidArgumentException('Only pending staff requests can be approved.');
        }

        $role ??= $applicant->requested_role ?? UserRole::Registrations;

        $applicant->forceFill(['role' => $role, 'requested_role' => null])->save();

        AuditLog::record('staff_access_approved', $applicant, $admin, ['role' => $role->value]);

        $applicant->notify(new StaffAccessApproved);
    }
}
