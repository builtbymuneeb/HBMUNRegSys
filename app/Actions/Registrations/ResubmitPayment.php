<?php

namespace App\Actions\Registrations;

use App\Enums\RegistrationStatus;
use App\Models\AuditLog;
use App\Models\Registration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ResubmitPayment
{
    /**
     * Replace a rejected registration's payment screenshot and send it back for review.
     */
    public function handle(Registration $registration, UploadedFile $paymentScreenshot): void
    {
        $previousPath = $registration->payment_screenshot_path;
        $newPath = $paymentScreenshot->store('payment-screenshots', 'local');

        DB::transaction(function () use ($registration, $previousPath, $newPath) {
            $registration->update([
                'status' => RegistrationStatus::Pending,
                'payment_screenshot_path' => $newPath,
                'rejection_reason' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);

            AuditLog::record('payment_resubmitted', $registration, properties: [
                'previous_screenshot' => $previousPath,
            ]);
        });
    }
}
