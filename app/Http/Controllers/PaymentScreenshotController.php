<?php

namespace App\Http\Controllers;

use App\Models\PaymentScreenshot;
use App\Models\Registration;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentScreenshotController extends Controller
{
    /**
     * Stream one of a registration's private payment screenshots to staff.
     */
    public function __invoke(Registration $registration, PaymentScreenshot $paymentScreenshot): StreamedResponse
    {
        Gate::authorize('view', $registration);

        return Storage::disk('local')->response($paymentScreenshot->path);
    }
}
