<?php

namespace App\Models;

use Database\Factories\InboxMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * An email in the contact@ mailbox (direction "in"), or a reply staff sent from the inbox page (direction "out").
 *
 * `email` and `name` are always the other person, so one conversation is every message with the same `email`.
 *
 * @property int $id
 * @property string $direction
 * @property string|null $message_id
 * @property string|null $in_reply_to
 * @property string $email
 * @property string|null $name
 * @property string|null $subject
 * @property string|null $body
 * @property string|null $html
 * @property Carbon $received_at
 * @property Carbon|null $read_at
 * @property int|null $campus_ambassador_id
 * @property int|null $registration_id
 * @property int|null $sent_by
 */
#[Fillable(['direction', 'message_id', 'in_reply_to', 'email', 'name', 'subject', 'body', 'html', 'received_at', 'read_at', 'campus_ambassador_id', 'registration_id', 'sent_by'])]
class InboxMessage extends Model
{
    /** @use HasFactory<InboxMessageFactory> */
    use HasFactory;

    public const IN = 'in';

    public const OUT = 'out';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (InboxMessage $message) {
            $message->email = Str::lower(trim($message->email));
        });
    }

    /**
     * @return BelongsTo<CampusAmbassador, $this>
     */
    public function ambassador(): BelongsTo
    {
        return $this->belongsTo(CampusAmbassador::class, 'campus_ambassador_id');
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * Messages this user may read: conference managers see the whole mailbox, ambassador managers only mail from ambassadors.
     *
     * @param  Builder<InboxMessage>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->when(! $user->canManageConference(), fn (Builder $query) => $query->whereNotNull('campus_ambassador_id'));
    }

    /**
     * @param  Builder<InboxMessage>  $query
     */
    public function scopeIncoming(Builder $query): void
    {
        $query->where('direction', self::IN);
    }

    /**
     * @param  Builder<InboxMessage>  $query
     */
    public function scopeUnread(Builder $query): void
    {
        $query->where('direction', self::IN)->whereNull('read_at');
    }

    public function isIncoming(): bool
    {
        return $this->direction === self::IN;
    }

    /**
     * Who the message is from or to, by name when we know it.
     */
    public function displayName(): string
    {
        return $this->ambassador?->name ?? $this->name ?: $this->email;
    }

    /**
     * The body without the quoted earlier message most mail apps add below a reply ("On … wrote:" and "> " lines).
     */
    public function bodyWithoutQuote(): string
    {
        $body = (string) $this->body;
        $body = preg_split('/^\s*(On .{5,200}wrote:|-{2,}\s*Original Message\s*-{2,}|From: .+\R(Sent|Date): )/mi', $body)[0];
        $lines = array_filter(preg_split('/\R/', $body), fn (string $line) => ! str_starts_with(ltrim($line), '>'));

        return trim(implode("\n", $lines)) ?: trim((string) $this->body);
    }

    /**
     * Newsletters and notification emails read best formatted; personal replies read best as text with the quote hidden.
     */
    public function opensFormatted(): bool
    {
        return $this->html !== null && $this->in_reply_to === null && ! Str::startsWith(Str::lower((string) $this->subject), 're:');
    }

    /**
     * The sender's HTML as a page for a sandboxed frame: no scripts, forms or embeds, and links open in a new tab.
     */
    public function htmlDocument(): string
    {
        $guard = '<meta http-equiv="Content-Security-Policy" content="script-src \'none\'; object-src \'none\'; frame-src \'none\'; form-action \'none\'">'
            .'<base target="_blank">'
            .'<style>body{margin:0;padding:12px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:#1c1525;overflow-wrap:anywhere}img{max-width:100%;height:auto}table{max-width:100%!important}*{box-sizing:border-box}html{overflow-y:hidden}</style>';

        $html = (string) $this->html;

        return preg_match('/<head\b[^>]*>/i', $html)
            ? preg_replace('/<head\b[^>]*>/i', '$0'.$guard, $html, 1)
            : '<!DOCTYPE html><html><head><meta charset="utf-8">'.$guard.'</head><body>'.$html.'</body></html>';
    }

    /**
     * A one-line preview for the message list.
     */
    public function snippet(int $length = 120): string
    {
        return Str::limit(preg_replace('/\s+/', ' ', $this->bodyWithoutQuote()), $length);
    }
}
