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
            self::PrivateDelegate => 'Individual delegate',
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
     * The fee in PKR for a registration of this type with the given number of delegates,
     * of whom $homebridgeCount are Homebridge students. An individual delegate pays the
     * Homebridge individual fee if they are a Homebridge student, otherwise the private
     * one. In a delegation, the Homebridge discount (if any) applies to each Homebridge
     * member, and to the delegation fee when the whole team is from Homebridge.
     */
    public function amountDueFor(int $delegateCount, int $homebridgeCount = 0): int
    {
        if ($this->isIndividual()) {
            return $delegateCount * self::individualFee($homebridgeCount > 0);
        }

        $homebridgeCount = max(0, min($homebridgeCount, $delegateCount));
        $perDelegate = config('conference.fees.per_delegate');

        $total = ($delegateCount - $homebridgeCount) * $perDelegate
            + $homebridgeCount * self::homebridgeRate($perDelegate);

        $delegationFee = config('conference.fees.per_delegation');
        $allHomebridge = $delegateCount > 0 && $homebridgeCount === $delegateCount;

        return $total + ($allHomebridge ? self::homebridgeRate($delegationFee) : $delegationFee);
    }

    /**
     * What one delegate registering on their own pays.
     */
    public static function individualFee(bool $homebridge): int
    {
        $private = (int) config('conference.fees.private_delegate', 1000);

        return $homebridge ? (int) config('conference.fees.homebridge_individual', $private) : $private;
    }

    /**
     * The Homebridge discount, as a percentage.
     */
    public static function homebridgeDiscountPercent(): int
    {
        return (int) config('conference.fees.homebridge_discount_percent', 0);
    }

    /**
     * What a Homebridge student pays for a fee of $amount.
     */
    public static function homebridgeRate(int $amount): int
    {
        return (int) round($amount * (100 - self::homebridgeDiscountPercent()) / 100);
    }
}
