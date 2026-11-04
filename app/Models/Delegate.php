<?php

namespace App\Models;

use App\Enums\MunExperience;
use App\Enums\PortfolioType;
use Database\Factories\DelegateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property string|null $first_preference
 * @property string|null $second_preference
 * @property MunExperience|null $mun_experience
 * @property int|null $allocated_committee_id
 * @property string|null $allocated_portfolio
 * @property int|null $allocated_by
 * @property Carbon|null $allocated_at
 * @property Carbon|null $allocation_published_at
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
    'first_preference',
    'second_preference',
    'mun_experience',
    'allocated_committee_id',
    'allocated_portfolio',
    'allocated_by',
    'allocated_at',
    'allocation_published_at',
])]
class Delegate extends Model
{
    /** @use HasFactory<DelegateFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allocated_at' => 'datetime',
            'allocation_published_at' => 'datetime',
            'mun_experience' => MunExperience::class,
        ];
    }

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

    public function isAllocated(): bool
    {
        return $this->allocated_committee_id !== null && filled($this->allocated_portfolio);
    }

    /**
     * Whether the delegate has been sent their allocation and can see it.
     */
    public function hasPublishedAllocation(): bool
    {
        return $this->isAllocated() && $this->allocation_published_at !== null;
    }

    /**
     * The delegate's preference for the given committee, if any. Each committee choice has its
     * own; for any other committee, the preference of a chosen committee of the same kind is used.
     * Delegates registered before per-committee preferences fall back to their shared one.
     */
    public function preferenceFor(Committee $committee): ?string
    {
        if ($committee->id === $this->first_committee_id && filled($this->first_preference)) {
            return $this->first_preference;
        }

        if ($committee->id === $this->second_committee_id && filled($this->second_preference)) {
            return $this->second_preference;
        }

        if (! in_array($committee->id, [$this->first_committee_id, $this->second_committee_id], true)) {
            foreach (['first' => $this->firstCommittee, 'second' => $this->secondCommittee] as $slot => $chosen) {
                if ($chosen?->portfolio_type === $committee->portfolio_type && filled($this->{"{$slot}_preference"})) {
                    return $this->{"{$slot}_preference"};
                }
            }
        }

        return $committee->portfolio_type === PortfolioType::Character
            ? $this->personality_preference
            : $this->country_preference;
    }

    /**
     * @return HasMany<WhatsAppLog, $this>
     */
    public function whatsAppLogs(): HasMany
    {
        return $this->hasMany(WhatsAppLog::class)->latest('id');
    }

    /**
     * The WhatsApp message telling them their account is ready, if one was sent.
     */
    public function accountWhatsApp(): ?WhatsAppLog
    {
        return $this->whatsAppLogs->firstWhere('kind', WhatsAppLog::ACCOUNT);
    }

    /**
     * The WhatsApp message with their current allocation, if one was sent since it last changed.
     */
    public function allocationWhatsApp(): ?WhatsAppLog
    {
        if (! $this->allocation_published_at) {
            return null;
        }

        $since = $this->allocation_published_at->copy()->startOfSecond();

        return $this->whatsAppLogs
            ->where('kind', WhatsAppLog::ALLOCATION)
            ->first(fn (WhatsAppLog $log) => $log->created_at->gte($since));
    }
}
