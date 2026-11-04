<?php

namespace App\Enums;

/**
 * Where an ambassador's "done" on a task stands with staff.
 */
enum SubmissionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for review',
            self::Approved => 'Approved',
            self::Rejected => 'Sent back',
        };
    }

    /**
     * The Flux badge colour used for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'green',
            self::Rejected => 'red',
        };
    }
}
