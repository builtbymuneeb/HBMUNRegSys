<?php

use App\Models\MailLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Email log')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, MailLog>
     */
    #[Computed]
    public function logs(): LengthAwarePaginator
    {
        return MailLog::query()
            ->when($this->status, fn ($query) => $query->where('status', $this->status))
            ->when(trim($this->search), fn ($query, string $search) => $query->where(fn ($query) => $query
                ->where('to', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate(50);
    }

    #[Computed]
    public function sentCount(): int
    {
        return MailLog::where('status', MailLog::SENT)->count();
    }

    #[Computed]
    public function failedCount(): int
    {
        return MailLog::where('status', MailLog::FAILED)->count();
    }

    /**
     * Emails still waiting for the queue worker.
     */
    #[Computed]
    public function waitingCount(): int
    {
        return DB::table('jobs')->count();
    }
}; ?>

<div class="flex w-full flex-col gap-6 p-6 lg:p-8">
    <div>
        <flux:heading size="xl" level="1">{{ __('Email log') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Every email the system sends. Emails sent from here don’t appear in the mailbox’s Sent folder, so check here instead.') }}</flux:text>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <flux:card class="flex flex-col gap-1">
            <flux:text>{{ __('Sent') }}</flux:text>
            <flux:heading size="xl">{{ $this->sentCount }}</flux:heading>
        </flux:card>
        <flux:card class="flex flex-col gap-1">
            <flux:text>{{ __('Failed') }}</flux:text>
            <flux:heading size="xl" @class(['text-red-400' => $this->failedCount > 0])>{{ $this->failedCount }}</flux:heading>
        </flux:card>
        <flux:card class="flex flex-col gap-1">
            <flux:text>{{ __('Waiting to send') }}</flux:text>
            <flux:heading size="xl">{{ $this->waitingCount }}</flux:heading>
            <flux:text class="text-xs">{{ __('Usually gone within a few seconds.') }}</flux:text>
        </flux:card>
    </div>

    <div class="grid gap-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search')" :placeholder="__('Email address or subject…')" />
        <flux:select wire:model.live="status" :label="__('Status')">
            <flux:select.option value="">{{ __('All') }}</flux:select.option>
            <flux:select.option value="sent">{{ __('Sent') }}</flux:select.option>
            <flux:select.option value="failed">{{ __('Failed') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$this->logs">
        <flux:table.columns>
            <flux:table.column>{{ __('When') }}</flux:table.column>
            <flux:table.column>{{ __('To') }}</flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->logs as $log)
                <flux:table.row :key="$log->id">
                    <flux:table.cell class="whitespace-nowrap">{{ $log->created_at?->timezone('Asia/Karachi')->format('j M, g:i a') }}</flux:table.cell>
                    <flux:table.cell class="font-medium text-zinc-800 dark:text-white">{{ $log->to }}</flux:table.cell>
                    <flux:table.cell>
                        <div>{{ $log->typeLabel() }}</div>
                        @if ($log->subject)
                            <div class="text-xs">{{ $log->subject }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($log->failed())
                            <flux:badge size="sm" color="red" inset="top bottom">{{ __('Failed') }}</flux:badge>
                            <div class="mt-1 max-w-80 text-xs whitespace-normal text-red-300">{{ $log->error }}</div>
                        @else
                            <flux:badge size="sm" color="green" inset="top bottom">{{ __('Sent') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="py-10 text-center">{{ __('No emails yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
