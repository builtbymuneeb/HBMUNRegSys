<?php

namespace App\Http\Controllers;

use App\Models\StudyGuide;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudyGuideDownloadController extends Controller
{
    /**
     * Download a private study guide file.
     */
    public function __invoke(StudyGuide $guide): StreamedResponse
    {
        Gate::authorize('view', $guide);

        return Storage::disk('local')->download($guide->file_path, $guide->file_name);
    }
}
