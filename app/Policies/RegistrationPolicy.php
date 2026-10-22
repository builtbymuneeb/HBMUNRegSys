<?php

namespace App\Policies;

use App\Models\Registration;
use App\Models\User;

class RegistrationPolicy
{
    /**
     * Determine whether the user can view the list of registrations.
     */
    public function viewAny(User $user): bool
    {
        return $user->canViewRegistrations();
    }

    /**
     * Determine whether the user can view a registration and its payment screenshot.
     */
    public function view(User $user, Registration $registration): bool
    {
        return $user->canViewRegistrations();
    }

    /**
     * Determine whether the user can verify or reject a registration's payment.
     */
    public function review(User $user, Registration $registration): bool
    {
        return $user->canReviewRegistrations();
    }
}
