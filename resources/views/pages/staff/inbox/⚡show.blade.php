<?php

use App\Models\AuditLog;
use App\Models\InboxMessage;
use App\Notifications\InboxReply;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Inbox')] class extends Component {
    public InboxMessage $message;

    public string $reply = '';

    public function mount(): void
    {
        abort_unless(InboxMessage::visibleTo(Auth::user())->whereKey($this->message->id)->exists(), 404);

        $this->conversationQuery()->unread()->update(['read_at' => now()]);
    }

    /**
     * Every message to and from this person, oldest first.
     *
     * @return Collection<int, InboxMessage>
     */
    #[Computed]
    public function conversation(): Collection
    {
        return $this->conversationQuery()->with(['ambassador', 'registration', 'sender:id,name'])->oldest('received_at')->oldest('id')->get();
    }

    /**
     * The newest message they sent, which a reply answers.
     */
    #[Computed]
    public function latestIncoming(): ?InboxMessage
    {
        return $this->conversation->where('direction', InboxMessage::IN)->last();
    }

    public function sendReply(): void
    {
        Gate::authorize('manage-ambassadors');

        $this->validate(['reply' => ['required', 'string', 'max:5000']], attributes: ['reply' => __('reply')]);

        $original = $this->latestIncoming ?? $this->message;

        Notification::route('mail', $original->email)->notify(new InboxReply($original, $this->reply));

        $sent = InboxMessage::create([
            'direction' => InboxMessage::OUT,
            'email' => $original->email,
            'name' => $original->name,
            'subject' => InboxReply::subjectFor($original),
            'body' => trim($this->reply),
            'received_at' => now(),
            'campus_ambassador_id' => $original->campus_ambassador_id,
            'registration_id' => $original->registration_id,
            'sent_by' => Auth::id(),
        ]);

        AuditLog::record('inbox_replied', $sent, Auth::user(), ['to' => $original->email]);

        $this->reset('reply');
        unset($this->conversation, $this->latestIncoming);
        Flux::toast(variant: 'success', text: __('Reply on its way to :email.', ['email' => $original->email]));
    }

    public function markUnread(): void
    {
        $this->latestIncoming?->update(['read_at' => null]);

        $this->redirectRoute('staff.inbox', navigate: true);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<InboxMessage>
     */
    private function conversationQuery()
    {
        return InboxMessage::visibleTo(Auth::user())->where('email', $this->message->email);
    }
}; ?>

<div class="flex w-full max-w-4xl flex-col gap-6 p-6 lg:p-8">
    <div>
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('staff.inbox')" wire:navigate class="-ml-2">{{ __('Inbox') }}</flux:button>
    </div>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ $message->displayName() }}</flux:heading>
            <flux:text class="mt-1 flex flex-wrap items-center gap-2">
                <a href="mailto:{{ $message->email }}" class="underline decoration-zinc-500 underline-offset-2">{{ $message->email }}</a>
                @if ($ambassador = $this->conversation->pluck('ambassador')->filter()->first())
                    <flux:badge size="sm" color="purple" icon="ticket" :href="route('staff.ambassadors.show', $ambassador)" wire:navigate>
                        {{ __('Campus ambassador :code', ['code' => $ambassador->code]) }}
                    </flux:badge>
                @endif
                @if (($registration = $this->conversation->pluck('registration')->filter()->first()) && auth()->user()->can('view', $registration))
                    <flux:badge size="sm" color="zinc" icon="clipboard-document-check" :href="route('staff.registrations.show', $registration)" wire:navigate>
                        {{ __('Registration :reference', ['reference' => $registration->reference]) }}
                    </flux:badge>
                @endif
            </flux:text>
        </div>
        @if ($this->latestIncoming)
            <flux:button size="sm" variant="ghost" icon="envelope" wire:click="markUnread">{{ __('Mark as unread') }}</flux:button>
        @endif
    </div>

    <div class="flex flex-col gap-4">
        @foreach ($this->conversation as $item)
            <flux:card wire:key="message-{{ $item->id }}" @class(['space-y-3', 'ml-6 border-hb-purple-line/60 bg-hb-purple/5 sm:ml-16 dark:bg-hb-purple/20' => ! $item->isIncoming(), 'mr-6 sm:mr-16' => $item->isIncoming()])>
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <div>
                        <flux:heading>{{ $item->subject ?: __('(no subject)') }}</flux:heading>
                        <flux:text class="text-xs">
                            {{ $item->isIncoming() ? __('From :name', ['name' => $item->displayName()]) : __('Reply by :name', ['name' => $item->sender?->name ?? __('staff')]) }}
                        </flux:text>
                    </div>
                    <flux:text class="text-xs whitespace-nowrap">{{ $item->received_at->timezone('Asia/Karachi')->format('j M Y, g:i a') }}</flux:text>
                </div>

                <div x-data="{ full: false, formatted: @js($item->opensFormatted()) }">
                    @if ($item->html)
                        {{-- The sender's own HTML, in a sandbox that runs no scripts; links open in a new tab. --}}
                        <div x-show="formatted" x-cloak class="overflow-hidden rounded-md bg-white">
                            <iframe
                                title="{{ $item->subject }}"
                                sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
                                referrerpolicy="no-referrer"
                                srcdoc="{{ $item->htmlDocument() }}"
                                class="block h-48 w-full"
                                x-init="
                                    const fit = () => { const doc = $el.contentDocument; if (doc?.body && doc.documentElement.scrollHeight) { $el.style.height = doc.documentElement.scrollHeight + 'px' } };
                                    const watch = () => { fit(); if ($el.contentDocument?.body) { new ResizeObserver(fit).observe($el.contentDocument.body) } };
                                    $el.addEventListener('load', watch);
                                    if ($el.contentDocument?.readyState === 'complete') { watch() }
                                    $watch('formatted', () => $nextTick(fit));
                                "
                            ></iframe>
                        </div>
                    @endif
                    <div x-show="! formatted">
                        <div class="text-sm leading-relaxed whitespace-pre-line text-zinc-700 [overflow-wrap:anywhere] dark:text-zinc-200" x-show="! full">{{ $item->bodyWithoutQuote() ?: __('(empty message)') }}</div>
                        @if ($item->bodyWithoutQuote() !== trim((string) $item->body))
                            <div class="text-sm leading-relaxed whitespace-pre-line text-zinc-700 [overflow-wrap:anywhere] dark:text-zinc-200" x-show="full" x-cloak>{{ $item->body }}</div>
                        @endif
                    </div>
                    <div class="mt-2 flex gap-4 text-xs text-zinc-500">
                        @if ($item->bodyWithoutQuote() !== trim((string) $item->body))
                            <button type="button" class="underline underline-offset-2" x-show="! formatted" x-on:click="full = ! full" x-text="full ? @js(__('Hide quoted text')) : @js(__('Show quoted text'))"></button>
                        @endif
                        @if ($item->html)
                            <button type="button" class="underline underline-offset-2" x-on:click="formatted = ! formatted" x-text="formatted ? @js(__('Show as plain text')) : @js(__('Show as formatted email'))"></button>
                        @endif
                    </div>
                </div>
            </flux:card>
        @endforeach
    </div>

    <flux:card>
        <form wire:submit="sendReply" class="space-y-4">
            <flux:textarea wire:model="reply" rows="6" :label="__('Reply to :name', ['name' => $message->displayName()])" :description="__('Sent from :address in the Homebridge email design, with a greeting added at the top and the team’s sign-off at the bottom. Leave a blank line between paragraphs.', ['address' => config('mail.from.address')])" />
            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary" icon="paper-airplane">{{ __('Send reply') }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>
