<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentScreenshotController extends Controller
{
    /**
     * Stream a registration's private payment screenshot to staff.
     */
    public function __invoke(Registration $registration): StreamedResponse
    {
        Gate::authorize('view', $registration);

        return Storage::disk('local')->response($registration->payment_screenshot_path);
    }
}
