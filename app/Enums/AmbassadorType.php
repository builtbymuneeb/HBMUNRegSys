<?php

namespace App\Enums;

/**
 * Which campus ambassador programme someone joined, matching the two sign-up forms on the site.
 */
enum AmbassadorType: string
{
    /** A Homebridge student, promoting within the school. */
    case Homebridge = 'hb';

    /** A student at another school, promoting there. */
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Homebridge => 'HB',
            self::Private => 'Private',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Homebridge => 'Homebridge students (internal)',
            self::Private => 'Students from other schools (external)',
        };
    }

    /**
     * The Flux badge colour used for this type.
     */
    public function color(): string
    {
        return match ($this) {
            self::Homebridge => 'purple',
            self::Private => 'sky',
        };
    }
}
