<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Points staff added or took away by hand, with the reason.
 *
 * @property int $id
 * @property int $campus_ambassador_id
 * @property int $points
 * @property string $reason
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['points', 'reason', 'created_by'])]
class AmbassadorPointAdjustment extends Model
{
    protected function casts(): array
    {
        return ['points' => 'integer'];
    }

    /**
     * @return BelongsTo<CampusAmbassador, $this>
     */
    public function ambassador(): BelongsTo
    {
        return $this->belongsTo(CampusAmbassador::class, 'campus_ambassador_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
