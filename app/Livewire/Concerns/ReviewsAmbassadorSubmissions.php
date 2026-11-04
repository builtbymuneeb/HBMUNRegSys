<?php

namespace App\Livewire\Concerns;

use App\Actions\Ambassadors\ReviewSubmission;
use App\Models\AmbassadorSubmission;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Award points for (approve) or send back ambassador submissions from a staff page. The page shows the
 * "send-back" modal from partials.ambassador-send-back and calls forgetSubmissions()
 * to refresh its lists.
 */
trait ReviewsAmbassadorSubmissions
{
    public ?int $sendingBackId = null;

    public string $sendBackReason = '';

    /**
     * Award points for a submission and mark it approved. With no points given, the task's points are used.
     */
    public function approveSubmission(int $submissionId, mixed $points = null): void
    {
        Gate::authorize('manage-ambassadors');

        if ($points !== null && (filter_var($points, FILTER_VALIDATE_INT) === false || (int) $points < 0 || (int) $points > 100000)) {
            Flux::toast(variant: 'danger', text: __('Enter a whole number of points, 0 or more.'));

            return;
        }

        $submission = AmbassadorSubmission::with('task', 'ambassador.user')->findOrFail($submissionId);

        try {
            app(ReviewSubmission::class)->approve($submission, Auth::user(), points: $points === null ? null : (int) $points);
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: $e->validator->errors()->first());

            return;
        }

        $this->forgetSubmissions();
        Flux::toast(variant: 'success', text: __('+:points for :name.', ['points' => $submission->points_awarded, 'name' => $submission->ambassador->name]));
    }

    public function startSendBack(int $submissionId): void
    {
        $this->sendingBackId = $submissionId;
        $this->reset('sendBackReason');
        $this->resetValidation('sendBackReason');
        Flux::modal('send-back')->show();
    }

    public function sendBack(ReviewSubmission $review): void
    {
        Gate::authorize('manage-ambassadors');

        $this->validate(['sendBackReason' => ['required', 'string', 'max:1000']], attributes: ['sendBackReason' => __('reason')]);

        $submission = AmbassadorSubmission::with('task', 'ambassador.user')->findOrFail($this->sendingBackId);
        $review->sendBack($submission, Auth::user(), $this->sendBackReason);

        $this->reset('sendingBackId', 'sendBackReason');
        $this->forgetSubmissions();
        Flux::modal('send-back')->close();
        Flux::toast(text: __('Sent back to :name. They can fix it and try again.', ['name' => $submission->ambassador->name]));
    }

    abstract protected function forgetSubmissions(): void;
}
