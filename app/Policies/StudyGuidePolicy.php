<?php

namespace App\Policies;

use App\Enums\RegistrationStatus;
use App\Models\StudyGuide;
use App\Models\User;

class StudyGuidePolicy
{
    /**
     * Staff can open any guide. A verified delegate can open guides for every
     * committee, plus their own committee's once their allocation has been sent.
     */
    public function view(User $user, StudyGuide $guide): bool
    {
        if ($user->canManageConference()) {
            return true;
        }

        $delegate = $user->delegate;

        if (! $delegate || $delegate->registration->status !== RegistrationStatus::Verified) {
            return false;
        }

        return $guide->committee_id === null
            || ($delegate->hasPublishedAllocation() && $guide->committee_id === $delegate->allocated_committee_id);
    }
}
