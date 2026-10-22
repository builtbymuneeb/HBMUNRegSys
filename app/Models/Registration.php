<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Enums\RegistrationType;
use Database\Factories\RegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $reference
 * @property RegistrationType $type
 * @property RegistrationStatus $status
 * @property string|null $organisation_name
 * @property string $city
 * @property string $contact_name
 * @property string $contact_cnic
 * @property string $contact_phone
 * @property string $contact_email
 * @property int $amount_due
 * @property string $payment_screenshot_path
 * @property string|null $rejection_reason
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'type',
    'status',
    'organisation_name',
    'city',
    'contact_name',
    'contact_cnic',
    'contact_phone',
    'contact_email',
    'amount_due',
    'payment_screenshot_path',
    'rejection_reason',
    'reviewed_by',
    'reviewed_at',
])]
class Registration extends Model
{
    /** @use HasFactory<RegistrationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RegistrationType::class,
            'status' => RegistrationStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Assign the public reference number once the registration has an id.
     */
    protected static function booted(): void
    {
        static::created(function (Registration $registration) {
            $registration->reference = sprintf(
                '%s-%04d',
                config('conference.reference_prefix'),
                $registration->id,
            );

            $registration->saveQuietly();
        });
    }

    /**
     * @return HasMany<Delegate, $this>
     */
    public function delegates(): HasMany
    {
        return $this->hasMany(Delegate::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('id');
    }

    public function isPending(): bool
    {
        return $this->status === RegistrationStatus::Pending;
    }

    public function isRejected(): bool
    {
        return $this->status === RegistrationStatus::Rejected;
    }

    /**
     * The institution or delegation name, falling back to the contact's name.
     */
    public function displayName(): string
    {
        return $this->organisation_name ?: $this->contact_name;
    }
}
