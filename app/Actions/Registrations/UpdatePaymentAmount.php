<?php

namespace App\Actions\Registrations;

use App\Models\AuditLog;
use App\Models\Registration;
use App\Models\User;

class UpdatePaymentAmount
{
    /**
     * Correct the amount recorded as received for a verified registration.
     */
    public function handle(Registration $registration, User $editor, int $amountPaid): void
    {
        $previous = $registration->amountReceived();

        if ($previous === $amountPaid) {
            return;
        }

        $registration->update(['amount_paid' => $amountPaid]);

        AuditLog::record('payment_amount_changed', $registration, $editor, [
            'reason' => 'PKR '.number_format($previous).' → PKR '.number_format($amountPaid),
            'from' => $previous,
            'to' => $amountPaid,
        ]);
    }
}
