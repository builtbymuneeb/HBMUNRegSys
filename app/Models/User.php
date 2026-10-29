<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property UserRole|null $requested_role
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property Carbon|null $password_set_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_set_at' => 'datetime',
            'role' => UserRole::class,
            'requested_role' => UserRole::class,
        ];
    }

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'delegate',
    ];

    /**
     * Note when someone chooses a password (from the set-password email, "Forgot password"
     * or settings), so staff can see who has opened their email. Code that sets
     * password_set_at itself, like the random placeholder for new delegates, wins.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->isDirty('password') && ! $user->isDirty('password_set_at')) {
                $user->password_set_at = now();
            }
        });
    }

    /**
     * @return HasOne<Delegate, $this>
     */
    public function delegate(): HasOne
    {
        return $this->hasOne(Delegate::class)->latestOfMany();
    }

    /**
     * @return HasMany<PushSubscription, $this>
     */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    public function canReviewRegistrations(): bool
    {
        return in_array($this->role, [UserRole::Registrations, UserRole::Admin], true);
    }

    public function canViewRegistrations(): bool
    {
        return in_array($this->role, [UserRole::Registrations, UserRole::Admin], true);
    }

    /**
     * Allocations, committee details, study guides and announcements.
     */
    public function canManageConference(): bool
    {
        return in_array($this->role, [UserRole::Registrations, UserRole::Admin], true);
    }

    /**
     * Campus ambassadors and the registrations their codes bring in.
     */
    public function canManageAmbassadors(): bool
    {
        return in_array($this->role, [UserRole::Registrations, UserRole::Ambassadors, UserRole::Admin], true);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isAwaitingApproval(): bool
    {
        return $this->role === UserRole::Applicant;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
