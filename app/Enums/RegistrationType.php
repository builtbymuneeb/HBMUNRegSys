<?php

namespace App\Enums;

enum RegistrationType: string
{
    case InstitutionDelegation = 'institution_delegation';
    case PrivateDelegation = 'private_delegation';
    case PrivateDelegate = 'private_delegate';

    public function label(): string
    {
        return match ($this) {
            self::InstitutionDelegation => 'Institution delegation',
            self::PrivateDelegation => 'Private delegation',
            self::PrivateDelegate => 'Individual Homebridge delegate',
        };
    }

    /**
     * Whether this type registers a single person rather than a team with a head.
     */
    public function isIndividual(): bool
    {
        return $this === self::PrivateDelegate;
    }

    public function minimumDelegates(): int
    {
        return $this->isIndividual() ? 1 : config('conference.delegation.min_delegates');
    }

    public function maximumDelegates(): int
    {
        return $this->isIndividual() ? 1 : config('conference.delegation.max_delegates');
    }

    /**
     * The fee in PKR for a registration of this type with the given number of delegates.
     */
    public function amountDueFor(int $delegateCount): int
    {
        if ($this->isIndividual()) {
            return $delegateCount * config('conference.fees.private_delegate', 1000);
        }

        return $delegateCount * config('conference.fees.per_delegate') + config('conference.fees.per_delegation');
    }
}
