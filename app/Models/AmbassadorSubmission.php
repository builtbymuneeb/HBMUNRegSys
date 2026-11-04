<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An ambassador marking a task as done, with the proof they gave, and what staff decided.
 *
 * @property int $id
 * @property int $ambassador_task_id
 * @property int $campus_ambassador_id
 * @property string|null $note
 * @property string|null $link
 * @property SubmissionStatus $status
 * @property int|null $points_awarded
 * @property string|null $review_note
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['note', 'link', 'status', 'points_awarded', 'review_note', 'reviewed_by', 'reviewed_at'])]
class AmbassadorSubmission extends Model
{
    /** The most proof files one submission can have. */
    public const MAX_FILES = 6;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'points_awarded' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AmbassadorTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(AmbassadorTask::class, 'ambassador_task_id');
    }

    /**
     * @return BelongsTo<CampusAmbassador, $this>
     */
    public function ambassador(): BelongsTo
    {
        return $this->belongsTo(CampusAmbassador::class, 'campus_ambassador_id');
    }

    /**
     * @return HasMany<AmbassadorSubmissionFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(AmbassadorSubmissionFile::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @param  Builder<AmbassadorSubmission>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', SubmissionStatus::Pending);
    }

    /**
     * @param  Builder<AmbassadorSubmission>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', SubmissionStatus::Approved);
    }

    public function isPending(): bool
    {
        return $this->status === SubmissionStatus::Pending;
    }
}
