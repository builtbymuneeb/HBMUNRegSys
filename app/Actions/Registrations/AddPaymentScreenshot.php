<?php

namespace App\Actions\Registrations;

use App\Models\AuditLog;
use App\Models\PaymentScreenshot;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class AddPaymentScreenshot
{
    /**
     * Attach another payment receipt to a registration on a staff member's behalf,
     * e.g. a second slip the delegation sent over WhatsApp.
     */
    public function handle(Registration $registration, UploadedFile $file, User $staff): PaymentScreenshot
    {
        $path = $file->store('payment-screenshots', 'local');

        return DB::transaction(function () use ($registration, $path, $staff) {
            $screenshot = $registration->paymentScreenshots()->create([
                'path' => $path,
                'uploaded_by' => $staff->id,
            ]);

            AuditLog::record('payment_screenshot_added', $registration, $staff, ['path' => $path]);

            return $screenshot;
        });
    }
}
