<?php

namespace App\Enums;

enum MunExperience: string
{
    case FirstTimer = 'first_timer';
    case Some = 'some';
    case Experienced = 'experienced';

    public function label(): string
    {
        return match ($this) {
            self::FirstTimer => 'First MUN',
            self::Some => '1–4 MUNs',
            self::Experienced => '5+ MUNs',
        };
    }
}
