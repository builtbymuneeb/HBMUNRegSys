<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Support\ReferralCode;
use Database\Factories\CampusAmbassadorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * A campus ambassador whose referral code registrants can enter.
 *
 * @property int $id
 * @property string $code
 * @property string $code_key
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $institution
 * @property string|null $city
 * @property string|null $notes
 * @property string|null $flag_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'email', 'phone', 'institution', 'city', 'notes', 'flag_reason'])]
class CampusAmbassador extends Model
{
    /** @use HasFactory<CampusAmbassadorFactory> */
    use HasFactory;

    /**
     * Keep the matching key in step with the code, and re-link registrations
     * whenever a code is added or changed, so codes typed before the
     * ambassador was added still count.
     */
    protected static function booted(): void
    {
        static::saving(function (CampusAmbassador $ambassador) {
            $ambassador->code = ReferralCode::clean($ambassador->code);
            $ambassador->code_key = ReferralCode::key($ambassador->code);
        });

        static::saved(fn (CampusAmbassador $ambassador) => $ambassador->linkRegistrations());
    }

    /**
     * The ambassador whose code matches the one a registrant typed, if any.
     */
    public static function forCode(?string $code): ?self
    {
        $key = ReferralCode::key($code);

        return $key === null ? null : self::where('code_key', $key)->first();
    }

    /**
     * Problems with the contact details staff should check before relying on them.
     *
     * @return list<string>
     */
    public static function contactProblems(?string $email, ?string $phone): array
    {
        $problems = [];

        if (blank($phone)) {
            $problems[] = __('No phone number');
        } elseif (! preg_match('/^(\+92|0)3\d{9}$/', preg_replace('/[\s\-()]/', '', $phone))) {
            $problems[] = __('Phone :phone isn’t a Pakistani mobile number', ['phone' => $phone]);
        }

        if (blank($email)) {
            $problems[] = __('No email');
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $problems[] = __('Email :email doesn’t look valid', ['email' => $email]);
        }

        return $problems;
    }

    /**
     * The digits a phone number is compared on, so 0300…, +92 300… and 92300… are the same number.
     */
    public static function phoneKey(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return $digits === '' ? null : preg_replace('/^(?:92|0)(3\d{9})$/', '$1', $digits);
    }

    public function needsCheck(): bool
    {
        return filled($this->flag_reason);
    }

    /**
     * @param  Builder<CampusAmbassador>  $query
     */
    public function scopeFlagged(Builder $query): void
    {
        $query->whereNotNull('flag_reason')->where('flag_reason', '!=', '');
    }

    /**
     * The next unused HB## code.
     */
    public static function nextCode(): string
    {
        $highest = self::pluck('code')->map(fn (string $code) => ReferralCode::number($code) ?? 0)->max() ?? 0;

        return ReferralCode::format($highest + 1);
    }

    /**
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * @return HasManyThrough<Delegate, Registration, $this>
     */
    public function delegates(): HasManyThrough
    {
        return $this->hasManyThrough(Delegate::class, Registration::class);
    }

    /**
     * Add the counts shown on the ambassadors pages.
     *
     * @param  Builder<CampusAmbassador>  $query
     */
    public function scopeWithReferralCounts(Builder $query): void
    {
        $query->withCount([
            'registrations',
            'registrations as verified_registrations_count' => fn ($query) => $query->where('status', RegistrationStatus::Verified),
            'delegates',
            'delegates as verified_delegates_count' => fn ($query) => $query->where('registrations.status', RegistrationStatus::Verified),
        ]);
    }

    /**
     * Point every registration whose referral code matches at this ambassador,
     * and release any that no longer match after a code change.
     */
    public function linkRegistrations(): void
    {
        $matches = fn (Registration $registration) => ReferralCode::key($registration->referral_code) === $this->code_key;

        $stale = $this->registrations()->get(['id', 'referral_code'])->reject($matches)->modelKeys();
        if ($stale !== []) {
            Registration::whereKey($stale)->toBase()->update(['campus_ambassador_id' => null]);
        }

        $unlinked = Registration::query()
            ->whereNull('campus_ambassador_id')
            ->whereNotNull('referral_code')
            ->get(['id', 'referral_code'])
            ->filter($matches)
            ->modelKeys();

        if ($unlinked !== []) {
            Registration::whereKey($unlinked)->toBase()->update(['campus_ambassador_id' => $this->id]);
        }
    }
}
