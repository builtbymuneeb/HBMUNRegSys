<?php

namespace App\Enums;

enum RegistrationType: string
{
    case InstitutionDelegation = 'institution_delegation';
    case PrivateDelegation = 'private_delegation';

    public function label(): string
    {
        return match ($this) {
            self::InstitutionDelegation => 'Institution delegation',
            self::PrivateDelegation => 'Private delegation',
        };
    }

    public function minimumDelegates(): int
    {
        return config('conference.delegation.min_delegates');
    }

    public function maximumDelegates(): int
    {
        return config('conference.delegation.max_delegates');
    }

    /**
     * The fee in PKR for a delegation of this type with the given number of delegates.
     */
    public function amountDueFor(int $delegateCount): int
    {
        return $delegateCount * config('conference.fees.per_delegate') + config('conference.fees.per_delegation');
    }
}
