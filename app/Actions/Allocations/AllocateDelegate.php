<?php

namespace App\Actions\Allocations;

use App\Enums\RegistrationStatus;
use App\Models\AuditLog;
use App\Models\Committee;
use App\Models\Delegate;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AllocateDelegate
{
    /**
     * Give a verified delegate a committee and portfolio, or clear their allocation.
     *
     * Changing an allocation that was already sent marks it unsent again, so the
     * delegate only ever sees an allocation the team has deliberately sent.
     */
    public function handle(Delegate $delegate, ?Committee $committee, ?string $portfolio, User $staff): void
    {
        if ($delegate->registration->status !== RegistrationStatus::Verified) {
            throw ValidationException::withMessages([
                'committeeId' => __('Only delegates with a verified payment can be allocated.'),
            ]);
        }

        $portfolio = $committee ? trim((string) $portfolio) : null;

        if ($committee && $this->portfolioIsTaken($delegate, $committee, $portfolio)) {
            throw ValidationException::withMessages([
                'portfolio' => __(':portfolio is already allocated in :committee.', ['portfolio' => $portfolio, 'committee' => $committee->code]),
            ]);
        }

        $changed = $delegate->allocated_committee_id !== $committee?->id
            || $delegate->allocated_portfolio !== $portfolio;

        if (! $changed) {
            return;
        }

        $delegate->forceFill([
            'allocated_committee_id' => $committee?->id,
            'allocated_portfolio' => $portfolio,
            'allocated_by' => $committee ? $staff->id : null,
            'allocated_at' => $committee ? now() : null,
            'allocation_published_at' => null,
        ])->save();

        AuditLog::record($committee ? 'allocated' : 'allocation_cleared', $delegate->registration, $staff, [
            'delegate' => $delegate->name,
            'committee' => $committee?->code,
            'portfolio' => $portfolio,
        ]);
    }

    private function portfolioIsTaken(Delegate $delegate, Committee $committee, string $portfolio): bool
    {
        return Delegate::query()
            ->where('allocated_committee_id', $committee->id)
            ->whereKeyNot($delegate->id)
            ->whereRaw('lower(allocated_portfolio) = ?', [mb_strtolower($portfolio)])
            ->exists();
    }
}
