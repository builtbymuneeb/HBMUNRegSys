<?php

namespace App\Enums;

/**
 * How much of a staff page a team member can use.
 */
enum PageAccess: string
{
    /** Can open the page and look, but every change is refused. */
    case View = 'view';

    /** Can open the page and make changes on it. */
    case Edit = 'edit';

    public function label(): string
    {
        return match ($this) {
            self::View => 'View only',
            self::Edit => 'Can edit',
        };
    }

    public function includes(self $other): bool
    {
        return $this === self::Edit || $other === self::View;
    }
}
