<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseMaterial;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CourseMaterialDownloadController extends Controller
{
    public function __invoke(Course $course, CourseMaterial $material): BinaryFileResponse
    {
        Gate::authorize('view', $course);
        abort_if($material->course_id !== $course->id, 404);
        Gate::authorize('download', $material);

        return response()->download(Storage::disk('local')->path($material->file_path), $material->original_name);
    }
}
