<?php

namespace App\Enums;

/**
 * Which campus ambassadors an ambassador task is for.
 */
enum TaskAudience: string
{
    case Everyone = 'everyone';
    case Homebridge = 'hb';
    case Private = 'private';
    case Selected = 'selected';

    public function label(): string
    {
        return match ($this) {
            self::Everyone => 'All ambassadors',
            self::Homebridge => 'HB ambassadors',
            self::Private => 'Private ambassadors',
            self::Selected => 'Chosen ambassadors',
        };
    }

    /**
     * The ambassador type this audience covers, when it is one type.
     */
    public function type(): ?AmbassadorType
    {
        return match ($this) {
            self::Homebridge => AmbassadorType::Homebridge,
            self::Private => AmbassadorType::Private,
            default => null,
        };
    }
}
