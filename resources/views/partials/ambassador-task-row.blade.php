{{-- One task in an ambassador's list. $row comes from the tasks page's describe(). --}}
@php($task = $row['task'])
<a href="{{ route('ambassador.tasks.show', $task) }}" wire:navigate class="hb-row hover:bg-zinc-50 dark:hover:bg-white/4" data-test="task-{{ $task->id }}">
    <span class="hb-icon-tile">
        @if ($row['state'] === 'done')
            <flux:icon.check-circle class="size-5 text-green-500" />
        @elseif ($row['state'] === 'waiting')
            <flux:icon.clock class="size-5 text-amber-500" />
        @else
            <flux:icon.clipboard-document-list class="size-5" />
        @endif
    </span>
    <span class="flex min-w-0 flex-1 flex-col">
        <span class="truncate text-[15px] font-medium text-zinc-800 dark:text-white">{{ $task->title }}</span>
        <span class="flex flex-wrap gap-x-2 text-xs text-zinc-500 dark:text-zinc-400">
            <span>{{ $task->frequencyLabel() }}</span>
            @if ($task->due_at)
                <span @class(['text-red-500' => $task->isPastDue()])>· {{ $task->isPastDue() ? __('Closed :date', ['date' => $task->due_at->timezone('Asia/Karachi')->format('j M')]) : __('Due :date', ['date' => $task->due_at->timezone('Asia/Karachi')->format('j M, g:i a')]) }}</span>
            @endif
            @if ($row['approved'] > 0 && $task->repeatable)
                <span>· {{ trans_choice('Done :count time|Done :count times', $row['approved']) }}</span>
            @endif
            @if ($row['pending'] > 0 && $row['state'] === 'todo')
                <span class="text-amber-500">· {{ __(':count waiting for review', ['count' => $row['pending']]) }}</span>
            @endif
        </span>
    </span>
    @if ($row['sent_back'] && $row['state'] === 'todo')
        <flux:badge size="sm" color="red">{{ __('Sent back') }}</flux:badge>
    @endif
    <flux:badge size="sm" color="purple">{{ __('+:points', ['points' => $task->points]) }}</flux:badge>
    <flux:icon.chevron-right variant="micro" class="text-zinc-400" />
</a>
