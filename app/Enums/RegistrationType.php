<?php

namespace App\Enums;

enum RegistrationType: string
{
    case InstitutionDelegation = 'institution_delegation';
    case PrivateDelegate = 'private_delegate';
    case PrivateDelegation = 'private_delegation';

    public function label(): string
    {
        return match ($this) {
            self::InstitutionDelegation => 'Institution delegation',
            self::PrivateDelegate => 'Private delegate',
            self::PrivateDelegation => 'Private delegation',
        };
    }

    public function isDelegation(): bool
    {
        return $this !== self::PrivateDelegate;
    }

    public function minimumDelegates(): int
    {
        return $this->isDelegation() ? config('conference.delegation.min_delegates') : 1;
    }

    public function maximumDelegates(): int
    {
        return $this->isDelegation() ? config('conference.delegation.max_delegates') : 1;
    }

    /**
     * The fee in PKR for a registration of this type with the given number of delegates.
     */
    public function amountDueFor(int $delegateCount): int
    {
        $amount = $delegateCount * config('conference.fees.per_delegate');

        if ($this->isDelegation()) {
            $amount += config('conference.fees.per_delegation');
        }

        return $amount;
    }
}
