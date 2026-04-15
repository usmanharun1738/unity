<?php

namespace App\Livewire\Pages\Students;

use App\Models\Grade;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Student Profile')]
class Show extends Component
{
    use AuthorizesRequests;

    public User $user;

    public bool $show_assessment_form = false;

    public ?int $selected_course_id = null;

    public ?string $ca_score = null;

    public ?string $test_score = null;

    public ?string $assignment_score = null;

    public ?string $quiz_score = null;

    public ?string $project_score = null;

    public ?string $exam_score = null;

    public ?string $assessment_message = null;

    public ?string $approval_message = null;

    public function mount(User $user): void
    {
        $this->user = $user;

        $this->authorize('viewAcademicRecords', $this->user);
    }

    #[Computed]
    public function studentProfile()
    {
        return $this->user->studentProfile;
    }

    #[Computed]
    public function enrolledCourses()
    {
        return $this->user
            ->enrollments()
            ->with('course')
            ->where('status', 'active')
            ->get()
            ->pluck('course')
            ->sortBy('title');
    }

    #[Computed]
    public function studentGrades()
    {
        return Grade::where('user_id', $this->user->id)
            ->with('course')
            ->get()
            ->groupBy('course_id');
    }

    #[Computed]
    public function attendanceRecords()
    {
        return $this->user
            ->attendanceRecords()
            ->with('course')
            ->latest('attendance_date')
            ->get()
            ->groupBy('course_id');
    }

    public function openAssessmentForm(?int $courseId = null): void
    {
        $this->selected_course_id = $courseId;
        $this->show_assessment_form = true;
        $this->resetAssessmentForm();
    }

    public function closeAssessmentForm(): void
    {
        $this->show_assessment_form = false;
        $this->resetAssessmentForm();
    }

    public function resetAssessmentForm(): void
    {
        $this->ca_score = null;
        $this->test_score = null;
        $this->assignment_score = null;
        $this->quiz_score = null;
        $this->project_score = null;
        $this->exam_score = null;
        $this->assessment_message = null;
    }

    #[Computed]
    public function canApproveGrades(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'department-staff']);
    }

    public function saveAssessmentScores(): void
    {
        $enrolledCourseIds = $this->enrolledCourses->pluck('id')->all();

        $validated = $this->validate([
            'selected_course_id' => ['required', 'integer', Rule::in($enrolledCourseIds)],
            'ca_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'test_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'assignment_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'quiz_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'project_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'exam_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $this->authorize('manageCourseAssessments', [$this->user, (int) $validated['selected_course_id']]);

        $grade = Grade::firstOrCreate(
            [
                'user_id' => $this->user->id,
                'course_id' => $validated['selected_course_id'],
            ]
        );

        $this->authorize('update', $grade);

        // Update individual scores
        if ($this->ca_score !== null) {
            $grade->ca_score = (float) $this->ca_score;
        }
        if ($this->test_score !== null) {
            $grade->test_score = (float) $this->test_score;
        }
        if ($this->assignment_score !== null) {
            $grade->assignment_score = (float) $this->assignment_score;
        }
        if ($this->quiz_score !== null) {
            $grade->quiz_score = (float) $this->quiz_score;
        }
        if ($this->project_score !== null) {
            $grade->project_score = (float) $this->project_score;
        }
        if ($this->exam_score !== null) {
            $grade->exam_score = (float) $this->exam_score;
        }

        // Any score change requires a fresh admin verification.
        if ($grade->isDirty(['ca_score', 'test_score', 'assignment_score', 'quiz_score', 'project_score', 'exam_score'])) {
            $grade->is_approved_by_admin = false;
            $grade->approved_by = null;
            $grade->approved_at = null;
        }

        // Calculate final grade: CA(30%) + Tests(20%) + Assignments(10%) + Projects(10%) + Exam(30%)
        $scores = [
            'ca' => $grade->ca_score ?? 0,
            'test' => $grade->test_score ?? 0,
            'assignment' => $grade->assignment_score ?? 0,
            'quiz' => $grade->quiz_score ?? 0,
            'project' => $grade->project_score ?? 0,
            'exam' => $grade->exam_score ?? 0,
        ];

        $finalGrade = ($scores['ca'] * 0.30) + ($scores['test'] * 0.20) + ($scores['assignment'] * 0.10) + ($scores['project'] * 0.10) + ($scores['exam'] * 0.30);
        $grade->final_grade = round($finalGrade, 2);

        $grade->grade_letter = match (true) {
            $finalGrade >= 80 => 'A',
            $finalGrade >= 70 => 'B',
            $finalGrade >= 60 => 'C',
            $finalGrade >= 50 => 'D',
            default => 'F'
        };

        $grade->save();

        $this->closeAssessmentForm();
        $this->assessment_message = 'Assessment scores saved successfully!';

        // Refresh data
        $this->dispatch('refresh');
    }

    public function approveGrade(int $gradeId): void
    {
        $grade = Grade::query()
            ->where('id', $gradeId)
            ->where('user_id', $this->user->id)
            ->firstOrFail();

        $this->authorize('approve', $grade);

        $grade->is_approved_by_admin = true;
        $grade->approved_by = auth()->id();
        $grade->approved_at = now();
        $grade->save();

        $this->approval_message = 'Grade approved successfully.';
        $this->dispatch('refresh');
    }

    public function revokeGradeApproval(int $gradeId): void
    {
        $grade = Grade::query()
            ->where('id', $gradeId)
            ->where('user_id', $this->user->id)
            ->firstOrFail();

        $this->authorize('approve', $grade);

        $grade->is_approved_by_admin = false;
        $grade->approved_by = null;
        $grade->approved_at = null;
        $grade->save();

        $this->approval_message = 'Grade approval was reset.';
        $this->dispatch('refresh');
    }

    public function render()
    {
        return view('pages.students.show');
    }
}
