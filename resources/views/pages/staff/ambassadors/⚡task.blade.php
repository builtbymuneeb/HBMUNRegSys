<?php

use App\Enums\SubmissionStatus;
use App\Livewire\Concerns\ManagesAmbassadorTasks;
use App\Livewire\Concerns\ReviewsAmbassadorSubmissions;
use App\Livewire\Forms\AmbassadorTaskForm;
use App\Models\AmbassadorSubmission;
use App\Models\AmbassadorTask;
use App\Models\AuditLog;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Ambassador task')] class extends Component {
    use ManagesAmbassadorTasks, ReviewsAmbassadorSubmissions;

    public AmbassadorTask $task;

    public AmbassadorTaskForm $form;

    /** '' for all, or a SubmissionStatus value. */
    #[Url]
    public string $status = '';

    /**
     * @return Collection<int, AmbassadorSubmission>
     */
    #[Computed]
    public function submissions(): Collection
    {
        return $this->task->submissions()
            ->with(['ambassador', 'files', 'reviewer', 'task'])
            ->when(SubmissionStatus::tryFrom($this->status), fn ($query, SubmissionStatus $status) => $query->where('status', $status))
            ->orderByRaw("case status when 'pending' then 0 else 1 end")
            ->latest('id')
            ->get();
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        return $this->task->submissions()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all();
    }

    #[Computed]
    public function audienceSize(): int
    {
        return $this->task->audienceMembers()->count();
    }

    public function editThis(): void
    {
        $this->editTask($this->task->id);
    }

    public function delete(): void
    {
        Gate::authorize('manage-ambassadors');

        AuditLog::record('ambassador_task_deleted', $this->task, Auth::user(), ['title' => $this->task->title, 'approved' => $this->counts['approved'] ?? 0]);
        $this->task->delete();

        Flux::toast(text: __('Task deleted, with the points it gave.'));
        $this->redirectRoute('staff.ambassadors.tasks', ['tab' => 'tasks'], navigate: true);
    }

    protected function forgetSubmissions(): void
    {
        unset($this->submissions, $this->counts);
    }

    protected function afterTaskSaved(AmbassadorTask $task): void
    {
        $this->task = $task->fresh();
        unset($this->submissions, $this->counts, $this->audienceSize);
    }
}; ?>

<div class="flex w-full flex-col gap-6 p-6 lg:p-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <flux:link :href="route('staff.ambassadors.tasks', ['tab' => 'tasks'])" wire:navigate class="text-sm">← {{ __('Ambassador tasks') }}</flux:link>
            <flux:heading size="xl" level="1" class="mt-2">{{ $task->title }}</flux:heading>
            <div class="mt-2 flex flex-wrap gap-2">
                <flux:badge color="purple" icon="star">{{ trans_choice(':count point|:count points', $task->points) }}</flux:badge>
                <flux:badge icon="arrow-path">{{ $task->frequencyLabel() }}</flux:badge>
                <flux:badge icon="users">{{ $task->audience->label() }} · {{ $this->audienceSize }}</flux:badge>
                @if ($task->isArchived())
                    <flux:badge color="zinc" icon="lock-closed">{{ __('Closed') }}</flux:badge>
                @elseif ($task->due_at)
                    <flux:badge :color="$task->isPastDue() ? 'red' : 'zinc'" icon="calendar">{{ __('Due :date', ['date' => $task->due_at->timezone('Asia/Karachi')->format('j M, g:i a')]) }}</flux:badge>
                @endif
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button icon="pencil-square" wire:click="editThis">{{ __('Edit') }}</flux:button>
            <flux:button :icon="$task->isArchived() ? 'arrow-path' : 'lock-closed'" wire:click="toggleArchive({{ $task->id }})">{{ $task->isArchived() ? __('Reopen') : __('Close task') }}</flux:button>
            <flux:button icon="trash" variant="danger" wire:click="delete" data-test="delete-task"
                wire:confirm="{{ __('Delete this task? Everything handed in for it goes too, and ambassadors lose the points it gave them. To just stop new submissions, close it instead.') }}">{{ __('Delete task') }}</flux:button>
        </div>
    </div>

    @if (filled($task->description))
        <flux:card><div class="text-sm break-words whitespace-pre-line text-zinc-700 dark:text-zinc-200">{{ $task->description }}</div></flux:card>
    @endif

    <flux:radio.group wire:model.live="status" variant="segmented" size="sm" class="self-start">
        <flux:radio value="" :label="__('All').' ('.array_sum($this->counts).')'" />
        @foreach (SubmissionStatus::cases() as $option)
            <flux:radio :value="$option->value" :label="$option->label().' ('.($this->counts[$option->value] ?? 0).')'" />
        @endforeach
    </flux:radio.group>

    @forelse ($this->submissions as $submission)
        @include('partials.ambassador-review-card', ['submission' => $submission, 'showTask' => false])
    @empty
        <flux:callout icon="inbox" :text="__('Nothing handed in yet.')" />
    @endforelse

    @include('partials.ambassador-task-form')
    @include('partials.ambassador-send-back')
</div>
