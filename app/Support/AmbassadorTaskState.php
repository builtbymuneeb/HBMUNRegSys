<?php

namespace App\Support;

use App\Enums\SubmissionStatus;
use App\Models\AmbassadorTask;

/**
 * Where one ambassador stands on a task, for their task lists. Expects the task's
 * submissions to be loaded for that ambassador only, newest first.
 */
class AmbassadorTaskState
{
    /**
     * @return array{task: AmbassadorTask, state: 'todo'|'waiting'|'done'|'closed', approved: int, pending: int, sent_back: bool}
     */
    public static function describe(AmbassadorTask $task): array
    {
        $approved = $task->submissions->where('status', SubmissionStatus::Approved)->count();
        $pending = $task->submissions->where('status', SubmissionStatus::Pending)->count();
        $limit = $task->completionLimit();
        $full = $limit !== null && $approved + $pending >= $limit;

        $state = match (true) {
            $task->isOpen() && ! $full => 'todo',
            $pending > 0 => 'waiting',
            $approved > 0 => 'done',
            default => 'closed',
        };

        return [
            'task' => $task,
            'state' => $state,
            'approved' => $approved,
            'pending' => $pending,
            'sent_back' => $task->submissions->first()?->status === SubmissionStatus::Rejected,
        ];
    }
}
