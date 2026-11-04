<?php

use App\Enums\SubmissionStatus;
use App\Livewire\Concerns\ManagesAmbassadorTasks;
use App\Livewire\Concerns\ReviewsAmbassadorSubmissions;
use App\Livewire\Forms\AmbassadorTaskForm;
use App\Models\AmbassadorSubmission;
use App\Models\AmbassadorTask;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Ambassador tasks')] class extends Component {
    use ManagesAmbassadorTasks, ReviewsAmbassadorSubmissions;

    public AmbassadorTaskForm $form;

    /** 'review' or 'tasks'. */
    #[Url]
    public string $tab = '';

    public function mount(): void
    {
        if (! in_array($this->tab, ['review', 'tasks'], true)) {
            $this->tab = AmbassadorSubmission::pending()->exists() ? 'review' : 'tasks';
        }
    }

    /**
     * @return Collection<int, AmbassadorSubmission>
     */
    #[Computed]
    public function pending(): Collection
    {
        return AmbassadorSubmission::pending()->with(['task', 'ambassador', 'files', 'reviewer'])->oldest('id')->get();
    }

    /**
     * @return Collection<int, AmbassadorTask>
     */
    #[Computed]
    public function tasks(): Collection
    {
        return AmbassadorTask::query()
            ->withCount([
                'submissions as pending_count' => fn ($query) => $query->where('status', SubmissionStatus::Pending),
                'submissions as approved_count' => fn ($query) => $query->where('status', SubmissionStatus::Approved),
            ])
            ->orderByRaw('archived_at is not null')
            ->latest('id')
            ->get();
    }

    protected function forgetSubmissions(): void
    {
        unset($this->pending, $this->tasks);
    }

    protected function afterTaskSaved(AmbassadorTask $task): void
    {
        unset($this->tasks);
        $this->tab = 'tasks';
    }

    protected function afterTaskDeleted(): void
    {
        unset($this->pending, $this->tasks);
    }
}; ?>

<div class="flex w-full flex-col gap-6 p-6 lg:p-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Ambassador tasks') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Post tasks for campus ambassadors, check their proof and give points.') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button icon="trophy" :href="route('staff.ambassadors.leaderboard')" wire:navigate>{{ __('Leaderboard') }}</flux:button>
            <flux:button variant="primary" icon="plus" wire:click="newTask" data-test="new-task">{{ __('New task') }}</flux:button>
        </div>
    </div>

    <flux:radio.group wire:model.live="tab" variant="segmented" class="self-start">
        <flux:radio value="review" :label="__('To review').($this->pending->count() ? ' ('.$this->pending->count().')' : '')" />
        <flux:radio value="tasks" :label="__('Tasks').' ('.$this->tasks->count().')'" />
    </flux:radio.group>

    @if ($tab === 'review')
        @forelse ($this->pending as $submission)
            @include('partials.ambassador-review-card', ['submission' => $submission])
        @empty
            <flux:callout icon="check-circle" color="green" :heading="__('All caught up')" :text="__('Nothing is waiting for review.')" />
        @endforelse
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Task') }}</flux:table.column>
                <flux:table.column>{{ __('For') }}</flux:table.column>
                <flux:table.column>{{ __('Points') }}</flux:table.column>
                <flux:table.column>{{ __('Due') }}</flux:table.column>
                <flux:table.column>{{ __('Approved') }}</flux:table.column>
                <flux:table.column>{{ __('To review') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->tasks as $task)
                    <flux:table.row :key="$task->id">
                        <flux:table.cell variant="strong">
                            <a href="{{ route('staff.ambassadors.tasks.show', $task) }}" wire:navigate class="hover:underline">{{ $task->title }}</a>
                            <div class="text-xs font-normal text-zinc-500">{{ $task->frequencyLabel() }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $task->audience->label() }}</flux:table.cell>
                        <flux:table.cell>{{ $task->points }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($task->isArchived())
                                <flux:badge size="sm">{{ __('Closed') }}</flux:badge>
                            @elseif ($task->due_at)
                                <span @class(['text-red-500' => $task->isPastDue()])>{{ $task->due_at->timezone('Asia/Karachi')->format('j M, g:i a') }}</span>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $task->approved_count }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($task->pending_count)
                                <flux:badge size="sm" color="amber">{{ $task->pending_count }}</flux:badge>
                            @else
                                0
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:dropdown>
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Actions')" />
                                <flux:menu>
                                    <flux:menu.item icon="eye" :href="route('staff.ambassadors.tasks.show', $task)" wire:navigate>{{ __('Open') }}</flux:menu.item>
                                    <flux:menu.item icon="pencil-square" wire:click="editTask({{ $task->id }})">{{ __('Edit') }}</flux:menu.item>
                                    <flux:menu.item :icon="$task->isArchived() ? 'arrow-path' : 'lock-closed'" wire:click="toggleArchive({{ $task->id }})">{{ $task->isArchived() ? __('Reopen') : __('Close task') }}</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="deleteTask({{ $task->id }})" data-test="delete-task-{{ $task->id }}"
                                        wire:confirm="{{ __('Delete this task? Everything handed in for it goes too, and ambassadors lose the points it gave them. To just stop new submissions, close it instead.') }}">{{ __('Delete') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center">{{ __('No tasks yet. Post the first one with “New task”.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif

    @include('partials.ambassador-task-form')
    @include('partials.ambassador-send-back')
</div>
