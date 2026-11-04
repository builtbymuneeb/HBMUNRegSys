<?php

namespace App\Livewire\Forms;

use App\Enums\TaskAudience;
use App\Models\AmbassadorTask;
use App\Models\AuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Form;

class AmbassadorTaskForm extends Form
{
    public const TIMEZONE = 'Asia/Karachi';

    public ?AmbassadorTask $task = null;

    public string $title = '';

    public string $description = '';

    public string $points = '10';

    public string $audience = 'everyone';

    /** @var array<int, int|string> Ambassador ids. */
    public array $assignees = [];

    /** 'once' or 'repeat'. */
    public string $frequency = 'once';

    /** For repeatable tasks: the most times it counts; blank for no limit. */
    public string $maxCompletions = '';

    public string $dueDate = '';

    public string $dueTime = '23:59';

    public bool $notify = true;

    public function create(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    public function edit(AmbassadorTask $task): void
    {
        $this->resetValidation();
        $this->task = $task;
        $this->title = $task->title;
        $this->description = (string) $task->description;
        $this->points = (string) $task->points;
        $this->audience = $task->audience->value;
        $this->assignees = $task->assignees()->pluck('campus_ambassadors.id')->map(fn ($id) => (int) $id)->values()->all();
        $this->frequency = $task->repeatable ? 'repeat' : 'once';
        $this->maxCompletions = (string) ($task->max_completions ?? '');
        $due = $task->due_at?->timezone(self::TIMEZONE);
        $this->dueDate = $due?->format('Y-m-d') ?? '';
        $this->dueTime = $due?->format('H:i') ?? '23:59';
        $this->notify = false;
    }

    public function save(): AmbassadorTask
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'points' => ['required', 'integer', 'min:1', 'max:100000'],
            'audience' => ['required', Rule::enum(TaskAudience::class)],
            'assignees' => [Rule::requiredIf($this->audience === TaskAudience::Selected->value), 'array'],
            'assignees.*' => ['integer', 'exists:campus_ambassadors,id'],
            'frequency' => ['required', 'in:once,repeat'],
            'maxCompletions' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'dueDate' => ['nullable', 'date_format:Y-m-d'],
            'dueTime' => ['nullable', 'date_format:H:i'],
        ], attributes: [
            'maxCompletions' => 'most times',
            'dueDate' => 'due date',
            'dueTime' => 'due time',
            'assignees' => 'ambassadors',
        ], messages: [
            'assignees.required' => 'Pick at least one ambassador.',
        ]);

        $repeatable = $validated['frequency'] === 'repeat';
        $task = $this->task ?? new AmbassadorTask(['created_by' => Auth::id()]);

        $task->fill([
            'title' => trim($validated['title']),
            'description' => filled($validated['description']) ? trim($validated['description']) : null,
            'points' => (int) $validated['points'],
            'audience' => $validated['audience'],
            'repeatable' => $repeatable,
            'max_completions' => $repeatable && filled($validated['maxCompletions']) ? (int) $validated['maxCompletions'] : null,
            'due_at' => filled($validated['dueDate'])
                ? Carbon::parse($validated['dueDate'].' '.($validated['dueTime'] ?: '23:59'), self::TIMEZONE)->utc()
                : null,
        ])->save();

        $task->assignees()->sync($validated['audience'] === TaskAudience::Selected->value ? array_map('intval', $validated['assignees']) : []);

        AuditLog::record($task->wasRecentlyCreated ? 'ambassador_task_created' : 'ambassador_task_updated', $task, Auth::user(), ['title' => $task->title, 'points' => $task->points]);

        $this->task = $task;

        return $task;
    }
}
