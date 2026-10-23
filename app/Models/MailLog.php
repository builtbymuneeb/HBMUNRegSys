<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * An email the app handed to the mail server, or one that failed to send.
 *
 * Mail sent over SMTP does not land in the mailbox's Sent folder, so this is
 * the only record staff have of what went out.
 *
 * @property int $id
 * @property string $status
 * @property string $to
 * @property string|null $subject
 * @property string|null $notification
 * @property string|null $error
 * @property Carbon|null $created_at
 */
#[Fillable(['status', 'to', 'subject', 'notification', 'error'])]
class MailLog extends Model
{
    public const UPDATED_AT = null;

    public const SENT = 'sent';

    public const FAILED = 'failed';

    /**
     * A readable name for the kind of email, e.g. "Allocation published".
     */
    public function typeLabel(): string
    {
        return $this->notification ? Str::headline(class_basename($this->notification)) : __('Other');
    }

    public function failed(): bool
    {
        return $this->status === self::FAILED;
    }
}
