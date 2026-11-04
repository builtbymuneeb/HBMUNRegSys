{{-- A campus ambassador's home page: their points, referral link and the tasks waiting for them. --}}
@php($openTasks = \App\Models\AmbassadorTask::visibleTo($ambassador)
    ->with(['submissions' => fn ($query) => $query->where('campus_ambassador_id', $ambassador->id)->latest('id')])
    ->orderByRaw('due_at is null')->orderBy('due_at')->latest('id')->get()
    ->filter(fn ($task) => $task->isOpen())
    ->map(fn ($task) => \App\Support\AmbassadorTaskState::describe($task))
    ->where('state', 'todo'))

<div class="grid gap-4 lg:gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
    <div class="flex flex-col gap-4 lg:gap-6">
        @include('partials.ambassador-summary', ['ambassador' => $ambassador])
    </div>
    <section class="flex flex-col gap-2">
        <div class="flex items-center justify-between px-1">
            <flux:heading size="lg">{{ __('To do') }} <span class="text-zinc-400">· {{ $openTasks->count() }}</span></flux:heading>
            <flux:link :href="route('ambassador.tasks')" wire:navigate class="text-sm">{{ __('All tasks') }}</flux:link>
        </div>
        @if ($openTasks->isEmpty())
            <flux:callout icon="sparkles" :text="__('Nothing to do right now. New tasks from the team will show up here.')" />
        @else
            <div class="hb-list">
                @foreach ($openTasks->take(6) as $row)
                    @include('partials.ambassador-task-row', ['row' => $row])
                @endforeach
            </div>
        @endif
    </section>
</div>
