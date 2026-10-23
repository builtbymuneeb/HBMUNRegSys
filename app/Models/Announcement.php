<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $committee_id
 * @property string $title
 * @property string $body
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['committee_id', 'title', 'body', 'user_id'])]
class Announcement extends Model
{
    /**
     * @return BelongsTo<Committee, $this>
     */
    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Announcements for everyone, plus those for the given committee.
     *
     * @param  Builder<Announcement>  $query
     */
    public function scopeVisibleToCommittee(Builder $query, ?int $committeeId): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereNull('committee_id')
            ->when($committeeId, fn (Builder $query) => $query->orWhere('committee_id', $committeeId)));
    }
}
