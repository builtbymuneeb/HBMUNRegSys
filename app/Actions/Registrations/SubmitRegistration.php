<?php

namespace App\Actions\Registrations;

use App\Enums\MunExperience;
use App\Enums\RegistrationStatus;
use App\Enums\RegistrationType;
use App\Models\AuditLog;
use App\Models\CampusAmbassador;
use App\Models\Registration;
use App\Notifications\RegistrationReceived;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SubmitRegistration
{
    /**
     * Store a new registration with its delegates and payment screenshots.
     *
     * @param  array{organisation_name?: string|null, city: string, name: string, cnic: string, phone: string, email: string, referral_code?: string|null}  $contact
     * @param  list<array{name: string, cnic: string, email: string, phone: string, first_committee_id: int, second_committee_id: int, country_preference?: string|null, personality_preference?: string|null, mun_experience?: MunExperience|null, is_homebridge?: bool}>  $delegates
     * @param  non-empty-list<UploadedFile>  $paymentScreenshots
     */
    public function handle(RegistrationType $type, array $contact, array $delegates, array $paymentScreenshots): Registration
    {
        $screenshotPaths = array_map(fn (UploadedFile $file) => $file->store('payment-screenshots', 'local'), $paymentScreenshots);

        $registration = DB::transaction(function () use ($type, $contact, $delegates, $screenshotPaths) {
            $registration = Registration::create([
                'type' => $type,
                'status' => RegistrationStatus::Pending,
                'organisation_name' => $contact['organisation_name'] ?? null,
                'city' => $contact['city'],
                'contact_name' => $contact['name'],
                'contact_cnic' => $contact['cnic'],
                'contact_phone' => $contact['phone'],
                'contact_email' => $contact['email'],
                'referral_code' => $contact['referral_code'] ?? null,
                'campus_ambassador_id' => CampusAmbassador::forCode($contact['referral_code'] ?? null)?->id,
                'amount_due' => $type->amountDueFor(count($delegates), count(array_filter(array_column($delegates, 'is_homebridge')))),
                'payment_screenshot_path' => $screenshotPaths[0],
            ]);

            $registration->delegates()->createMany($delegates);
            $registration->paymentScreenshots()->createMany(array_map(fn (string $path) => ['path' => $path], $screenshotPaths));

            AuditLog::record('submitted', $registration);

            return $registration;
        });

        Notification::route('mail', $registration->contact_email)
            ->notify(new RegistrationReceived($registration));

        return $registration;
    }
}
