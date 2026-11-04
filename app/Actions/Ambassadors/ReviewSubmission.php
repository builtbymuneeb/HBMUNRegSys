<?php

namespace App\Actions\Ambassadors;

use App\Enums\SubmissionStatus;
use App\Models\AmbassadorSubmission;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AmbassadorSubmissionReviewed;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Staff award points for an ambassador's proof (any amount they judge fair; the task's points
 * are only the suggested figure) or send it back with a reason. Awarding marks it approved.
 * A decision can be changed later; the points follow the latest one.
 */
class ReviewSubmission
{
    /**
     * @throws ValidationException when they already have all the approvals the task allows.
     */
    public function approve(AmbassadorSubmission $submission, User $reviewer, ?string $note = null, ?int $points = null): void
    {
        $points ??= $submission->task->points;

        if ($points < 0) {
            throw ValidationException::withMessages(['submission' => __('Points can’t be negative.')]);
        }

        $limit = $submission->task->completionLimit();
        $approvedElsewhere = AmbassadorSubmission::approved()
            ->where('ambassador_task_id', $submission->ambassador_task_id)
            ->where('campus_ambassador_id', $submission->campus_ambassador_id)
            ->whereKeyNot($submission->id)
            ->count();

        if ($limit !== null && $approvedElsewhere >= $limit) {
            throw ValidationException::withMessages(['submission' => __(':name already has the most approvals this task allows (:limit).', ['name' => $submission->ambassador->name, 'limit' => $limit])]);
        }

        $this->decide($submission, $reviewer, SubmissionStatus::Approved, $points, $note);
    }

    public function sendBack(AmbassadorSubmission $submission, User $reviewer, string $reason): void
    {
        $this->decide($submission, $reviewer, SubmissionStatus::Rejected, null, $reason);
    }

    private function decide(AmbassadorSubmission $submission, User $reviewer, SubmissionStatus $status, ?int $points, ?string $note): void
    {
        $submission->update([
            'status' => $status,
            'points_awarded' => $points,
            'review_note' => filled($note) ? trim($note) : null,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        AuditLog::record($status === SubmissionStatus::Approved ? 'ambassador_submission_approved' : 'ambassador_submission_sent_back', $submission, $reviewer, [
            'task' => $submission->task->title,
            'code' => $submission->ambassador->code,
            'points' => $points,
        ]);

        $email = $submission->ambassador->user->email ?? $submission->ambassador->email;

        if (filled($email)) {
            Notification::route('mail', $email)->notify(new AmbassadorSubmissionReviewed($submission));
        }
    }
}
