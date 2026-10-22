<?php

namespace App\Models;

use Database\Factories\DelegateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $registration_id
 * @property int|null $user_id
 * @property string $name
 * @property string $cnic
 * @property string $email
 * @property string $phone
 * @property int $first_committee_id
 * @property int $second_committee_id
 * @property string|null $country_preference
 * @property string|null $personality_preference
 * @property int|null $allocated_committee_id
 * @property string|null $allocated_portfolio
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'name',
    'cnic',
    'email',
    'phone',
    'first_committee_id',
    'second_committee_id',
    'country_preference',
    'personality_preference',
    'allocated_committee_id',
    'allocated_portfolio',
])]
class Delegate extends Model
{
    /** @use HasFactory<DelegateFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Committee, $this>
     */
    public function firstCommittee(): BelongsTo
    {
        return $this->belongsTo(Committee::class, 'first_committee_id');
    }

    /**
     * @return BelongsTo<Committee, $this>
     */
    public function secondCommittee(): BelongsTo
    {
        return $this->belongsTo(Committee::class, 'second_committee_id');
    }

    /**
     * @return BelongsTo<Committee, $this>
     */
    public function allocatedCommittee(): BelongsTo
    {
        return $this->belongsTo(Committee::class, 'allocated_committee_id');
    }
}
