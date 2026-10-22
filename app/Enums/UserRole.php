<?php

namespace App\Enums;

enum UserRole: string
{
    case Delegate = 'delegate';
    case Registrations = 'registrations';
    case Allocations = 'allocations';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Delegate => 'Delegate',
            self::Registrations => 'Registrations team',
            self::Allocations => 'Allocations team',
            self::Admin => 'Admin',
        };
    }

    public function isStaff(): bool
    {
        return $this !== self::Delegate;
    }
}
