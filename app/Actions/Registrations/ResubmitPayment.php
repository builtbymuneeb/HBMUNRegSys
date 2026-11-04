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
     * Replace a rejected registration's payment screenshots and send it back for review.
     * The old files stay on disk; their paths are kept in the audit log.
     *
     * @param  non-empty-list<UploadedFile>  $paymentScreenshots
     */
    public function handle(Registration $registration, array $paymentScreenshots): void
    {
        $previousPaths = $registration->paymentScreenshots()->pluck('path')->all();
        $newPaths = array_map(fn (UploadedFile $file) => $file->store('payment-screenshots', 'local'), $paymentScreenshots);

        DB::transaction(function () use ($registration, $previousPaths, $newPaths) {
            $registration->update([
                'status' => RegistrationStatus::Pending,
                'payment_screenshot_path' => $newPaths[0],
                'rejection_reason' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);

            $registration->paymentScreenshots()->delete();
            $registration->paymentScreenshots()->createMany(array_map(fn (string $path) => ['path' => $path], $newPaths));

            AuditLog::record('payment_resubmitted', $registration, properties: [
                'previous_screenshots' => $previousPaths,
            ]);
        });

        $registration->unsetRelation('paymentScreenshots');
    }
}
