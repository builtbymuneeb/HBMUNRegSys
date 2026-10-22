<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string $auditable_type
 * @property int $auditable_id
 * @property array<string, mixed>|null $properties
 * @property Carbon|null $created_at
 */
#[Fillable(['user_id', 'action', 'properties'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    /**
     * Record that an action was taken on a model.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function record(string $action, Model $auditable, ?User $user = null, array $properties = []): self
    {
        $log = new self([
            'user_id' => $user?->id,
            'action' => $action,
            'properties' => $properties ?: null,
        ]);

        $log->auditable()->associate($auditable);
        $log->save();

        return $log;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
