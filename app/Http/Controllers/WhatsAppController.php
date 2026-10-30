<?php

namespace App\Http\Controllers;

use App\Enums\RegistrationStatus;
use App\Models\CampusAmbassador;
use App\Models\Delegate;
use App\Models\Registration;
use App\Models\WhatsAppLog;
use App\Notifications\AllocationPublished;
use App\Notifications\AmbassadorMessage;
use App\Notifications\AmbassadorWelcome;
use App\Notifications\RegistrationReceived;
use App\Notifications\RegistrationRejected;
use App\Notifications\RegistrationVerified;
use App\Notifications\SetPassword;
use App\Support\WhatsApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Staff click "Send on WhatsApp" through here: we note who opened which message for
 * whom, then hand over to WhatsApp with the message typed in.
 */
class WhatsAppController extends Controller
{
    /**
     * Message the registration's contact person about its current status.
     */
    public function contact(Request $request, Registration $registration): RedirectResponse
    {
        Gate::authorize('view', $registration);

        $message = match ($registration->status) {
            RegistrationStatus::Pending => (new RegistrationReceived($registration))->toWhatsApp(),
            RegistrationStatus::Verified => (new RegistrationVerified($registration))->toWhatsApp(),
            RegistrationStatus::Rejected => (new RegistrationRejected($registration))->toWhatsApp(),
        };

        return $this->open($request, WhatsAppLog::CONTACT.'_'.$registration->status->value, $registration->contact_phone, $message, $registration);
    }

    /**
     * Tell a delegate their account is ready and the set-password email is on its way.
     */
    public function account(Request $request, Delegate $delegate): RedirectResponse
    {
        Gate::authorize('view', $delegate->registration);
        abort_unless($delegate->user, 404);

        $message = SetPassword::whatsAppMessage($delegate->name, $delegate->user->email);

        return $this->open($request, WhatsAppLog::ACCOUNT, $delegate->phone, $message, $delegate->registration, $delegate);
    }

    /**
     * Send a delegate their allocation, once it has been sent by email.
     */
    public function allocation(Request $request, Delegate $delegate): RedirectResponse
    {
        Gate::authorize('manage-conference');
        abort_unless($delegate->hasPublishedAllocation(), 404);

        $message = (new AllocationPublished($delegate))->toWhatsApp();

        return $this->open($request, WhatsAppLog::ALLOCATION, $delegate->phone, $message, $delegate->registration, $delegate);
    }

    /**
     * Welcome a campus ambassador with their code and registration link.
     */
    public function ambassadorWelcome(Request $request, CampusAmbassador $ambassador): RedirectResponse
    {
        Gate::authorize('manage-ambassadors');

        return $this->open($request, WhatsAppLog::AMBASSADOR_WELCOME, (string) $ambassador->phone, (new AmbassadorWelcome($ambassador))->toWhatsApp(), ambassador: $ambassador);
    }

    /**
     * Send a campus ambassador a message staff wrote ({name}, {code} and {link} are filled in).
     */
    public function ambassadorMessage(Request $request, CampusAmbassador $ambassador): RedirectResponse
    {
        Gate::authorize('manage-ambassadors');

        $text = $request->validate(['text' => ['required', 'string', 'max:2000']])['text'];

        return $this->open($request, WhatsAppLog::AMBASSADOR_MESSAGE, (string) $ambassador->phone, (new AmbassadorMessage($ambassador, '', $text))->toWhatsApp(), ambassador: $ambassador);
    }

    private function open(Request $request, string $kind, string $phone, string $message, ?Registration $registration = null, ?Delegate $delegate = null, ?CampusAmbassador $ambassador = null): RedirectResponse
    {
        $link = WhatsApp::link($phone, $message);

        abort_if($link === null, 404, __('That phone number can’t be opened in WhatsApp.'));

        WhatsAppLog::create([
            'kind' => $kind,
            'phone' => $phone,
            'registration_id' => $registration?->id,
            'delegate_id' => $delegate?->id,
            'campus_ambassador_id' => $ambassador?->id,
            'sent_by' => $request->user()->id,
        ]);

        return redirect()->away($link);
    }
}
