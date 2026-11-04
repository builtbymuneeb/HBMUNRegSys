<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A value staff can change in the app, stored by key.
 *
 * @property string $key
 * @property string|null $value
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /** Leaderboard points an ambassador earns for each verified delegate their code brings in. */
    public const POINTS_PER_VERIFIED_DELEGATE = 'ambassador_points_per_verified_delegate';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::whereKey($key)->value('value') ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function pointsPerVerifiedDelegate(): int
    {
        return max(0, (int) self::get(self::POINTS_PER_VERIFIED_DELEGATE, '0'));
    }
}
