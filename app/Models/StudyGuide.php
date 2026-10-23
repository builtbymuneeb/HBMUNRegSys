<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

/**
 * @property int $id
 * @property int|null $committee_id
 * @property string $title
 * @property string|null $description
 * @property string $file_path
 * @property string $file_name
 * @property int $file_size
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['committee_id', 'title', 'description', 'file_path', 'file_name', 'file_size', 'uploaded_by'])]
class StudyGuide extends Model
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
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Guides for every committee, plus those for the given committee.
     *
     * @param  Builder<StudyGuide>  $query
     */
    public function scopeVisibleToCommittee(Builder $query, ?int $committeeId): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereNull('committee_id')
            ->when($committeeId, fn (Builder $query) => $query->orWhere('committee_id', $committeeId)));
    }

    public function humanFileSize(): string
    {
        return Number::fileSize($this->file_size, precision: 1);
    }
}
