<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One visit to a public page, for the admin's visitor counts. The visitor is a keyed
 * hash of IP and browser, so we can count people without storing their IP address.
 *
 * @property int $id
 * @property string $path
 * @property string $visitor
 * @property string|null $source
 * @property string $device
 * @property string|null $browser
 * @property Carbon $viewed_on
 * @property Carbon|null $created_at
 */
#[Fillable(['path', 'visitor', 'source', 'device', 'browser', 'viewed_on'])]
class PageView extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'viewed_on' => 'date',
        ];
    }
}
