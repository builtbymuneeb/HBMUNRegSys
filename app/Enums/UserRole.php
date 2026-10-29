<?php

namespace App\Enums;

enum UserRole: string
{
    case Delegate = 'delegate';
    case Applicant = 'applicant';
    case Registrations = 'registrations';
    case Ambassadors = 'ambassadors';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Delegate => 'Delegate',
            self::Applicant => 'Awaiting approval',
            self::Registrations => 'Registrations team',
            self::Ambassadors => 'Campus ambassador management',
            self::Admin => 'Admin',
        };
    }

    /**
     * What a member with this role can do, shown when choosing a role.
     */
    public function description(): string
    {
        return match ($this) {
            self::Delegate => 'Their own delegate dashboard.',
            self::Applicant => 'Can log in, but has no access until an admin approves them.',
            self::Registrations => 'Verifies or rejects payments, allocates committees and portfolios, and runs committee pages, study guides, announcements and campus ambassadors.',
            self::Ambassadors => 'Adds and imports campus ambassadors and tracks the registrations their codes bring in. No access to payments, registration details or allocations.',
            self::Admin => 'Everything the other teams do, plus managing the team.',
        };
    }

    public function isStaff(): bool
    {
        return in_array($this, self::staffRoles(), true);
    }

    /**
     * Roles a team member can hold.
     *
     * @return list<self>
     */
    public static function staffRoles(): array
    {
        return [self::Registrations, self::Ambassadors, self::Admin];
    }
}
