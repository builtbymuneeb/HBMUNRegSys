<?php

namespace App\Actions\Ambassadors;

use App\Enums\AmbassadorType;
use App\Models\AuditLog;
use App\Models\CampusAmbassador;
use App\Models\User;
use App\Support\ReferralCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns the rows of a campus ambassador sign-up sheet (e.g. a Google Forms
 * export) into ambassador profiles. Columns are found by their headings, so
 * the sheet doesn't need a fixed layout; rows without a code get the next
 * free HB## code. Rows with doubtful contact details, or a note in a
 * "note"/"flag"/"status" column, are flagged for staff to check.
 */
class ImportAmbassadors
{
    /**
     * Heading patterns for each field, checked in this order.
     *
     * @var array<string, string>
     */
    private const COLUMNS = [
        'code' => '/\b(referral|code)\b/',
        'email' => '/e-?mail/',
        'phone' => '/phone|whats\s*app|mobile|contact\s*(no|number|#)/',
        'institution' => '/institut|college|school|universit|campus/',
        'city' => '/\bcity\b/',
        'name' => '/name/',
        'flag' => '/note|flag|status|reason|remark|check/',
    ];

    /**
     * Work out what an import would do, without saving anything.
     *
     * @param  list<list<string>>  $rows
     * @return list<array{name: string, email: string|null, phone: string|null, institution: string|null, city: string|null, code: string, flag_reason: string|null, generated: bool, skip: string|null}>
     */
    public function preview(array $rows): array
    {
        $rows = array_values(array_filter($rows, fn (array $row) => implode('', $row) !== ''));
        $columns = $this->columns(array_shift($rows) ?? []);

        $existing = CampusAmbassador::all(['code', 'code_key', 'email', 'phone']);
        $takenKeys = $existing->pluck('code_key')->flip()->all();
        $takenEmails = $existing->pluck('email')->filter()->map(fn (string $email) => Str::lower($email))->flip()->all();
        $takenPhones = $existing->filter(fn (CampusAmbassador $ambassador) => CampusAmbassador::phoneKey($ambassador->phone))
            ->mapWithKeys(fn (CampusAmbassador $ambassador) => [CampusAmbassador::phoneKey($ambassador->phone) => $ambassador->code])
            ->all();
        $nextNumber = ($existing->map(fn (CampusAmbassador $ambassador) => ReferralCode::number($ambassador->code) ?? 0)->max() ?? 0) + 1;

        $preview = [];
        foreach ($rows as $row) {
            $value = fn (string $field) => isset($columns[$field]) ? (trim($row[$columns[$field]] ?? '') ?: null) : null;

            $entry = [
                'name' => Str::limit((string) $value('name'), 255, ''),
                'email' => ($email = $value('email')) ? Str::lower($email) : null,
                'phone' => $this->phone($value('phone')),
                'institution' => $value('institution'),
                'city' => $value('city'),
                'code' => (string) ReferralCode::clean($value('code')),
                'flag_reason' => $value('flag'),
                'generated' => false,
                'skip' => null,
            ];

            if ($entry['code'] !== '' && ReferralCode::number($entry['code']) !== null) {
                $nextNumber = max($nextNumber, ReferralCode::number($entry['code']) + 1);
            }

            $preview[] = $entry;
        }

        foreach ($preview as &$entry) {
            if ($entry['name'] === '') {
                $entry['skip'] = __('No name');
            } elseif ($entry['email'] && isset($takenEmails[$entry['email']])) {
                $entry['skip'] = __('Email already added');
            } elseif ($entry['code'] !== '' && isset($takenKeys[ReferralCode::key($entry['code'])])) {
                $entry['skip'] = __('Code already used');
            }

            if ($entry['skip'] !== null) {
                continue;
            }

            if ($entry['code'] === '') {
                do {
                    $entry['code'] = ReferralCode::format($nextNumber++);
                } while (isset($takenKeys[ReferralCode::key($entry['code'])]));
                $entry['generated'] = true;
            }

            $problems = CampusAmbassador::contactProblems($entry['email'], $entry['phone']);
            $phoneKey = CampusAmbassador::phoneKey($entry['phone']);
            if ($phoneKey !== null && isset($takenPhones[$phoneKey])) {
                $problems[] = __('Same phone as :code', ['code' => $takenPhones[$phoneKey]]);
            }
            $entry['flag_reason'] = implode('. ', array_filter([...$problems, $entry['flag_reason']])) ?: null;

            if ($phoneKey !== null) {
                $takenPhones[$phoneKey] ??= $entry['code'];
            }

            $takenKeys[ReferralCode::key($entry['code'])] = true;
            if ($entry['email']) {
                $takenEmails[$entry['email']] = true;
            }
        }

        return $preview;
    }

    /**
     * Create an ambassador for every row the preview didn't skip.
     *
     * @param  list<list<string>>  $rows
     */
    public function import(array $rows, User $by, AmbassadorType $type = AmbassadorType::Homebridge): int
    {
        $entries = array_filter($this->preview($rows), fn (array $entry) => $entry['skip'] === null);

        DB::transaction(function () use ($entries, $by, $type) {
            foreach ($entries as $entry) {
                $ambassador = CampusAmbassador::create([
                    ...collect($entry)->only(['code', 'name', 'email', 'phone', 'institution', 'city', 'flag_reason'])->all(),
                    'type' => $type,
                ]);

                AuditLog::record('ambassador_imported', $ambassador, $by, ['code' => $ambassador->code, 'flag_reason' => $ambassador->flag_reason]);
            }
        });

        return count($entries);
    }

    /**
     * Map each field to the index of the first heading that matches it.
     *
     * @param  list<string>  $headings
     * @return array<string, int>
     */
    private function columns(array $headings): array
    {
        $columns = [];
        foreach (self::COLUMNS as $field => $pattern) {
            foreach ($headings as $index => $heading) {
                if (! in_array($index, $columns, true) && preg_match($pattern, Str::lower($heading))) {
                    $columns[$field] = $index;

                    break;
                }
            }
        }

        if (! isset($columns['name'])) {
            throw ValidationException::withMessages([
                'sheet' => __('Couldn’t find a name column. The first row should hold headings such as “Full name”, “Email”, “Phone” and “Institution”.'),
            ]);
        }

        return $columns;
    }

    /**
     * Put back the leading zero spreadsheets drop from Pakistani numbers (3001234567 → 03001234567).
     */
    private function phone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        return preg_match('/^3\d{9}$/', $phone) ? '0'.$phone : $phone;
    }
}
