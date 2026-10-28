<?php

namespace App\Http\Controllers;

use App\Enums\RegistrationStatus;
use App\Models\Delegate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DelegateExportController extends Controller
{
    /**
     * Download every delegate (one row each) with their registration and allocation, as CSV.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        $status = RegistrationStatus::tryFrom((string) $request->query('status'));
        $filename = 'hbmun-delegates-'.($status->value ?? 'all').'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($status) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                throw new RuntimeException('Could not open the output stream for the CSV export.');
            }

            // Byte order mark so Excel opens the file as UTF-8.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Reference', 'Registration status', 'Type', 'Institution / delegation', 'City',
                'Contact name', 'Contact phone', 'Contact email', 'Referral code',
                'Delegate name', 'CNIC / B-Form', 'Email', 'Phone',
                '1st committee', '2nd committee', 'Country preference', 'Personality preference', 'Previous MUN experience',
                'Allocated committee', 'Allocated portfolio', 'Allocation sent',
            ]);

            Delegate::query()
                ->with(['registration', 'firstCommittee', 'secondCommittee', 'allocatedCommittee'])
                ->when($status, fn (Builder $query) => $query->whereHas('registration', fn (Builder $query) => $query->where('status', $status)))
                ->orderBy('registration_id')
                ->orderBy('id')
                ->chunk(200, function ($delegates) use ($out) {
                    foreach ($delegates as $delegate) {
                        $registration = $delegate->registration;

                        fputcsv($out, array_map($this->safeCell(...), [
                            $registration->reference,
                            $registration->status->label(),
                            $registration->type->label(),
                            $registration->organisation_name,
                            $registration->city,
                            $registration->contact_name,
                            $registration->contact_phone,
                            $registration->contact_email,
                            $registration->referral_code,
                            $delegate->name,
                            $delegate->cnic,
                            $delegate->email,
                            $delegate->phone,
                            $delegate->firstCommittee->code,
                            $delegate->secondCommittee->code,
                            $delegate->country_preference,
                            $delegate->personality_preference,
                            $delegate->mun_experience,
                            $delegate->allocatedCommittee?->code,
                            $delegate->allocated_portfolio,
                            $delegate->allocation_published_at?->timezone('Asia/Karachi')->format('Y-m-d H:i'),
                        ]));
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Stop spreadsheet apps from running values typed into the form as formulas.
     */
    private function safeCell(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
