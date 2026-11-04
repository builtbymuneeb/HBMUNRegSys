<?php

use App\Models\AmbassadorTask;
use App\Models\CampusAmbassador;
use App\Support\AmbassadorTaskState;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('My tasks')] class extends Component {
    #[Computed]
    public function ambassador(): CampusAmbassador
    {
        return Auth::user()->ambassador;
    }

    /**
     * Every task they can see, with where they stand on it, grouped for the page.
     *
     * @return Collection<string, Collection<int, array{task: AmbassadorTask, state: string, approved: int, pending: int, sent_back: bool}>>
     */
    #[Computed]
    public function groups(): Collection
    {
        $ambassador = $this->ambassador;

        return AmbassadorTask::query()
            ->visibleTo($ambassador)
            ->with(['submissions' => fn ($query) => $query->where('campus_ambassador_id', $ambassador->id)->latest('id')])
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->latest('id')
            ->get()
            ->map(fn (AmbassadorTask $task) => AmbassadorTaskState::describe($task))
            ->reject(fn (array $row) => $row['task']->isArchived() && $row['approved'] === 0 && $row['pending'] === 0)
            ->groupBy('state');
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-6 lg:gap-6 lg:p-8">
    <flux:heading size="xl" level="1">{{ __('My tasks') }}</flux:heading>

    @foreach (['todo' => __('To do'), 'waiting' => __('Waiting for review'), 'done' => __('Done'), 'closed' => __('Closed')] as $state => $heading)
        @if ($rows = $this->groups->get($state))
            <section class="flex flex-col gap-2" data-test="tasks-{{ $state }}">
                <flux:heading size="lg" class="px-1">{{ $heading }} <span class="text-zinc-400">· {{ $rows->count() }}</span></flux:heading>
                <div class="hb-list">
                    @foreach ($rows as $row)
                        @include('partials.ambassador-task-row', ['row' => $row])
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach

    @if ($this->groups->isEmpty())
        <flux:callout icon="clipboard-document-list" :heading="__('No tasks yet')" :text="__('When the team posts a task for you, it will appear here.')" />
    @endif
</div>
