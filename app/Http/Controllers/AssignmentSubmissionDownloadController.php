<?php

namespace App\Http\Controllers;

use App\Models\AssignmentSubmission;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentSubmissionDownloadController extends Controller
{
    public function __invoke(Request $request, Course $course, AssignmentSubmission $submission): StreamedResponse
    {
        Gate::authorize('view', $course);

        abort_unless($submission->assignment && $submission->assignment->course_id === $course->id, 404);

        $user = $request->user();
        $isAuthor = $submission->user_id === $user->id;
        $canManage = $user->can('update', $submission->assignment);

        abort_unless($isAuthor || $canManage, 403);

        if (! $submission->file_path || ! Storage::disk('local')->exists($submission->file_path)) {
            abort(404, __('Submission file not found on disk.'));
        }

        $extension = pathinfo($submission->file_path, PATHINFO_EXTENSION);
        $studentName = str_replace(' ', '_', $submission->student?->name ?? 'student');
        $assignmentTitle = str_replace(' ', '_', $submission->assignment->title);
        $downloadFilename = "{$studentName}_{$assignmentTitle}_submission.{$extension}";

        return Storage::disk('local')->download($submission->file_path, $downloadFilename);
    }
}

