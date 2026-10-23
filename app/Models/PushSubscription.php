<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Minishlink\WebPush\Subscription;

/**
 * A browser that asked for Web Push notifications, so it hears about updates even with the dashboard closed.
 *
 * @property int $id
 * @property int $user_id
 * @property string $endpoint
 * @property string $public_key
 * @property string $auth_token
 * @property string $content_encoding
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'endpoint', 'public_key', 'auth_token', 'content_encoding'])]
class PushSubscription extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The subscription in the form the Web Push library sends to.
     */
    public function toWebPush(): Subscription
    {
        return new Subscription($this->endpoint, $this->public_key, $this->auth_token, $this->content_encoding);
    }
}
