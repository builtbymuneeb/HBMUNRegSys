<?php

namespace App\Actions\Registrations;

use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Flag, block, archive, bin, restore and permanently delete registrations, and take appeals.
 * Every step is written to the audit log.
 */
class ModerateRegistration
{
    public function flag(Registration $registration, User $staff, string $note): void
    {
        $registration->update([
            'flagged_at' => now(),
            'flag_note' => $note,
            'flagged_by' => $staff->id,
        ]);

        AuditLog::record('flagged', $registration, $staff, ['note' => $note]);
    }

    public function unflag(Registration $registration, User $staff): void
    {
        $registration->update(['flagged_at' => null, 'flag_note' => null, 'flagged_by' => null]);

        AuditLog::record('unflagged', $registration, $staff);
    }

    /**
     * Block, archive or move to the bin. Keeps the status it had before the first of these,
     * so restoring puts it back exactly, even after being moved from blocked to the bin.
     */
    public function moderate(Registration $registration, User $staff, RegistrationStatus $to, string $reason): void
    {
        if (! $to->isModerated()) {
            throw new InvalidArgumentException("{$to->value} is not a moderation status.");
        }

        DB::transaction(function () use ($registration, $staff, $to, $reason) {
            $from = $registration->status;

            $registration->update([
                'status_before_moderation' => $from->isModerated()
                    ? $registration->status_before_moderation
                    : $from->value,
                'status' => $to,
                'moderation_reason' => $reason,
                'moderated_by' => $staff->id,
                'moderated_at' => now(),
                // A fresh decision answers any open appeal; it stays in the history.
                'appeal_message' => null,
                'appealed_at' => null,
            ]);

            AuditLog::record($to->value, $registration, $staff, ['reason' => $reason, 'from' => $from->value]);
        });
    }

    /**
     * Put a blocked, archived or binned registration back to the status it had before.
     */
    public function restore(Registration $registration, User $staff, ?string $note = null): void
    {
        if (! $registration->isModerated()) {
            return;
        }

        DB::transaction(function () use ($registration, $staff, $note) {
            $from = $registration->status;
            $to = RegistrationStatus::tryFrom((string) $registration->status_before_moderation) ?? RegistrationStatus::Pending;

            $registration->update([
                'status' => $to,
                'status_before_moderation' => null,
                'moderation_reason' => null,
                'moderated_by' => null,
                'moderated_at' => null,
                'appeal_message' => null,
                'appealed_at' => null,
            ]);

            AuditLog::record('restored', $registration, $staff, array_filter([
                'from' => $from->value,
                'to' => $to->value,
                'note' => $note,
            ]));
        });
    }

    /**
     * Someone on a blocked, archived or binned registration asks for it back.
     */
    public function appeal(Registration $registration, string $message, ?User $by = null): void
    {
        if (! $registration->isModerated()) {
            return;
        }

        $registration->update(['appeal_message' => $message, 'appealed_at' => now()]);

        AuditLog::record('appealed', $registration, $by, ['message' => $message]);
    }

    /**
     * Staff turn an appeal down; the registration stays as it is and they can appeal again.
     */
    public function dismissAppeal(Registration $registration, User $staff): void
    {
        $registration->update(['appeal_message' => null, 'appealed_at' => null]);

        AuditLog::record('appeal_dismissed', $registration, $staff);
    }

    /**
     * Delete a binned registration for good: its delegates, payment screenshots, and the
     * delegate logins that belong to nothing else. This can't be undone.
     */
    public function purge(Registration $registration, User $admin): void
    {
        if ($registration->status !== RegistrationStatus::Deleted) {
            throw new InvalidArgumentException('Only registrations in the bin can be deleted permanently.');
        }

        $registration->loadMissing('delegates', 'paymentScreenshots');

        $paths = $registration->paymentScreenshots->pluck('path')
            ->push($registration->payment_screenshot_path)
            ->filter()
            ->unique();

        $userIds = $registration->delegates->pluck('user_id')->filter()->unique();

        DB::transaction(function () use ($registration, $admin, $userIds) {
            AuditLog::record('purged', $registration, $admin, [
                'reference' => $registration->reference,
                'name' => $registration->displayName(),
                'contact_email' => $registration->contact_email,
            ]);

            $registration->delegates()->delete();
            $registration->delete();

            User::query()
                ->whereIn('id', $userIds)
                ->where('role', UserRole::Delegate)
                ->whereDoesntHave('delegate')
                ->delete();
        });

        Storage::disk('local')->delete($paths->all());
    }
}
