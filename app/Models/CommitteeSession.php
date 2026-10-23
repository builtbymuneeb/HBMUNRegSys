<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CommitteeSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One sitting of a committee, with its own time slot and meeting link.
 *
 * @property int $id
 * @property int $committee_id
 * @property string $title
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property string|null $meeting_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'starts_at', 'ends_at', 'meeting_url'])]
class CommitteeSession extends Model
{
    /** @use HasFactory<CommitteeSessionFactory> */
    use HasFactory;

    /**
     * The timezone session times are entered and shown in.
     */
    public const TIMEZONE = 'Asia/Karachi';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Committee, $this>
     */
    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    /**
     * The local start time.
     */
    public function localStart(): CarbonImmutable
    {
        return $this->starts_at->toImmutable()->timezone(self::TIMEZONE);
    }

    /**
     * The local end time.
     */
    public function localEnd(): CarbonImmutable
    {
        return $this->ends_at->toImmutable()->timezone(self::TIMEZONE);
    }

    /**
     * e.g. "Sat 21 Nov · 10:00 am – 1:00 pm".
     */
    public function timeLabel(): string
    {
        return $this->localStart()->format('D j M').' · '.$this->localStart()->format('g:i a').' – '.$this->localEnd()->format('g:i a');
    }

    /**
     * Whether the session has not finished yet.
     */
    public function isUpcoming(): bool
    {
        return $this->ends_at->isFuture();
    }
}
