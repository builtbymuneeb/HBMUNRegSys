<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One row of the conference timetable (Day 1, 2, 3 …). A row either has its own link
 * (e.g. the opening ceremony) or sends each delegate to their committee's room. Either
 * way delegates only get the link once staff switch it on.
 *
 * @property int $id
 * @property int $day
 * @property string $starts_at
 * @property string|null $ends_at
 * @property string $activity
 * @property string|null $link
 * @property bool $uses_committee_link
 * @property bool $link_visible
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['day', 'starts_at', 'ends_at', 'activity', 'link', 'uses_committee_link', 'link_visible'])]
class ScheduleItem extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'integer',
            'uses_committee_link' => 'boolean',
            'link_visible' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('day')->orderBy('starts_at')->orderBy('id');
    }

    /**
     * Whether this row has any link to reveal.
     */
    public function hasLink(): bool
    {
        return $this->uses_committee_link || filled($this->link);
    }

    /**
     * The link this delegate may open, or null while it's hidden or missing.
     * Committee-room rows only ever give out the delegate's own committee's link.
     */
    public function delegateLink(?Committee $committee): ?string
    {
        if (! $this->link_visible) {
            return null;
        }

        return $this->uses_committee_link ? $committee?->meeting_url : $this->link;
    }

    /**
     * "HH:MM" for time inputs.
     */
    public function startInput(): string
    {
        return substr($this->starts_at, 0, 5);
    }

    public function endInput(): string
    {
        return $this->ends_at ? substr($this->ends_at, 0, 5) : '';
    }

    /**
     * e.g. "10:00 AM – 11:00 AM", or just "06:45 PM" when there's no end.
     */
    public function timeLabel(): string
    {
        $format = fn (string $time) => Carbon::createFromFormat('H:i', substr($time, 0, 5))->format('h:i A');

        return $this->ends_at
            ? $format($this->starts_at).' – '.$format($this->ends_at)
            : $format($this->starts_at);
    }
}
