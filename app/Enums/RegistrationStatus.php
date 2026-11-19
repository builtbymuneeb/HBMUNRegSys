<?php

namespace App\Enums;

enum RegistrationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    /** Suspicious: delegates can't use their dashboard until it's restored. */
    case Blocked = 'blocked';

    /** Set aside, out of every list and total, until it's restored. */
    case Archived = 'archived';

    /** In the bin: restorable, or deleted for good by an admin. */
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending verification',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
            self::Blocked => 'Blocked',
            self::Archived => 'Archived',
            self::Deleted => 'In the bin',
        };
    }

    /**
     * The Flux badge colour used for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Verified => 'green',
            self::Rejected => 'red',
            self::Blocked => 'rose',
            self::Archived => 'zinc',
            self::Deleted => 'zinc',
        };
    }

    /**
     * Blocked, archived or binned: taken out by staff and restorable.
     */
    public function isModerated(): bool
    {
        return in_array($this, self::moderated(), true);
    }

    /**
     * @return list<self>
     */
    public static function moderated(): array
    {
        return [self::Blocked, self::Archived, self::Deleted];
    }

    /**
     * The statuses of the normal review flow.
     *
     * @return list<self>
     */
    public static function review(): array
    {
        return [self::Pending, self::Verified, self::Rejected];
    }
}
