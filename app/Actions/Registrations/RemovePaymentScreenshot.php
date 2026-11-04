<?php

namespace App\Actions\Registrations;

use App\Models\AuditLog;
use App\Models\PaymentScreenshot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RemovePaymentScreenshot
{
    /**
     * Take a screenshot off a registration, e.g. one added by mistake. The file stays on
     * disk and its path is kept in the audit log, so nothing is lost for good.
     */
    public function handle(PaymentScreenshot $screenshot, User $staff): void
    {
        $registration = $screenshot->registration;

        DB::transaction(function () use ($screenshot, $registration, $staff) {
            $screenshot->delete();

            $first = $registration->paymentScreenshots()->first();

            if ($first && $first->path !== $registration->payment_screenshot_path) {
                $registration->update(['payment_screenshot_path' => $first->path]);
            }

            AuditLog::record('payment_screenshot_removed', $registration, $staff, ['path' => $screenshot->path]);
        });
    }
}
