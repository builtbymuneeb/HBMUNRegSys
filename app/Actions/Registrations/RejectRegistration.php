<?php

namespace App\Actions\Registrations;

use App\Enums\RegistrationStatus;
use App\Models\AuditLog;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\RegistrationRejected;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class RejectRegistration
{
    /**
     * Reject the registration's payment and tell the contact why.
     */
    public function handle(Registration $registration, User $reviewer, string $reason): void
    {
        DB::transaction(function () use ($registration, $reviewer, $reason) {
            $registration->update([
                'status' => RegistrationStatus::Rejected,
                'rejection_reason' => $reason,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            AuditLog::record('rejected', $registration, $reviewer, ['reason' => $reason]);
        });

        Notification::route('mail', $registration->contact_email)
            ->notify(new RegistrationRejected($registration));
    }
}
