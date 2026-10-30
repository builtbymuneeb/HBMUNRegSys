<?php

use App\Actions\Inbox\FetchInbox;
use App\Models\InboxMessage;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Inbox')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $show = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * One row per person, newest conversation first, with the latest message and how many are unread.
     *
     * @return LengthAwarePaginator<int, InboxMessage>
     */
    #[Computed]
    public function conversations(): LengthAwarePaginator
    {
        $latest = InboxMessage::query()
            ->visibleTo(Auth::user())
            ->selectRaw('email, max(id) as latest_id, sum(case when direction = ? and read_at is null then 1 else 0 end) as unread_count', [InboxMessage::IN])
            ->when($this->show === 'ambassadors', fn ($query) => $query->whereNotNull('campus_ambassador_id'))
            ->when(trim($this->search), fn ($query, string $search) => $query->where(fn ($query) => $query
                ->where('email', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%")
                ->orWhere('body', 'like', "%{$search}%")))
            ->groupBy('email')
            ->when($this->show === 'unread', fn ($query) => $query->havingRaw('sum(case when direction = ? and read_at is null then 1 else 0 end) > 0', [InboxMessage::IN]))
            ->orderByRaw('max(received_at) desc')
            ->paginate(30);

        $messages = InboxMessage::with('ambassador:id,code,name')->findMany($latest->pluck('latest_id'))->keyBy('id');

        return $latest->through(function (InboxMessage $row) use ($messages) {
            $message = $messages[$row->latest_id];
            $message->unread_count = (int) $row->unread_count;

            return $message;
        });
    }

    #[Computed]
    public function unreadCount(): int
    {
        return InboxMessage::visibleTo(Auth::user())->unread()->count();
    }

    #[Computed]
    public function lastCheckedAt(): ?Carbon
    {
        return Cache::get(FetchInbox::LAST_FETCHED_KEY);
    }

    /**
     * Check the mailbox now instead of waiting for the next automatic check.
     */
    public function checkNow(FetchInbox $fetchInbox): void
    {
        Gate::authorize('manage-ambassadors');

        try {
            $new = $fetchInbox->fetch(FetchInbox::mailbox());
        } catch (Throwable $exception) {
            report($exception);
            Flux::toast(variant: 'danger', text: __('Couldn’t reach the mailbox. Try again in a minute.'));

            return;
        }

        unset($this->conversations, $this->unreadCount, $this->lastCheckedAt);
        Flux::toast(variant: 'success', text: trans_choice('No new messages.|:count new message.|:count new messages.', $new));
    }
}; ?>

<div class="flex w-full flex-col gap-6 p-6 lg:p-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Inbox') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('Replies and other mail sent to :address. Checked every 5 minutes.', ['address' => config('mail.from.address')]) }}
                @if ($this->lastCheckedAt)
                    {{ __('Last checked :time.', ['time' => $this->lastCheckedAt->diffForHumans()]) }}
                @endif
            </flux:text>
        </div>
        <flux:button icon="arrow-path" wire:click="checkNow" wire:loading.attr="disabled" wire:target="checkNow">
            <span wire:loading.remove wire:target="checkNow">{{ __('Check now') }}</span>
            <span wire:loading wire:target="checkNow">{{ __('Checking…') }}</span>
        </flux:button>
    </div>

    <div class="grid gap-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search')" :placeholder="__('Name, email, subject or text…')" />
        <flux:select wire:model.live="show" :label="__('Show')">
            <flux:select.option value="">{{ __('Everything') }}</flux:select.option>
            <flux:select.option value="unread">{{ __('Unread (:count)', ['count' => $this->unreadCount]) }}</flux:select.option>
            <flux:select.option value="ambassadors">{{ __('From campus ambassadors') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$this->conversations">
        <flux:table.columns>
            <flux:table.column>{{ __('From') }}</flux:table.column>
            <flux:table.column>{{ __('Latest message') }}</flux:table.column>
            <flux:table.column>{{ __('When') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->conversations as $message)
                <flux:table.row :key="$message->id">
                    <flux:table.cell>
                        <a href="{{ route('staff.inbox.show', $message) }}" wire:navigate class="block">
                            <div @class(['flex items-center gap-2 text-zinc-800 dark:text-white', 'font-semibold' => $message->unread_count > 0, 'font-medium' => $message->unread_count === 0])>
                                @if ($message->unread_count > 0)
                                    <span class="size-2 shrink-0 rounded-full bg-hb-lavender" aria-hidden="true"></span>
                                @endif
                                {{ $message->displayName() }}
                                @if ($message->unread_count > 1)
                                    <flux:badge size="sm" color="purple" inset="top bottom">{{ $message->unread_count }}</flux:badge>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 text-xs">
                                {{ $message->email }}
                                @if ($message->ambassador)
                                    <flux:badge size="sm" color="purple" inset="top bottom">{{ $message->ambassador->code }}</flux:badge>
                                @endif
                            </div>
                        </a>
                    </flux:table.cell>
                    <flux:table.cell class="max-w-md whitespace-normal">
                        <a href="{{ route('staff.inbox.show', $message) }}" wire:navigate class="block">
                            <div @class(['text-zinc-800 dark:text-white', 'font-semibold' => $message->unread_count > 0])>
                                @unless ($message->isIncoming())
                                    <span class="text-zinc-500">{{ __('You:') }}</span>
                                @endunless
                                {{ $message->subject ?: __('(no subject)') }}
                            </div>
                            <div class="line-clamp-1 text-xs">{{ $message->snippet() }}</div>
                        </a>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $message->received_at->timezone('Asia/Karachi')->format('j M, g:i a') }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3" class="py-10 text-center">{{ trim($search) || $show ? __('Nothing matches.') : __('No mail yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
