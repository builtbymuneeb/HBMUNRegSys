<?php

namespace App\Enums;

enum UserRole: string
{
    case Delegate = 'delegate';
    case Applicant = 'applicant';
    case Registrations = 'registrations';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Delegate => 'Delegate',
            self::Applicant => 'Awaiting approval',
            self::Registrations => 'Registrations team',
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
            self::Registrations => 'Verifies or rejects payments, allocates committees and portfolios, and runs committee pages, study guides and announcements.',
            self::Admin => 'Everything the registrations team does, plus managing the team.',
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
        return [self::Registrations, self::Admin];
    }
}
