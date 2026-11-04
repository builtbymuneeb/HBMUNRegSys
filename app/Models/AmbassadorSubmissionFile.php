<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A screenshot or document an ambassador uploaded as proof. Kept on the private disk.
 *
 * @property int $id
 * @property int $ambassador_submission_id
 * @property string $path
 * @property string|null $original_name
 * @property string|null $mime_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['path', 'original_name', 'mime_type'])]
class AmbassadorSubmissionFile extends Model
{
    /**
     * @return BelongsTo<AmbassadorSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(AmbassadorSubmission::class, 'ambassador_submission_id');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function url(): string
    {
        return route('ambassador-proof', $this);
    }
}
