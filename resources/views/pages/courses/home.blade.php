<?php

use App\Actions\Courses\EnrollStudentInCourseByInstructor;
use App\Actions\Courses\GenerateEnrollmentKey;
use App\Enums\RoleName;
use App\Livewire\Concerns\HasToastFeedback;
use App\Models\AssessmentLog;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\QuizResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use League\Flysystem\UnableToRetrieveMetadata;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Course Home')] class extends Component
{
    use HasToastFeedback;
    use WithFileUploads;

    public Course $course;

    public string $syllabus_content = '';

    public bool $is_syllabus_collapsed = true;

    public string $module_title = '';

    public ?int $module_week_number = null;

    public string $module_description = '';

    public int $module_position = 0;

    public bool $is_modules_collapsed = true;

    public bool $is_enrollment_management_collapsed = true;

    public string $material_title = '';

    public string $material_description = '';

    public ?int $material_module_id = null;

    /** @var mixed */
    public $material_file = null;

    public string $syllabus_file_title = '';

    public string $syllabus_file_description = '';

    /** @var mixed */
    public $syllabus_file = null;

    public string $add_student_email = '';

    public bool $is_assignments_collapsed = true;

    public string $assignment_title = '';

    public string $assignment_description = '';

    public string $assignment_due_date = '';

    public string $assignment_max_score = '100';

    public ?int $submission_assignment_id = null;

    /** @var array<int, string|int|float|null> */
    public array $submissionScores = [];

    /** @var array<int, string|null> */
    public array $submissionFeedbacks = [];

    /** @var mixed */
    public $submission_file = null;

    public bool $is_quizzes_collapsed = true;

    /** @var array<int, string|int|float|null> */
    public array $quizResponseScores = [];

    public function mount(Course $course): void
    {
        Gate::authorize('view', $course);

        if (! auth()->user()->can('courses.view')) {
            abort(403);
        }

        $this->course = $course->load(['department', 'facultyProfile.user', 'enrollments']);
        $this->syllabus_content = $this->course->syllabus_content ?? '';
        $this->pullToastFromSession();
    }

    public function refreshCourse(): void
    {
        $this->course->refresh()->load(['department', 'facultyProfile.user', 'enrollments']);
    }

    #[Computed]
    public function isEnrolled(): bool
    {
        return $this->course->enrollments->contains('user_id', auth()->id());
    }

    #[Computed]
    public function isManager(): bool
    {
        return auth()->user()->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value]);

    }

    #[Computed]
    public function isInstructor(): bool
    {
        return $this->course->faculty_profile_id && $this->course->facultyProfile?->user_id === auth()->id();
    }

    #[Computed]
    public function canAccessLearningContent(): bool
    {
        return $this->isManager || $this->isInstructor || $this->isEnrolled;
    }

    #[Computed]
    public function canManageCourse(): bool
    {
        return $this->isManager || $this->isInstructor;
    }

    protected function ensureCanManageCourseContent(): void
    {
        if (! auth()->user()->can('courses.manage-content')) {
            abort(403);
        }
    }

    protected function ensureCanManageEnrollments(): void
    {
        if (! auth()->user()->can('enrollments.manage')) {
            abort(403);
        }
    }

    protected function ensureCanManageAssignments(): void
    {
        if (! auth()->user()->can('assignments.manage')) {
            abort(403);
        }
    }

    protected function ensureCanManageQuizzes(): void
    {
        if (! auth()->user()->can('quizzes.manage')) {
            abort(403);
        }
    }

    #[Computed]
    public function modules()
    {
        return $this->course->modules()
            ->with(['materials' => fn ($query) => $query->where('is_syllabus', false)->latest()])
            ->get();
    }

    #[Computed]
    public function syllabusFiles()
    {
        return $this->course->materials()
            ->where('is_syllabus', true)
            ->latest()
            ->get();
    }

    #[Computed]
    public function assignments()
    {
        return $this->course->assignments()
            ->with(['submissions.student'])
            ->withCount('submissions')
            ->orderBy('due_date')
            ->get();
    }

    #[Computed]
    public function myAssignmentSubmissions()
    {
        return AssignmentSubmission::query()
            ->where('user_id', auth()->id())
            ->whereHas('assignment', fn ($query) => $query->where('course_id', $this->course->id))
            ->get()
            ->keyBy('assignment_id');
    }

    #[Computed]
    public function quizzes()
    {
        return $this->course->quizzes()
            ->with(['responses.student'])
            ->withCount('responses')
            ->orderBy('display_order')
            ->get();
    }

    #[Computed]
    public function myQuizResponses()
    {
        return QuizResponse::query()
            ->where('user_id', auth()->id())
            ->whereHas('quiz', fn ($query) => $query->where('course_id', $this->course->id))
            ->get()
            ->keyBy('quiz_id');
    }

    public function saveSyllabus(): void
    {
        Gate::authorize('update', $this->course);
        $this->ensureCanManageCourseContent();

        $validated = $this->validate([
            'syllabus_content' => ['nullable', 'string', 'max:20000'],
        ]);

        $this->course->update([
            'syllabus_content' => $validated['syllabus_content'] !== '' ? $validated['syllabus_content'] : null,
            'syllabus_updated_at' => now(),
        ]);

        $this->refreshCourse();
        $this->successToast(__('Syllabus updated successfully.'));
    }

    public function toggleSyllabusCollapsed(): void
    {
        $this->is_syllabus_collapsed = ! $this->is_syllabus_collapsed;
    }

    public function createModule(): void
    {
        Gate::authorize('update', $this->course);
        $this->ensureCanManageCourseContent();

        $validated = $this->validate([
            'module_title' => ['required', 'string', 'max:150'],
            'module_week_number' => ['nullable', 'integer', 'between:1,52'],
            'module_description' => ['nullable', 'string', 'max:2000'],
            'module_position' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        CourseModule::query()->create([
            'course_id' => $this->course->id,
            'title' => $validated['module_title'],
            'week_number' => $validated['module_week_number'],
            'description' => $validated['module_description'] !== '' ? $validated['module_description'] : null,
            'position' => $validated['module_position'] ?? 0,
        ]);

        $this->reset(['module_title', 'module_week_number', 'module_description', 'module_position']);
        $this->refreshCourse();
        $this->successToast(__('Module created successfully.'));
    }

    public function toggleModulesCollapsed(): void
    {
        $this->is_modules_collapsed = ! $this->is_modules_collapsed;
    }

    public function toggleEnrollmentManagementCollapsed(): void
    {
        $this->is_enrollment_management_collapsed = ! $this->is_enrollment_management_collapsed;
    }

    public function toggleAssignmentsCollapsed(): void
    {
        $this->is_assignments_collapsed = ! $this->is_assignments_collapsed;
    }

    public function createAssignment(): void
    {
        Gate::authorize('update', $this->course);
        $this->ensureCanManageAssignments();

        $validated = $this->validate([
            'assignment_title' => ['required', 'string', 'max:150'],
            'assignment_description' => ['required', 'string', 'max:4000'],
            'assignment_due_date' => ['required', 'date'],
            'assignment_max_score' => ['required', 'numeric', 'min:1', 'max:999.99'],
        ]);

        $nextDisplayOrder = ((int) $this->course->assignments()->max('display_order')) + 1;

        Assignment::query()->create([
            'course_id' => $this->course->id,
            'title' => $validated['assignment_title'],
            'description' => $validated['assignment_description'],
            'due_date' => $validated['assignment_due_date'],
            'max_score' => $validated['assignment_max_score'],
            'display_order' => $nextDisplayOrder,
        ]);

        $this->reset(['assignment_title', 'assignment_description', 'assignment_due_date', 'assignment_max_score']);
        $this->assignment_max_score = '100';
        $this->successToast(__('Assignment created successfully.'));
    }

    public function deleteAssignment(int $assignmentId): void
    {
        $assignment = Assignment::query()
            ->where('course_id', $this->course->id)
            ->with('submissions')
            ->findOrFail($assignmentId);

        Gate::authorize('delete', $assignment);
        $this->ensureCanManageAssignments();

        $affectedStudentIds = $assignment->submissions
            ->pluck('user_id')
            ->unique()
            ->map(fn ($id): int => (int) $id)
            ->values();

        $assessmentName = 'Assignment: '.$assignment->title;

        foreach ($assignment->submissions as $submission) {
            if ($submission->file_path && Storage::disk('local')->exists($submission->file_path)) {
                Storage::disk('local')->delete($submission->file_path);
            }
        }

        $assignment->delete();

        AssessmentLog::query()
            ->where('course_id', $this->course->id)
            ->where('assessment_type', 'assignment')
            ->where('assessment_name', $assessmentName)
            ->delete();

        foreach ($affectedStudentIds as $studentId) {
            $this->syncAssignmentAggregateGradeForStudent($studentId);
        }

        $this->successToast(__('Assignment deleted.'));
    }

    public function submitAssignment(): void
    {
        if (! $this->isEnrolled || ! auth()->user()->studentProfile()->exists()) {
            abort(403);
        }

        $validated = $this->validate([
            'submission_assignment_id' => ['required', 'integer'],
            'submission_file' => ['required', 'file', 'max:10240'],
        ]);

        $assignment = Assignment::query()
            ->where('course_id', $this->course->id)
            ->findOrFail((int) $validated['submission_assignment_id']);

        Gate::authorize('view', $assignment);

        $existingSubmission = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('user_id', auth()->id())
            ->first();

        $path = $this->submission_file->store(
            'assignment-submissions/'.$this->course->id.'/'.$assignment->id,
            'local',
        );

        if ($existingSubmission?->file_path && Storage::disk('local')->exists($existingSubmission->file_path)) {
            Storage::disk('local')->delete($existingSubmission->file_path);
        }

        AssignmentSubmission::query()->updateOrCreate(
            [
                'assignment_id' => $assignment->id,
                'user_id' => auth()->id(),
            ],
            [
                'file_path' => $path,
                'submission_date' => now(),
                'is_late' => now()->greaterThan($assignment->due_date),
            ],
        );

        $this->reset(['submission_assignment_id', 'submission_file']);
        $this->successToast(__('Assignment submitted successfully.'));
    }

    public function gradeAssignmentSubmission(int $submissionId): void
    {
        $submission = AssignmentSubmission::query()
            ->whereHas('assignment', fn ($query) => $query->where('course_id', $this->course->id))
            ->with('assignment')
            ->findOrFail($submissionId);

        Gate::authorize('update', $submission->assignment);
        $this->ensureCanManageAssignments();

        $scoreInput = $this->submissionScores[$submissionId] ?? $submission->score;
        $feedbackInput = $this->submissionFeedbacks[$submissionId] ?? $submission->feedback;

        $validated = validator([
            'score' => $scoreInput,
            'feedback' => $feedbackInput,
        ], [
            'score' => ['required', 'numeric', 'min:0', 'max:'.$submission->assignment->max_score],
            'feedback' => ['nullable', 'string', 'max:4000'],
        ])->validate();

        DB::transaction(function () use ($submission, $validated, $submissionId): void {
            $submission->update([
                'score' => round((float) $validated['score'], 2),
                'feedback' => $validated['feedback'] !== '' ? $validated['feedback'] : null,
                'graded_by' => auth()->id(),
                'graded_at' => now(),
            ]);

            AssessmentLog::query()->updateOrCreate(
                [
                    'user_id' => $submission->user_id,
                    'course_id' => $this->course->id,
                    'assessment_type' => 'assignment',
                    'assessment_name' => 'Assignment: '.$submission->assignment->title,
                ],
                [
                    'score' => $submission->score,
                    'max_score' => $submission->assignment->max_score,
                    'assessed_by' => auth()->id(),
                    'assessed_at' => now(),
                    'notes' => $submission->feedback,
                    'created_at' => now(),
                ],
            );

            $this->syncAssignmentAggregateGradeForStudent((int) $submission->user_id);

            $this->submissionScores[$submissionId] = (string) $submission->score;
            $this->submissionFeedbacks[$submissionId] = $submission->feedback;
        });

        $this->successToast(__('Assignment submission graded successfully.'));
    }

    protected function syncAssignmentAggregateGradeForStudent(int $studentId): void
    {
        $submissions = AssignmentSubmission::query()
            ->where('user_id', $studentId)
            ->whereNotNull('score')
            ->whereHas('assignment', fn ($query) => $query->where('course_id', $this->course->id))
            ->with('assignment:id,max_score')
            ->get();

        $assignmentAggregate = null;

        if ($submissions->isNotEmpty()) {
            $normalizedScores = $submissions
                ->filter(fn (AssignmentSubmission $submission): bool => (float) $submission->assignment->max_score > 0)
                ->map(fn (AssignmentSubmission $submission): float => ((float) $submission->score / (float) $submission->assignment->max_score) * 100)
                ->values();

            if ($normalizedScores->isNotEmpty()) {
                $assignmentAggregate = round((float) $normalizedScores->avg(), 2);
            }
        }

        $grade = Grade::query()->firstOrCreate([
            'user_id' => $studentId,
            'course_id' => $this->course->id,
        ]);

        $grade->assignment_score = $assignmentAggregate;

        if ($grade->isDirty(['assignment_score'])) {
            $grade->is_approved_by_admin = false;
            $grade->approved_by = null;
            $grade->approved_at = null;
        }

        $finalGrade =
            (($grade->ca_score ?? 0) * 0.25) +
            (($grade->test_score ?? 0) * 0.15) +
            (($grade->quiz_score ?? 0) * 0.10) +
            (($grade->assignment_score ?? 0) * 0.10) +
            (($grade->project_score ?? 0) * 0.10) +
            (($grade->exam_score ?? 0) * 0.30);

        $grade->final_grade = round($finalGrade, 2);
        $grade->grade_letter = match (true) {
            $finalGrade >= 80 => 'A',
            $finalGrade >= 70 => 'B',
            $finalGrade >= 60 => 'C',
            $finalGrade >= 50 => 'D',
            default => 'F',
        };

        $grade->save();
    }

    public function toggleQuizzesCollapsed(): void
    {
        $this->is_quizzes_collapsed = ! $this->is_quizzes_collapsed;
    }

    public function gradeQuizResponse(int $responseId): void
    {
        $response = QuizResponse::query()
            ->whereHas('quiz', fn ($query) => $query->where('course_id', $this->course->id))
            ->with('quiz')
            ->findOrFail($responseId);

        // Verify the student is enrolled in the course
        $studentEnrolled = $this->course->enrollments()
            ->where('user_id', $response->user_id)
            ->whereIn('status', ['active', 'enrolled'])
            ->exists();

        if (! $studentEnrolled) {
            abort(403);
        }

        Gate::authorize('update', $response->quiz);
        $this->ensureCanManageQuizzes();

        $scoreInput = $this->quizResponseScores[$responseId] ?? $response->score;

        $validated = validator([
            'score' => $scoreInput,
        ], [
            'score' => ['required', 'numeric', 'min:0', 'max:'.$response->quiz->max_score],
        ])->validate();

        DB::transaction(function () use ($response, $validated, $responseId): void {
            $response->update([
                'score' => round((float) $validated['score'], 2),
            ]);

            AssessmentLog::query()->updateOrCreate(
                [
                    'user_id' => $response->user_id,
                    'course_id' => $this->course->id,
                    'assessment_type' => 'quiz',
                    'assessment_name' => 'Quiz: '.$response->quiz->title,
                ],
                [
                    'score' => $response->score,
                    'max_score' => $response->quiz->max_score,
                    'assessed_by' => auth()->id(),
                    'assessed_at' => now(),
                    'notes' => null,
                    'created_at' => now(),
                ],
            );

            $this->syncQuizAggregateGradeForStudent((int) $response->user_id);

            $this->quizResponseScores[$responseId] = (string) $response->score;
        });

        $this->successToast(__('Quiz scored successfully.'));
    }

    protected function syncQuizAggregateGradeForStudent(int $studentId): void
    {
        $responses = QuizResponse::query()
            ->where('user_id', $studentId)
            ->whereNotNull('score')
            ->whereHas('quiz', fn ($query) => $query->where('course_id', $this->course->id))
            ->with('quiz:id,max_score')
            ->get();

        $quizAggregate = null;

        if ($responses->isNotEmpty()) {
            $normalizedScores = $responses
                ->filter(fn (QuizResponse $response): bool => (float) $response->quiz->max_score > 0)
                ->map(fn (QuizResponse $response): float => ((float) $response->score / (float) $response->quiz->max_score) * 100)
                ->values();

            if ($normalizedScores->isNotEmpty()) {
                $quizAggregate = round((float) $normalizedScores->avg(), 2);
            }
        }

        $grade = Grade::query()->firstOrCreate([
            'user_id' => $studentId,
            'course_id' => $this->course->id,
        ]);

        $grade->quiz_score = $quizAggregate;

        if ($grade->isDirty(['quiz_score'])) {
            $grade->is_approved_by_admin = false;
            $grade->approved_by = null;
            $grade->approved_at = null;
        }

        $finalGrade =
            (($grade->ca_score ?? 0) * 0.25) +
            (($grade->test_score ?? 0) * 0.15) +
            (($grade->quiz_score ?? 0) * 0.10) +
            (($grade->assignment_score ?? 0) * 0.10) +
            (($grade->project_score ?? 0) * 0.10) +
            (($grade->exam_score ?? 0) * 0.30);

        $grade->final_grade = round($finalGrade, 2);
        $grade->grade_letter = match (true) {
            $finalGrade >= 80 => 'A',
            $finalGrade >= 70 => 'B',
            $finalGrade >= 60 => 'C',
            $finalGrade >= 50 => 'D',
            default => 'F',
        };

        $grade->save();
    }

    public function uploadSyllabusFile(): void
    {
        Gate::authorize('update', $this->course);
        $this->ensureCanManageCourseContent();

        try {
            $validated = $this->validate([
                'syllabus_file_title' => ['required', 'string', 'max:150'],
                'syllabus_file_description' => ['nullable', 'string', 'max:400'],
                'syllabus_file' => ['required', 'file', 'max:10240'],
            ]);
        } catch (UnableToRetrieveMetadata $exception) {
            $this->reset('syllabus_file');
            $this->addError('syllabus_file', __('The selected file is no longer available. Please choose it again.'));
            $this->errorToast(__('Upload session expired. Please select the file again.'));

            return;
        }

        $originalName = $this->syllabus_file->getClientOriginalName();
        $mimeType = $this->syllabus_file->getClientMimeType();
        $path = $this->syllabus_file->store('course-materials/'.$this->course->id.'/syllabus', 'local');

        $sizeBytes = 0;
        try {
            $sizeBytes = Storage::disk('local')->size($path);
        } catch (UnableToRetrieveMetadata $exception) {
            $sizeBytes = 0;
        }

        CourseMaterial::query()->create([
            'course_id' => $this->course->id,
            'course_module_id' => null,
            'uploaded_by' => auth()->id(),
            'title' => $validated['syllabus_file_title'],
            'description' => $validated['syllabus_file_description'] !== '' ? $validated['syllabus_file_description'] : null,
            'file_path' => $path,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'is_syllabus' => true,
        ]);

        $this->reset(['syllabus_file_title', 'syllabus_file_description', 'syllabus_file']);
        $this->refreshCourse();
        $this->successToast(__('Syllabus file uploaded successfully.'));
    }

    public function uploadModuleMaterial(): void
    {
        Gate::authorize('update', $this->course);
        $this->ensureCanManageCourseContent();

        try {
            $validated = $this->validate([
                'material_title' => ['required', 'string', 'max:150'],
                'material_description' => ['nullable', 'string', 'max:400'],
                'material_module_id' => ['required', 'exists:course_modules,id'],
                'material_file' => ['required', 'file', 'max:10240'],
            ]);
        } catch (UnableToRetrieveMetadata $exception) {
            $this->reset('material_file');
            $this->addError('material_file', __('The selected file is no longer available. Please choose it again.'));
            $this->errorToast(__('Upload session expired. Please select the file again.'));

            return;
        }

        $module = CourseModule::query()
            ->where('course_id', $this->course->id)
            ->findOrFail((int) $validated['material_module_id']);

        $originalName = $this->material_file->getClientOriginalName();
        $mimeType = $this->material_file->getClientMimeType();
        $path = $this->material_file->store('course-materials/'.$this->course->id.'/modules/'.$module->id, 'local');

        $sizeBytes = 0;
        try {
            $sizeBytes = Storage::disk('local')->size($path);
        } catch (UnableToRetrieveMetadata $exception) {
            $sizeBytes = 0;
        }

        CourseMaterial::query()->create([
            'course_id' => $this->course->id,
            'course_module_id' => $module->id,
            'uploaded_by' => auth()->id(),
            'title' => $validated['material_title'],
            'description' => $validated['material_description'] !== '' ? $validated['material_description'] : null,
            'file_path' => $path,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'is_syllabus' => false,
        ]);

        $this->reset(['material_title', 'material_description', 'material_module_id', 'material_file']);
        $this->refreshCourse();
        $this->successToast(__('Module material uploaded successfully.'));
    }

    public function deleteMaterial(int $materialId): void
    {
        Gate::authorize('update', $this->course);
        $this->ensureCanManageCourseContent();

        $material = CourseMaterial::query()
            ->where('course_id', $this->course->id)
            ->findOrFail($materialId);

        if (Storage::disk('local')->exists($material->file_path)) {
            Storage::disk('local')->delete($material->file_path);
        }

        $material->delete();
        $this->refreshCourse();
        $this->successToast(__('Material deleted.'));
    }

    public function deleteModule(int $moduleId): void
    {
        Gate::authorize('update', $this->course);
        $this->ensureCanManageCourseContent();

        $module = CourseModule::query()
            ->where('course_id', $this->course->id)
            ->with('materials')
            ->findOrFail($moduleId);

        foreach ($module->materials as $material) {
            if (Storage::disk('local')->exists($material->file_path)) {
                Storage::disk('local')->delete($material->file_path);
            }
        }

        $module->delete();

        if ($this->material_module_id === $moduleId) {
            $this->material_module_id = null;
        }

        $this->refreshCourse();
        $this->successToast(__('Module deleted.'));
    }

    public function generateEnrollmentKey(): void
    {
        Gate::authorize('update', $this->course);
        $this->ensureCanManageEnrollments();

        app(GenerateEnrollmentKey::class)->handle($this->course);
        $this->refreshCourse();
        $this->successToast(__('Enrollment key generated successfully.'));
    }

    public function addStudentByEmail(): void
    {
        Gate::authorize('update', $this->course);
        $this->ensureCanManageEnrollments();

        $validated = $this->validate([
            'add_student_email' => ['required', 'email', 'exists:users,email'],
        ]);

        $student = User::query()->where('email', $validated['add_student_email'])->firstOrFail();

        try {
            app(EnrollStudentInCourseByInstructor::class)->handle($this->course, $student);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError('add_student_email', $message);
                }
            }

            $this->errorToast(__('Failed to enroll student.'));

            return;
        }

        $this->reset('add_student_email');
        $this->refreshCourse();
        $this->successToast(__('Student enrolled successfully.'));
    }

    public function removeStudent(int $enrollmentId): void
    {
        Gate::authorize('update', $this->course);
        $this->ensureCanManageEnrollments();

        $enrollment = Enrollment::query()
            ->where('course_id', $this->course->id)
            ->findOrFail($enrollmentId);

        $enrollment->delete();
        $this->refreshCourse();
        $this->successToast(__('Student removed from course.'));
    }

    #[Computed]
    public function enrolledStudents()
    {
        return $this->course->students()->get();
    }
}; ?>
<div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
    <x-ui.toast :message="$toastMessage" :variant="$toastVariant" />

    <div class="space-y-4">
        <div class="text-sm text-zinc-500">
            <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 hover:underline dark:hover:text-zinc-200">{{ __('Dashboard') }}</a>
            <span class="mx-2">/</span>
            <button type="button" onclick="history.back()" class="hover:text-zinc-700 hover:underline dark:hover:text-zinc-200">
                {{ __('Back') }}
            </button>
            <span class="mx-2">/</span>
            <span>{{ __('Course Home') }}</span>
        </div>

        <div>
            <flux:heading size="xl">{{ $course->title }}</flux:heading>
            <flux:subheading>{{ $course->department?->name }} · {{ $course->code }}</flux:subheading>

            @if ($this->canManageCourse || $this->isEnrolled)
                <div class="mt-3">
                    <flux:button
                        size="sm"
                        variant="ghost"
                        :href="route('quizzes.index', ['course' => $course->id])"
                        wire:navigate
                        icon="question-mark-circle"
                    >
                        {{ __('Open Quiz Module') }}
                    </flux:button>
                </div>
            @endif
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm lg:col-span-3 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Class Overview') }}</h2>
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $course->description ?: __('No description provided yet.') }}</p>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="text-sm text-zinc-500">{{ __('Instructor') }}</div>
                    <div class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">{{ $course->facultyProfile?->user?->name ?? __('Unassigned') }}</div>
                </div>

                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="text-sm text-zinc-500">{{ __('Department') }}</div>
                    <div class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">{{ $course->department?->name }}</div>
                </div>

                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="text-sm text-zinc-500">{{ __('Capacity') }}</div>
                    <div class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">{{ $course->capacity ?? '—' }}</div>
                </div>

                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="text-sm text-zinc-500">{{ __('Enrollment status') }}</div>
                    <div class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $this->isEnrolled ? __('Enrolled') : ($course->is_active ? __('Open') : __('Archived')) }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Syllabus') }}</h2>
                <div class="flex items-center gap-2">
                    @if ($course->syllabus_updated_at)
                        <span class="text-xs text-zinc-500">{{ __('Updated') }} {{ $course->syllabus_updated_at->diffForHumans() }}</span>
                    @endif
                    <flux:button
                        size="sm"
                        variant="ghost"
                        wire:click="toggleSyllabusCollapsed"
                        :icon="$is_syllabus_collapsed ? 'chevron-down' : 'chevron-up'"
                    >
                        {{ $is_syllabus_collapsed ? __('Expand') : __('Collapse') }}
                    </flux:button>
                </div>
            </div>

            @if (! $is_syllabus_collapsed)
                @if ($this->canAccessLearningContent)
                    <div class="mt-3 whitespace-pre-line text-sm text-zinc-600 dark:text-zinc-300">{{ $course->syllabus_content ?: __('No syllabus published yet.') }}</div>

                    @if ($this->syllabusFiles->isNotEmpty())
                        <div class="mt-4 space-y-2">
                            @foreach ($this->syllabusFiles as $material)
                                <div class="flex flex-col gap-2 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $material->title }}</div>
                                        <div class="text-xs text-zinc-500">{{ $material->original_name }}</div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <flux:button size="sm" variant="ghost" :href="route('courses.materials.download', [$course, $material])">
                                            {{ __('Download') }}
                                        </flux:button>
                                        @if ($this->canManageCourse)
                                            <flux:button size="sm" variant="danger" wire:click="deleteMaterial({{ $material->id }})" wire:confirm="{{ __('Delete this file?') }}">
                                                {{ __('Delete') }}
                                            </flux:button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @else
                    <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-200">
                        {{ __('Enroll in this class to access syllabus and learning materials.') }}
                    </div>
                @endif

                @if ($this->canManageCourse)
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <form wire:submit="saveSyllabus" class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700 md:col-span-2">
                            <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Syllabus content') }}</label>
                            <textarea wire:model="syllabus_content" rows="6" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm outline-none ring-indigo-500 focus:ring dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"></textarea>
                            <flux:button variant="primary" type="submit">{{ __('Save syllabus') }}</flux:button>
                        </form>

                        <form wire:submit="uploadSyllabusFile" class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700 md:col-span-2">
                            <flux:heading size="sm">{{ __('Upload syllabus file') }}</flux:heading>
                            <flux:input wire:model="syllabus_file_title" :label="__('Title')" type="text" required />
                            <flux:input wire:model="syllabus_file_description" :label="__('Description')" type="text" />
                            <flux:input wire:model="syllabus_file" :label="__('File')" type="file" required />
                            <flux:button variant="primary" type="submit">{{ __('Upload syllabus file') }}</flux:button>
                        </form>
                    </div>
                @endif
            @endif
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Modules & Learning Materials') }}</h2>
                <flux:button
                    size="sm"
                    variant="ghost"
                    wire:click="toggleModulesCollapsed"
                    :icon="$is_modules_collapsed ? 'chevron-down' : 'chevron-up'"
                >
                    {{ $is_modules_collapsed ? __('Expand') : __('Collapse') }}
                </flux:button>
            </div>

            @if (! $is_modules_collapsed)
                @if ($this->canAccessLearningContent)
                    @if ($this->canManageCourse)
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <form wire:submit="createModule" class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                                <flux:heading size="sm">{{ __('Create module') }}</flux:heading>
                                <flux:input wire:model="module_title" :label="__('Title')" type="text" required />
                                <flux:input wire:model="module_week_number" :label="__('Week number')" type="number" min="1" max="52" />
                                <flux:input wire:model="module_position" :label="__('Sort order')" type="number" min="0" max="1000" />
                                <flux:input wire:model="module_description" :label="__('Description')" type="text" />
                                <flux:button variant="primary" type="submit">{{ __('Create module') }}</flux:button>
                            </form>

                            <form wire:submit="uploadModuleMaterial" class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                                <flux:heading size="sm">{{ __('Upload module material') }}</flux:heading>
                                <flux:select wire:model="material_module_id" :label="__('Module')" required>
                                    <option value="">{{ __('Select module') }}</option>
                                    @foreach ($this->modules as $moduleOption)
                                        <option value="{{ $moduleOption->id }}">{{ $moduleOption->title }}</option>
                                    @endforeach
                                </flux:select>
                                <flux:input wire:model="material_title" :label="__('Title')" type="text" required />
                                <flux:input wire:model="material_description" :label="__('Description')" type="text" />
                                <flux:input wire:model="material_file" :label="__('File')" type="file" required />
                                <flux:button variant="primary" type="submit">{{ __('Upload material') }}</flux:button>
                            </form>
                        </div>
                    @endif

                    <div class="mt-4 space-y-3">
                        @forelse ($this->modules as $module)
                            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $module->title }}</h3>
                                        <div class="text-xs text-zinc-500">
                                            {{ $module->week_number ? __('Week :week', ['week' => $module->week_number]) : __('No week assigned') }}
                                        </div>
                                    </div>
                                    @if ($this->canManageCourse)
                                        <flux:button size="sm" variant="danger" wire:click="deleteModule({{ $module->id }})" wire:confirm="{{ __('Delete this module and all its files?') }}">
                                            {{ __('Delete module') }}
                                        </flux:button>
                                    @endif
                                </div>

                                @if ($module->description)
                                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $module->description }}</p>
                                @endif

                                <div class="mt-3 space-y-2">
                                    @forelse ($module->materials as $material)
                                        <div class="flex flex-col gap-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $material->title }}</div>
                                                <div class="text-xs text-zinc-500">{{ $material->original_name }}</div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <flux:button size="sm" variant="ghost" :href="route('courses.materials.download', [$course, $material])">
                                                    {{ __('Download') }}
                                                </flux:button>
                                                @if ($this->canManageCourse)
                                                    <flux:button size="sm" variant="danger" wire:click="deleteMaterial({{ $material->id }})" wire:confirm="{{ __('Delete this file?') }}">
                                                        {{ __('Delete') }}
                                                    </flux:button>
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-sm text-zinc-500">{{ __('No materials uploaded yet for this module.') }}</div>
                                    @endforelse
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                                {{ __('No modules have been created yet.') }}
                            </div>
                        @endforelse
                    </div>
                @else
                    <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-200">
                        {{ __('Enroll in this class to access module content and downloadable files.') }}
                    </div>
                @endif
            @endif
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Assignments') }}</h2>
                <flux:button
                    size="sm"
                    variant="ghost"
                    wire:click="toggleAssignmentsCollapsed"
                    :icon="$is_assignments_collapsed ? 'chevron-down' : 'chevron-up'"
                >
                    {{ $is_assignments_collapsed ? __('Expand') : __('Collapse') }}
                </flux:button>
            </div>

            @if (! $is_assignments_collapsed)
                @if ($this->canAccessLearningContent)
                    @if ($this->canManageCourse && auth()->user()->can('assignments.manage'))
                        <form wire:submit="createAssignment" class="mt-4 grid gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700 md:grid-cols-2">
                            <flux:input wire:model="assignment_title" :label="__('Title')" type="text" required />
                            <flux:input wire:model="assignment_due_date" :label="__('Due date')" type="datetime-local" required />
                            <flux:input wire:model="assignment_max_score" :label="__('Max score')" type="number" min="1" max="999.99" step="0.01" required />
                            <div class="md:col-span-2 space-y-1">
                                <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Description') }}</label>
                                <textarea
                                    wire:model="assignment_description"
                                    rows="4"
                                    required
                                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm outline-none ring-indigo-500 focus:ring dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                                ></textarea>
                            </div>
                            <div class="md:col-span-2">
                                <flux:button variant="primary" type="submit">{{ __('Create assignment') }}</flux:button>
                            </div>
                        </form>
                    @endif

                    @if ($this->isEnrolled && auth()->user()->studentProfile()->exists())
                        <form wire:submit="submitAssignment" class="mt-4 grid gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700 md:grid-cols-2">
                            <flux:select wire:model="submission_assignment_id" :label="__('Assignment')" required>
                                <option value="">{{ __('Select assignment') }}</option>
                                @foreach ($this->assignments as $assignmentOption)
                                    <option value="{{ $assignmentOption->id }}">{{ $assignmentOption->title }}</option>
                                @endforeach
                            </flux:select>
                            <flux:input wire:model="submission_file" :label="__('Submission file')" type="file" required />
                            <div class="md:col-span-2">
                                <flux:button variant="primary" type="submit">{{ __('Submit assignment') }}</flux:button>
                            </div>
                        </form>
                    @endif

                    <div class="mt-4 space-y-3">
                        @forelse ($this->assignments as $assignment)
                            @php
                                $mySubmission = $this->myAssignmentSubmissions->get($assignment->id);
                            @endphp
                            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $assignment->title }}</h3>
                                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $assignment->description }}</p>
                                        <div class="mt-2 text-xs text-zinc-500">
                                            {{ __('Due: :due · Max score: :max', ['due' => $assignment->due_date?->format('M d, Y h:i A'), 'max' => $assignment->max_score]) }}
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if ($this->canManageCourse)
                                            <flux:badge>{{ __('Submissions: :count', ['count' => $assignment->submissions_count]) }}</flux:badge>
                                        @endif

                                        @if ($mySubmission)
                                            <flux:badge :variant="$mySubmission->is_late ? 'warning' : 'success'">
                                                {{ $mySubmission->is_late ? __('Submitted late') : __('Submitted') }}
                                            </flux:badge>

                                            @if ($mySubmission->score !== null)
                                                <flux:badge variant="success">{{ __('Score: :score', ['score' => $mySubmission->score]) }}</flux:badge>
                                            @endif
                                        @endif

                                        @if ($this->canManageCourse && auth()->user()->can('assignments.manage'))
                                            <flux:button
                                                size="sm"
                                                variant="danger"
                                                wire:click="deleteAssignment({{ $assignment->id }})"
                                                wire:confirm="{{ __('Delete this assignment and all submissions?') }}"
                                            >
                                                {{ __('Delete') }}
                                            </flux:button>
                                        @endif
                                    </div>
                                </div>

                                @if ($this->canManageCourse && auth()->user()->can('assignments.manage'))
                                    <div class="mt-4 space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                                        <h4 class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Submission Grading') }}</h4>

                                        @forelse ($assignment->submissions as $submission)
                                            <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                    <div class="text-sm">
                                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $submission->student?->name }}</div>
                                                        <div class="text-xs text-zinc-500">{{ $submission->student?->email }}</div>
                                                        <div class="mt-1 text-xs text-zinc-500">
                                                            {{ __('Submitted: :date', ['date' => $submission->submission_date?->format('M d, Y h:i A')]) }}
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        @if ($submission->is_late)
                                                            <flux:badge variant="warning">{{ __('Late') }}</flux:badge>
                                                        @endif
                                                        @if ($submission->graded_at)
                                                            <flux:badge variant="success">{{ __('Graded') }}</flux:badge>
                                                        @endif
                                                    </div>
                                                </div>

                                                <div class="mt-3 grid gap-3 md:grid-cols-3">
                                                    <flux:input
                                                        wire:model="submissionScores.{{ $submission->id }}"
                                                        :label="__('Score (out of :max)', ['max' => $assignment->max_score])"
                                                        type="number"
                                                        min="0"
                                                        max="{{ $assignment->max_score }}"
                                                        step="0.01"
                                                        placeholder="{{ $submission->score }}"
                                                    />

                                                    <div class="md:col-span-2 space-y-1">
                                                        <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Feedback') }}</label>
                                                        <textarea
                                                            wire:model="submissionFeedbacks.{{ $submission->id }}"
                                                            rows="2"
                                                            class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm outline-none ring-indigo-500 focus:ring dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                                                            placeholder="{{ $submission->feedback ?? __('Optional feedback') }}"
                                                        ></textarea>
                                                    </div>
                                                </div>

                                                <div class="mt-3 flex justify-end">
                                                    <flux:button size="sm" variant="primary" wire:click="gradeAssignmentSubmission({{ $submission->id }})">
                                                        {{ __('Save score & feedback') }}
                                                    </flux:button>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="text-sm text-zinc-500">{{ __('No submissions yet for this assignment.') }}</div>
                                        @endforelse
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="rounded-xl border border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                                {{ __('No assignments published yet.') }}
                            </div>
                        @endforelse
                    </div>
                @else
                    <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-200">
                        {{ __('Enroll in this class to access assignments.') }}
                    </div>
                @endif
            @endif
        </div>

        @if ($this->canManageCourse)
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Enrollment Management') }}</h2>
                    <flux:button
                        size="sm"
                        variant="ghost"
                        wire:click="toggleEnrollmentManagementCollapsed"
                        :icon="$is_enrollment_management_collapsed ? 'chevron-down' : 'chevron-up'"
                    >
                        {{ $is_enrollment_management_collapsed ? __('Expand') : __('Collapse') }}
                    </flux:button>
                </div>

                @if (! $is_enrollment_management_collapsed)
                    <div class="mt-4 space-y-6">
                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <flux:button size="sm" wire:click="generateEnrollmentKey" wire:confirm="{{ __('Generate a new enrollment key? The current key will no longer work.') }}">
                                    {{ __('Generate key') }}
                                </flux:button>
                            </div>

                            @if ($course->enrollment_key)
                                <div class="mt-4 flex flex-col gap-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-900/40 dark:bg-emerald-950/30">
                                    <div class="text-xs font-medium text-emerald-700 dark:text-emerald-200">{{ __('Current enrollment key') }}</div>
                                    <div class="font-mono text-lg font-semibold text-emerald-900 dark:text-emerald-100">{{ $course->enrollment_key }}</div>
                                </div>
                            @else
                                <div class="mt-4 text-sm text-zinc-500">{{ __('No enrollment key generated yet. Generate one to allow students to self-enroll.') }}</div>
                            @endif
                        </div>

                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <h3 class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('Add student by email') }}</h3>
                            <p class="mt-1 text-sm text-zinc-500">{{ __('Enroll a student directly without requiring an enrollment key.') }}</p>

                            <form wire:submit="addStudentByEmail" class="mt-4 space-y-3">
                                <flux:input wire:model="add_student_email" :label="__('Student email')" type="email" required />
                                <flux:button variant="primary" type="submit" class="w-full">{{ __('Enroll student') }}</flux:button>
                            </form>
                        </div>

                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <h3 class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('Enrolled Students') }}</h3>

                            <div class="mt-4 space-y-2">
                                @forelse ($this->enrolledStudents as $student)
                                    <div class="flex flex-col gap-2 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $student->name }}</div>
                                            <div class="text-xs text-zinc-500">{{ $student->email }}</div>
                                        </div>
                                        @php
                                            $enrollment = $student->enrollments()->where('course_id', $course->id)->first();
                                        @endphp
                                        @if ($enrollment)
                                            <flux:button size="sm" variant="danger" wire:click="removeStudent({{ $enrollment->id }})" wire:confirm="{{ __('Remove this student from the course?') }}">
                                                {{ __('Remove') }}
                                            </flux:button>
                                        @endif
                                    </div>
                                @empty
                                    <div class="rounded-xl border border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                                        {{ __('No students enrolled yet.') }}
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>

