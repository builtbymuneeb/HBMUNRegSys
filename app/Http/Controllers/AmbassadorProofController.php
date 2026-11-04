<?php

namespace App\Http\Controllers;

use App\Models\AmbassadorSubmissionFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AmbassadorProofController extends Controller
{
    /**
     * Stream a proof file to staff who manage ambassadors, or to the ambassador who uploaded it.
     */
    public function __invoke(Request $request, AmbassadorSubmissionFile $file): StreamedResponse
    {
        $user = $request->user();
        $owner = $file->submission->campus_ambassador_id;

        abort_unless($user->can('manage-ambassadors') || $user->ambassador?->id === $owner, 403);

        $headers = $file->isImage() ? [] : ['Content-Disposition' => 'attachment; filename="'.addcslashes($file->original_name ?: basename($file->path), '"\\').'"'];

        return Storage::disk('local')->response($file->path, $file->original_name, $headers);
    }
}
