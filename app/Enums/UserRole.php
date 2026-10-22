<?php

namespace App\Enums;

enum UserRole: string
{
    case Delegate = 'delegate';
    case Applicant = 'applicant';
    case Registrations = 'registrations';
    case Allocations = 'allocations';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Delegate => 'Delegate',
            self::Applicant => 'Awaiting approval',
            self::Registrations => 'Registrations team',
            self::Allocations => 'Allocations team',
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
            self::Registrations => 'Checks payment screenshots and verifies or rejects registrations.',
            self::Allocations => 'Assigns committees and portfolios to verified delegates.',
            self::Admin => 'Verifies or rejects registrations and manages the team.',
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
        return [self::Registrations, self::Allocations, self::Admin];
    }

    /**
     * Roles someone may ask for when requesting staff access.
     *
     * @return list<self>
     */
    public static function requestableRoles(): array
    {
        return [self::Registrations, self::Allocations];
    }
}
