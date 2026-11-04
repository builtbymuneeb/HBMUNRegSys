<?php

namespace App\Actions\Ambassadors;

use App\Models\AmbassadorSubmission;
use App\Models\AmbassadorTask;
use App\Models\AuditLog;
use App\Models\CampusAmbassador;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * An ambassador marks a task as done and hands in their proof for staff to check.
 */
class SubmitTask
{
    /**
     * @param  list<UploadedFile>  $files
     *
     * @throws ValidationException when the task can't be submitted (any more).
     */
    public function handle(AmbassadorTask $task, CampusAmbassador $ambassador, ?string $note, ?string $link, array $files, User $by): AmbassadorSubmission
    {
        $paths = array_map(fn (UploadedFile $file) => [
            'path' => $file->store('ambassador-proof', 'local'),
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $file->getMimeType(),
        ], $files);

        try {
            return DB::transaction(function () use ($task, $ambassador, $note, $link, $paths, $by) {
                // Lock the ambassador's row so two quick taps can't both get past the limit.
                CampusAmbassador::whereKey($ambassador->id)->lockForUpdate()->first();

                if (! $task->canBeSubmittedBy($ambassador)) {
                    throw ValidationException::withMessages(['submission' => $task->isOpen()
                        ? __('You have already done this task as many times as it counts.')
                        : __('This task is closed.')]);
                }

                $submission = new AmbassadorSubmission([
                    'note' => filled($note) ? trim($note) : null,
                    'link' => filled($link) ? trim($link) : null,
                ]);
                $submission->task()->associate($task);
                $submission->ambassador()->associate($ambassador);
                $submission->save();

                $submission->files()->createMany($paths);

                AuditLog::record('ambassador_task_submitted', $submission, $by, ['task' => $task->title, 'code' => $ambassador->code]);

                return $submission;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete(array_column($paths, 'path'));

            throw $e;
        }
    }
}
