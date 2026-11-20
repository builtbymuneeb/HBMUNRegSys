<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One row of the conference timetable (Day 1, 2, 3 …), optionally with a link
 * that delegates only see once staff switch it on.
 *
 * @property int $id
 * @property int $day
 * @property string $starts_at
 * @property string|null $ends_at
 * @property string $activity
 * @property string|null $link
 * @property bool $link_visible
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['day', 'starts_at', 'ends_at', 'activity', 'link', 'link_visible'])]
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
     * The link delegates may open, or null while it's hidden.
     */
    public function delegateLink(): ?string
    {
        return $this->link_visible ? $this->link : null;
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
