<?php

namespace App\Livewire\Concerns;

use App\Livewire\Forms\AmbassadorTaskForm;
use App\Models\AmbassadorTask;
use App\Models\AuditLog;
use App\Models\CampusAmbassador;
use App\Notifications\AmbassadorTaskPosted;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Computed;

/**
 * Create, edit, archive and delete ambassador tasks from a staff page that
 * includes partials.ambassador-task-form and declares an AmbassadorTaskForm $form.
 */
trait ManagesAmbassadorTasks
{
    /**
     * @return Collection<int, CampusAmbassador>
     */
    #[Computed]
    public function allAmbassadors(): Collection
    {
        return CampusAmbassador::orderBy('name')->get(['id', 'name', 'code']);
    }

    public function newTask(): void
    {
        $this->form->create();
        Flux::modal('task-form')->show();
    }

    public function editTask(int $taskId): void
    {
        $this->form->edit(AmbassadorTask::findOrFail($taskId));
        Flux::modal('task-form')->show();
    }

    public function saveTask(): void
    {
        Gate::authorize('manage-ambassadors');

        $isNew = $this->form->task === null;
        $notify = $isNew && $this->form->notify;
        $task = $this->form->save();

        $emailed = 0;
        if ($notify) {
            $task->audienceMembers()->whereNotNull('user_id')->with('user')->get()->each(function (CampusAmbassador $ambassador) use ($task, &$emailed) {
                Notification::route('mail', $ambassador->user->email)->notify(new AmbassadorTaskPosted($task, $ambassador));
                $emailed++;
            });
        }

        Flux::modal('task-form')->close();
        Flux::toast(variant: 'success', text: $isNew
            ? ($notify ? trans_choice('Task posted. :count ambassador emailed.|Task posted. :count ambassadors emailed.', $emailed) : __('Task posted.'))
            : __('Task saved.'));

        $this->afterTaskSaved($task);
    }

    public function toggleArchive(int $taskId): void
    {
        Gate::authorize('manage-ambassadors');

        $task = AmbassadorTask::findOrFail($taskId);
        $task->update(['archived_at' => $task->isArchived() ? null : now()]);
        AuditLog::record($task->isArchived() ? 'ambassador_task_archived' : 'ambassador_task_reopened', $task, Auth::user(), ['title' => $task->title]);

        Flux::toast(text: $task->isArchived() ? __('Task closed. Ambassadors can no longer hand it in; points already given stay.') : __('Task reopened.'));
        $this->afterTaskSaved($task);
    }

    /**
     * Delete a task with everything handed in for it (and the points it gave).
     */
    public function deleteTask(int $taskId): void
    {
        Gate::authorize('manage-ambassadors');

        $task = AmbassadorTask::withCount(['submissions as approved_count' => fn ($query) => $query->where('status', 'approved')])->findOrFail($taskId);
        AuditLog::record('ambassador_task_deleted', $task, Auth::user(), ['title' => $task->title, 'approved' => $task->approved_count]);
        $task->delete();

        Flux::toast(text: __('Task deleted, with the points it gave.'));
        $this->afterTaskDeleted();
    }

    protected function afterTaskSaved(AmbassadorTask $task): void {}

    protected function afterTaskDeleted(): void {}
}
