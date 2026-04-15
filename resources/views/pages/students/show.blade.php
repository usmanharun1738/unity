<div class="space-y-6">
    <!-- Breadcrumb & Header -->
    <div class="space-y-3">
        <flux:button variant="ghost" size="sm" onclick="history.back()" icon="arrow-left" class="text-zinc-600">
            {{ __('Back') }}
        </flux:button>

        <div>
            <div class="flex items-center gap-4">
                <flux:avatar :name="$user->name" size="lg" />
                <div>
                    <h1 class="text-3xl font-bold">{{ $user->name }}</h1>
                    <p class="text-zinc-600 dark:text-zinc-400">{{ $user->email }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Info Cards -->
    <div class="grid md:grid-cols-4 gap-4">
        <flux:card class="space-y-2">
            <flux:heading level="3" size="lg">{{ $this->studentProfile?->student_number ?? '-' }}</flux:heading>
            <flux:text size="sm">{{ __('Student Number') }}</flux:text>
        </flux:card>
        <flux:card class="space-y-2">
            <flux:heading level="3" size="lg">{{ $this->studentProfile?->major ?? '-' }}</flux:heading>
            <flux:text size="sm">{{ __('Major/Department') }}</flux:text>
        </flux:card>
        <flux:card class="space-y-2">
            <flux:heading level="3" size="lg">Year {{ $this->studentProfile?->year_level ?? '-' }}</flux:heading>
            <flux:text size="sm">{{ __('Year Level') }}</flux:text>
        </flux:card>
        <flux:card class="space-y-2">
            <flux:heading level="3" size="lg">{{ $this->enrolledCourses->count() }}</flux:heading>
            <flux:text size="sm">{{ __('Enrolled Courses') }}</flux:text>
        </flux:card>
    </div>

    <!-- Tabs -->
    <div class="space-y-4">
        <!-- Enrolled Courses Section -->
        <flux:card>
            <flux:heading level="2">{{ __('Enrolled Courses') }}</flux:heading>

            @if ($this->enrolledCourses->count() > 0)
                <div class="space-y-3 mt-4">
                    @foreach ($this->enrolledCourses as $course)
                        <div class="flex items-center justify-between p-3 border border-zinc-200 dark:border-zinc-700 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <div>
                                <p class="font-semibold">{{ $course->title }}</p>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $course->code }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                @php
                                    $grade = $this->studentGrades->get($course->id)?->first();
                                @endphp
                                @if ($grade)
                                    <flux:badge :variant="match($grade->grade_letter) {
                                        'A' => 'success',
                                        'B', 'C', 'D' => 'warning',
                                        'F' => 'danger',
                                        default => 'default'
                                    }">
                                        {{ $grade->grade_letter ?? 'N/A' }} - {{ number_format($grade->final_grade, 2) }}%
                                    </flux:badge>
                                @else
                                    <flux:badge variant="default">{{ __('No Grade') }}</flux:badge>
                                @endif
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil"
                                    wire:click="openAssessmentForm({{ $course->id }})"
                                >
                                    {{ __('Log Assessment') }}
                                </flux:button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                    {{ __('No enrolled courses') }}
                </div>
            @endif
        </flux:card>

        <!-- Assessment Modal -->
        @if ($show_assessment_form)
            <flux:modal>
                <flux:heading level="2">{{ __('Log Assessment Scores') }}</flux:heading>

                @if ($selected_course_id)
                    @php
                        $selectedCourse = $this->enrolledCourses->firstWhere('id', $selected_course_id);
                        $currentGrade = $this->studentGrades->get($selected_course_id)?->first();
                    @endphp

                    @if ($selectedCourse)
                        <div class="space-y-4 mt-4">
                            @if ($errors->any())
                                <flux:card class="bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800">
                                    <ul class="list-disc ps-5 text-sm text-red-900 dark:text-red-100 space-y-1">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </flux:card>
                            @endif

                            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                {{ __('Recording scores for') }}: <strong>{{ $selectedCourse->title }}</strong>
                            </p>

                            <!-- Score Inputs -->
                            <div class="grid md:grid-cols-2 gap-4">
                                <flux:input
                                    wire:model.number="ca_score"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    :label="__('Continuous Assessment')"
                                    :placeholder="$currentGrade?->ca_score ?? 'e.g., 85.50'"
                                />
                                <flux:input
                                    wire:model.number="test_score"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    :label="__('Tests')"
                                    :placeholder="$currentGrade?->test_score ?? 'e.g., 90.00'"
                                />
                                <flux:input
                                    wire:model.number="assignment_score"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    :label="__('Assignments')"
                                    :placeholder="$currentGrade?->assignment_score ?? 'e.g., 88.00'"
                                />
                                <flux:input
                                    wire:model.number="quiz_score"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    :label="__('Quizzes')"
                                    :placeholder="$currentGrade?->quiz_score ?? 'e.g., 92.00'"
                                />
                                <flux:input
                                    wire:model.number="project_score"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    :label="__('Projects')"
                                    :placeholder="$currentGrade?->project_score ?? 'e.g., 87.00'"
                                />
                                <flux:input
                                    wire:model.number="exam_score"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    :label="__('Exam')"
                                    :placeholder="$currentGrade?->exam_score ?? 'e.g., 85.00'"
                                />
                            </div>

                            <!-- Grading Formula Info -->
                            <flux:card class="bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800">
                                <flux:text size="sm" class="text-blue-900 dark:text-blue-100">
                                    {{ __('Formula: CA(30%) + Tests(20%) + Assignments(10%) + Projects(10%) + Exam(30%)') }}
                                </flux:text>
                            </flux:card>

                            <!-- Current Grade Preview -->
                            @php
                                $scores = [
                                    'ca' => $ca_score ? (float)$ca_score : ($currentGrade?->ca_score ?? 0),
                                    'test' => $test_score ? (float)$test_score : ($currentGrade?->test_score ?? 0),
                                    'assignment' => $assignment_score ? (float)$assignment_score : ($currentGrade?->assignment_score ?? 0),
                                    'quiz' => $quiz_score ? (float)$quiz_score : ($currentGrade?->quiz_score ?? 0),
                                    'project' => $project_score ? (float)$project_score : ($currentGrade?->project_score ?? 0),
                                    'exam' => $exam_score ? (float)$exam_score : ($currentGrade?->exam_score ?? 0),
                                ];
                                $calculatedFinal = ($scores['ca'] * 0.30) + ($scores['test'] * 0.20) + ($scores['assignment'] * 0.10) + ($scores['project'] * 0.10) + ($scores['exam'] * 0.30);
                                $letterGrade = match (true) {
                                    $calculatedFinal >= 80 => 'A',
                                    $calculatedFinal >= 70 => 'B',
                                    $calculatedFinal >= 60 => 'C',
                                    $calculatedFinal >= 50 => 'D',
                                    default => 'F'
                                };
                            @endphp

                            @if ($ca_score || $test_score || $assignment_score || $quiz_score || $project_score || $exam_score)
                                <flux:card class="bg-zinc-50 dark:bg-zinc-900">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">
                                                {{ __('Calculated Final Grade') }}
                                            </flux:text>
                                            <flux:heading level="3" size="lg">
                                                {{ number_format($calculatedFinal, 2) }}% ({{ $letterGrade }})
                                            </flux:heading>
                                        </div>
                                        <flux:badge :variant="match($letterGrade) {
                                            'A' => 'success',
                                            'B', 'C', 'D' => 'warning',
                                            'F' => 'danger',
                                            default => 'default'
                                        }">
                                            {{ $letterGrade }}
                                        </flux:badge>
                                    </div>
                                </flux:card>
                            @endif

                            <!-- Message Display -->
                            @if ($assessment_message)
                                <flux:card class="bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800">
                                    <flux:text size="sm" class="text-green-900 dark:text-green-100">
                                        {{ $assessment_message }}
                                    </flux:text>
                                </flux:card>
                            @endif

                            <!-- Actions -->
                            <div class="flex gap-2 justify-end">
                                <flux:button variant="ghost" wire:click="closeAssessmentForm">
                                    {{ __('Cancel') }}
                                </flux:button>
                                <flux:button wire:click="saveAssessmentScores">
                                    {{ __('Save Scores') }}
                                </flux:button>
                            </div>
                        </div>
                    @endif
                @endif
            </flux:modal>
        @endif

        <!-- Grades Summary -->
        @if ($this->studentGrades->count() > 0)
            <flux:card>
                <flux:heading level="2">{{ __('Grade Summary') }}</flux:heading>

                @if ($approval_message)
                    <flux:card class="mt-4 bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800">
                        <flux:text size="sm" class="text-green-900 dark:text-green-100">
                            {{ $approval_message }}
                        </flux:text>
                    </flux:card>
                @endif

                <div class="mt-4 space-y-3">
                    @foreach ($this->studentGrades as $courseId => $grades)
                        @php
                            $grade = $grades->first();
                            $course = $this->enrolledCourses->firstWhere('id', $courseId);
                        @endphp
                        <div class="p-3 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-semibold">{{ $course?->title ?? 'Unknown Course' }}</p>
                                    <div class="grid grid-cols-3 gap-2 mt-2 text-xs">
                                        <div class="text-zinc-600 dark:text-zinc-400">CA: <span class="font-semibold">{{ $grade->ca_score ?? '-' }}</span></div>
                                        <div class="text-zinc-600 dark:text-zinc-400">Tests: <span class="font-semibold">{{ $grade->test_score ?? '-' }}</span></div>
                                        <div class="text-zinc-600 dark:text-zinc-400">Assignments: <span class="font-semibold">{{ $grade->assignment_score ?? '-' }}</span></div>
                                        <div class="text-zinc-600 dark:text-zinc-400">Quizzes: <span class="font-semibold">{{ $grade->quiz_score ?? '-' }}</span></div>
                                        <div class="text-zinc-600 dark:text-zinc-400">Projects: <span class="font-semibold">{{ $grade->project_score ?? '-' }}</span></div>
                                        <div class="text-zinc-600 dark:text-zinc-400">Exam: <span class="font-semibold">{{ $grade->exam_score ?? '-' }}</span></div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <flux:heading level="3" size="xl">
                                        {{ number_format($grade->final_grade, 2) }}%
                                    </flux:heading>
                                    <flux:badge :variant="match($grade->grade_letter) {
                                        'A' => 'success',
                                        'B', 'C', 'D' => 'warning',
                                        'F' => 'danger',
                                        default => 'default'
                                    }">
                                        {{ $grade->grade_letter ?? 'N/A' }}
                                    </flux:badge>

                                    <div class="mt-2">
                                        @if ($grade->is_approved_by_admin)
                                            <flux:badge variant="success">{{ __('Approved') }}</flux:badge>
                                        @else
                                            <flux:badge variant="warning">{{ __('Pending Approval') }}</flux:badge>
                                        @endif
                                    </div>

                                    @if ($this->canApproveGrades)
                                        <div class="mt-2 flex items-center justify-end gap-2">
                                            @if (! $grade->is_approved_by_admin)
                                                <flux:button size="xs" variant="primary" wire:click="approveGrade({{ $grade->id }})">
                                                    {{ __('Approve') }}
                                                </flux:button>
                                            @else
                                                <flux:button size="xs" variant="ghost" wire:click="revokeGradeApproval({{ $grade->id }})">
                                                    {{ __('Reset') }}
                                                </flux:button>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </flux:card>
        @endif

        <!-- Attendance Summary -->
        @if ($this->attendanceRecords->count() > 0)
            <flux:card>
                <flux:heading level="2">{{ __('Attendance Record') }}</flux:heading>

                <div class="mt-4 space-y-3">
                    @php
                        $courseAttendance = [];
                        foreach ($this->attendanceRecords as $courseId => $records) {
                            $total = $records->count();
                            $present = $records->whereIn('status', ['present', 'late'])->count();
                            $percentage = ($total > 0) ? (($present / $total) * 100) : 0;
                            $courseAttendance[] = [
                                'courseId' => $courseId,
                                'course' => $this->enrolledCourses->firstWhere('id', $courseId),
                                'total' => $total,
                                'present' => $present,
                                'percentage' => $percentage,
                                'records' => $records->take(5),
                            ];
                        }
                    @endphp

                    @foreach ($courseAttendance as $attendance)
                        <div class="p-3 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <p class="font-semibold">{{ $attendance['course']?->title ?? 'Unknown Course' }}</p>
                                <flux:badge
                                    :variant="$attendance['percentage'] >= 75 ? 'success' : 'danger'"
                                >
                                    {{ number_format($attendance['percentage'], 0) }}%
                                </flux:badge>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                                <span>{{ $attendance['present'] }} of {{ $attendance['total'] }} sessions attended</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </flux:card>
        @endif
    </div>
</div>
