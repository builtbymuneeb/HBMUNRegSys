<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use App\Enums\TaskAudience;
use Database\Factories\AmbassadorTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Something staff ask campus ambassadors to do, worth a set number of points.
 * One-time tasks count once; repeatable ones count each time, up to an optional limit.
 *
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property int $points
 * @property TaskAudience $audience
 * @property bool $repeatable
 * @property int|null $max_completions
 * @property Carbon|null $due_at
 * @property Carbon|null $archived_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'description', 'points', 'audience', 'repeatable', 'max_completions', 'due_at', 'archived_at', 'created_by'])]
class AmbassadorTask extends Model
{
    /** @use HasFactory<AmbassadorTaskFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'audience' => 'everyone',
        'repeatable' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience' => TaskAudience::class,
            'repeatable' => 'boolean',
            'max_completions' => 'integer',
            'points' => 'integer',
            'due_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * Deleting a task deletes its submissions in the database; remove their proof files too.
     */
    protected static function booted(): void
    {
        static::deleting(function (AmbassadorTask $task) {
            $paths = AmbassadorSubmissionFile::whereIn('ambassador_submission_id', $task->submissions()->select('id'))->pluck('path')->all();
            Storage::disk('local')->delete($paths);
        });
    }

    /**
     * @return HasMany<AmbassadorSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(AmbassadorSubmission::class);
    }

    /**
     * The ambassadors a "Chosen ambassadors" task is for.
     *
     * @return BelongsToMany<CampusAmbassador, $this>
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(CampusAmbassador::class, 'ambassador_task_assignees');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Tasks staff haven't archived.
     *
     * @param  Builder<AmbassadorTask>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * Tasks this ambassador is meant to see.
     *
     * @param  Builder<AmbassadorTask>  $query
     */
    public function scopeVisibleTo(Builder $query, CampusAmbassador $ambassador): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('audience', TaskAudience::Everyone)
            ->orWhere('audience', TaskAudience::from($ambassador->type->value))
            ->orWhere(fn (Builder $query) => $query->where('audience', TaskAudience::Selected)
                ->whereHas('assignees', fn (Builder $query) => $query->whereKey($ambassador->id))));
    }

    /**
     * The ambassadors this task is for.
     *
     * @return Builder<CampusAmbassador>
     */
    public function audienceMembers(): Builder
    {
        return match ($this->audience) {
            TaskAudience::Everyone => CampusAmbassador::query(),
            TaskAudience::Selected => CampusAmbassador::whereHas('tasksAssigned', fn ($query) => $query->whereKey($this->id)),
            default => CampusAmbassador::where('type', $this->audience->type()),
        };
    }

    public function isFor(CampusAmbassador $ambassador): bool
    {
        return match ($this->audience) {
            TaskAudience::Everyone => true,
            TaskAudience::Selected => $this->assignees()->whereKey($ambassador->id)->exists(),
            default => $this->audience->type() === $ambassador->type,
        };
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isPastDue(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast();
    }

    /**
     * Whether ambassadors can still submit it at all.
     */
    public function isOpen(): bool
    {
        return ! $this->isArchived() && ! $this->isPastDue();
    }

    /**
     * How many times one ambassador can get points for it; null for no limit.
     */
    public function completionLimit(): ?int
    {
        return $this->repeatable ? $this->max_completions : 1;
    }

    /**
     * How many more times this ambassador can submit it: approved and waiting
     * submissions use up the limit, ones sent back don't. Null for no limit.
     */
    public function remainingFor(CampusAmbassador $ambassador): ?int
    {
        $limit = $this->completionLimit();

        if ($limit === null) {
            return null;
        }

        $used = $this->submissions()
            ->where('campus_ambassador_id', $ambassador->id)
            ->whereIn('status', [SubmissionStatus::Pending, SubmissionStatus::Approved])
            ->count();

        return max(0, $limit - $used);
    }

    public function canBeSubmittedBy(CampusAmbassador $ambassador): bool
    {
        return $this->isOpen() && $this->isFor($ambassador) && $this->remainingFor($ambassador) !== 0;
    }

    /**
     * A short line about how often it counts, e.g. "One time" or "Up to 5 times".
     */
    public function frequencyLabel(): string
    {
        if (! $this->repeatable) {
            return __('One time');
        }

        return $this->max_completions
            ? trans_choice('Up to :count time|Up to :count times', $this->max_completions)
            : __('As many times as you like');
    }
}
