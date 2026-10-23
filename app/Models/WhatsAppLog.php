<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A WhatsApp message staff opened to send by hand. WhatsApp doesn't tell us whether
 * they pressed send, so this records the click; it's the team's checklist, not proof.
 *
 * @property int $id
 * @property string $kind
 * @property string $phone
 * @property int|null $registration_id
 * @property int|null $delegate_id
 * @property int|null $sent_by
 * @property Carbon|null $created_at
 */
#[Fillable(['kind', 'phone', 'registration_id', 'delegate_id', 'sent_by'])]
class WhatsAppLog extends Model
{
    protected $table = 'whatsapp_logs';

    public const UPDATED_AT = null;

    /** The contact person, about their registration's current status. */
    public const CONTACT = 'contact';

    /** A delegate, about their new account and set-password email. */
    public const ACCOUNT = 'account';

    /** A delegate, about their sent allocation. */
    public const ALLOCATION = 'allocation';

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
