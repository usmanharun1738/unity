<?php

use App\Actions\Courses\EnrollStudentInCourseByInstructor;
use App\Actions\Courses\GenerateEnrollmentKey;
use App\Enums\RoleName;
use App\Livewire\Concerns\HasToastFeedback;
use App\Models\AssessmentLog;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\QuizResponse;
use App\Models\User;
use App\Services\Grading\GradeCalculatorService;
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

    public bool $is_attendance_collapsed = true;

    public string $attendance_date = '';

    /** @var array<int, string> */
    public array $studentAttendanceStatus = [];

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
            'submission_file' => ['required', 'file', 'max:10240', 'mimes:pdf,docx,doc,zip,txt'],
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

        $submission = AssignmentSubmission::query()->updateOrCreate(
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

        if (class_exists(\App\Events\AssignmentSubmitted::class)) {
            event(new \App\Events\AssignmentSubmitted($submission));
        }

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

        if (class_exists(\App\Events\AssignmentGraded::class)) {
            event(new \App\Events\AssignmentGraded($submission));
        }

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

        $scoresChanged = $grade->isDirty(['assignment_score']);
        app(GradeCalculatorService::class)->recalculate($grade, $scoresChanged);
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
                'syllabus_file' => ['required', 'file', 'max:10240', 'mimes:pdf,docx,doc,txt'],
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
                'material_file' => ['required', 'file', 'max:10240', 'mimes:pdf,docx,doc,ppt,pptx,xls,xlsx,zip,txt,jpg,jpeg,png'],
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

    public function toggleAttendanceCollapsed(): void
    {
        $this->is_attendance_collapsed = ! $this->is_attendance_collapsed;
        if (! $this->is_attendance_collapsed && empty($this->studentAttendanceStatus)) {
            $this->loadAttendanceForDate();
        }
    }

    public function updatedAttendanceDate(): void
    {
        $this->loadAttendanceForDate();
    }

    public function loadAttendanceForDate(): void
    {
        if ($this->attendance_date === '') {
            $this->attendance_date = now()->format('Y-m-d');
        }

        $existing = Attendance::query()
            ->where('course_id', $this->course->id)
            ->where('attendance_date', $this->attendance_date)
            ->pluck('status', 'user_id')
            ->all();

        foreach ($this->enrolledStudents as $student) {
            $this->studentAttendanceStatus[$student->id] = $existing[$student->id] ?? 'present';
        }
    }

    public function markAllAttendance(string $status): void
    {
        foreach ($this->enrolledStudents as $student) {
            $this->studentAttendanceStatus[$student->id] = $status;
        }
    }

    public function saveAttendance(): void
    {
        Gate::authorize('update', $this->course);
        abort_unless(auth()->user()->can('attendance.manage'), 403);

        if ($this->attendance_date === '') {
            $this->attendance_date = now()->format('Y-m-d');
        }

        foreach ($this->studentAttendanceStatus as $studentId => $status) {
            if (! in_array($status, ['present', 'absent', 'late', 'excused'], true)) {
                continue;
            }

            Attendance::query()->updateOrCreate(
                [
                    'course_id' => $this->course->id,
                    'user_id' => $studentId,
                    'attendance_date' => $this->attendance_date,
                ],
                [
                    'status' => $status,
                    'marked_by' => auth()->id(),
                ]
            );
        }

        $this->successToast(__('Attendance recorded successfully for :date', ['date' => $this->attendance_date]));
    }

    #[Computed]
    public function enrolledStudents()
    {
        return $this->course->students()->get();
    }
}; ?>
<div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
    <x-ui.toast :message="$toastMessage" :variant="$toastVariant" />

    @include('pages.courses.partials.header')

    @include('pages.courses.partials.overview')

    <div class="space-y-4">
        @include('pages.courses.partials.syllabus')
        @include('pages.courses.partials.modules')
        @include('pages.courses.partials.assignments')
        @include('pages.courses.partials.quizzes')
        @include('pages.courses.partials.enrollments')
        @include('pages.courses.partials.attendance')
    </div>
</div>


