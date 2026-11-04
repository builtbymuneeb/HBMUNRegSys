<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One payment receipt for a registration, uploaded by the registrant or added by staff.
 *
 * @property int $id
 * @property int $registration_id
 * @property string $path
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['path', 'uploaded_by'])]
class PaymentScreenshot extends Model
{
    /**
     * The most screenshots a registrant can upload at once, one per receipt.
     */
    public const MAX_UPLOADS = 6;

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /**
     * The staff member who added it; null when the registrant uploaded it.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
