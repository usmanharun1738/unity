<?php

use App\Livewire\Concerns\HasToastFeedback;
use App\Models\AssessmentLog;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Quizzes')] class extends Component
{
    use HasToastFeedback;

    public ?int $course_id = null;

    public string $quiz_title = '';

    public string $quiz_description = '';

    public string $quiz_max_score = '100';

    public string $quiz_pass_score = '';

    public string $quiz_time_limit_minutes = '';

    public bool $quiz_show_results_immediately = true;

    public ?int $selected_quiz_id = null;

    public string $question_prompt = '';

    public bool $question_allows_multiple = false;

    public string $question_points = '1';

    public string $theory_question_prompt = '';

    public string $theory_question_points = '1';

    public string $theory_question_rubric = '';

    /** @var array<int, string> */
    public array $question_options = ['', '', '', ''];

    /** @var array<int, int|string> */
    public array $question_correct_options = [];

    /** @var array<int, string|int|float|null> */
    public array $quizResponseScores = [];

    /** @var array<int, array<int, int|string|array<int, int|string>|null>> */
    public array $attemptAnswers = [];

    /** @var array<int, array<int, string|int|float|null>> */
    public array $theoryQuestionScores = [];

    /** @var array<int, array<int, string|null>> */
    public array $theoryQuestionFeedbacks = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', Quiz::class);

        if (! auth()->user()->can('courses.view')) {
            abort(403);
        }

        $this->pullToastFromSession();

        $requestedCourseId = request()->integer('course');

        if ($requestedCourseId > 0) {
            $requestedCourse = $this->availableCourses->firstWhere('id', $requestedCourseId);

            if ($requestedCourse) {
                $this->course_id = (int) $requestedCourse->id;

                $firstQuiz = Quiz::query()
                    ->where('course_id', $requestedCourse->id)
                    ->orderBy('display_order')
                    ->orderBy('created_at')
                    ->first();

                if ($firstQuiz) {
                    $this->selected_quiz_id = (int) $firstQuiz->id;
                }

                return;
            }
        }

        $firstCourse = $this->availableCourses->first();
        if ($firstCourse) {
            $this->course_id = (int) $firstCourse->id;

            $firstQuiz = Quiz::query()
                ->where('course_id', $firstCourse->id)
                ->orderBy('display_order')
                ->orderBy('created_at')
                ->first();

            if ($firstQuiz) {
                $this->selected_quiz_id = (int) $firstQuiz->id;
            }
        }
    }

    #[Computed]
    public function availableCourses()
    {
        $user = auth()->user();

        $query = Course::query()
            ->with('department')
            ->where('is_active', true)
            ->orderBy('title');

        if ($user->hasAnyRole(['admin', 'department-staff'])) {
            return $query->get();
        }

        if ($user->hasRole('faculty') && $user->facultyProfile) {
            return $query
                ->where('faculty_profile_id', $user->facultyProfile->id)
                ->get();
        }

        if ($user->hasRole('student')) {
            return $query
                ->whereHas('enrollments', function ($enrollmentQuery) use ($user): void {
                    $enrollmentQuery
                        ->where('user_id', $user->id)
                        ->whereIn('status', ['active', 'enrolled']);
                })
                ->get();
        }

        return collect();
    }

    #[Computed]
    public function selectedCourse(): ?Course
    {
        if (! $this->course_id) {
            return null;
        }

        return $this->availableCourses->firstWhere('id', (int) $this->course_id);
    }

    #[Computed]
    public function canManageSelectedCourse(): bool
    {
        $course = $this->selectedCourse;

        if (! $course) {
            return false;
        }

        return Gate::forUser(auth()->user())->check('update', $course);
    }

    #[Computed]
    public function quizzes()
    {
        if (! $this->selectedCourse) {
            return collect();
        }

        return $this->selectedCourse->quizzes()
            ->withCount('responses')
            ->withCount('questions')
            ->with(['responses.student', 'questions'])
            ->orderBy('display_order')
            ->orderBy('created_at')
            ->get();
    }

    #[Computed]
    public function myResponsesByQuiz()
    {
        if (! $this->selectedCourse) {
            return collect();
        }

        return QuizResponse::query()
            ->where('user_id', auth()->id())
            ->whereHas('quiz', fn ($query) => $query->where('course_id', $this->selectedCourse->id))
            ->get()
            ->keyBy('quiz_id');
    }

    public function updatedCourseId(): void
    {
        $this->quizResponseScores = [];
        $this->selected_quiz_id = null;

        $firstQuiz = $this->quizzes->first();
        if ($firstQuiz) {
            $this->selected_quiz_id = (int) $firstQuiz->id;
        }
    }

    #[Computed]
    public function selectedQuiz(): ?Quiz
    {
        if (! $this->selected_quiz_id) {
            return null;
        }

        return $this->quizzes->firstWhere('id', (int) $this->selected_quiz_id);
    }

    #[Computed]
    public function selectedQuizResponses()
    {
        if (! $this->selectedQuiz) {
            return collect();
        }

        return $this->selectedQuiz->responses;
    }

    #[Computed]
    public function selectedQuizQuestions()
    {
        if (! $this->selectedQuiz) {
            return collect();
        }

        return $this->selectedQuiz->questions;
    }

    public function openGradingPanel(int $quizId): void
    {
        $quiz = Quiz::query()
            ->whereHas('course', fn ($query) => $query->whereIn('id', $this->availableCourses->pluck('id')->all()))
            ->findOrFail($quizId);

        Gate::authorize('update', $quiz);

        $this->selected_quiz_id = $quiz->id;
    }

    public function createQuiz(): void
    {
        $course = $this->selectedCourse;

        if (! $course) {
            abort(403);
        }

        Gate::authorize('update', $course);

        $validated = $this->validate([
            'quiz_title' => ['required', 'string', 'max:150'],
            'quiz_description' => ['nullable', 'string', 'max:4000'],
            'quiz_max_score' => ['required', 'numeric', 'min:1', 'max:999.99'],
            'quiz_pass_score' => ['nullable', 'numeric', 'min:0'],
            'quiz_time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:720'],
            'quiz_show_results_immediately' => ['required', 'boolean'],
        ]);

        $nextDisplayOrder = ((int) Quiz::query()->where('course_id', $course->id)->max('display_order')) + 1;

        $quiz = Quiz::query()->create([
            'course_id' => $course->id,
            'title' => $validated['quiz_title'],
            'description' => $validated['quiz_description'] !== '' ? $validated['quiz_description'] : null,
            'max_score' => $validated['quiz_max_score'],
            'time_limit_minutes' => $validated['quiz_time_limit_minutes'] !== '' ? (int) $validated['quiz_time_limit_minutes'] : null,
            'pass_score' => $validated['quiz_pass_score'] !== '' ? $validated['quiz_pass_score'] : null,
            'show_results_immediately' => (bool) $validated['quiz_show_results_immediately'],
            'display_order' => $nextDisplayOrder,
        ]);

        $this->reset(['quiz_title', 'quiz_description', 'quiz_pass_score', 'quiz_time_limit_minutes']);
        $this->quiz_max_score = '100';
        $this->quiz_show_results_immediately = true;
        $this->selected_quiz_id = $quiz->id;

        $this->successToast(__('Quiz created successfully.'));
    }

    public function addObjectiveQuestion(): void
    {
        if (! $this->selectedQuiz) {
            abort(403);
        }

        Gate::authorize('update', $this->selectedQuiz);

        $validated = $this->validate([
            'question_prompt' => ['required', 'string', 'max:2000'],
            'question_points' => ['required', 'numeric', 'min:0.25', 'max:100'],
            'question_options' => ['required', 'array', 'min:2', 'max:8'],
            'question_options.*' => ['required', 'string', 'max:255'],
            'question_correct_options' => ['required', 'array', 'min:1'],
            'question_correct_options.*' => ['required', 'integer', 'min:0'],
        ]);

        $normalizedOptions = collect($validated['question_options'])
            ->map(fn (string $option): string => trim($option))
            ->filter(fn (string $option): bool => $option !== '')
            ->values();

        if ($normalizedOptions->count() < 2) {
            $this->addError('question_options', __('At least two options are required.'));

            return;
        }

        if ($normalizedOptions->count() !== $normalizedOptions->unique()->count()) {
            $this->addError('question_options', __('Options must be unique.'));

            return;
        }

        $correctOptionIndexes = collect($validated['question_correct_options'])
            ->map(fn ($index): int => (int) $index)
            ->unique()
            ->sort()
            ->values();

        $maxIndex = $normalizedOptions->count() - 1;
        $invalidIndex = $correctOptionIndexes->first(fn (int $index): bool => $index < 0 || $index > $maxIndex);

        if ($invalidIndex !== null) {
            $this->addError('question_correct_options', __('Correct option selection is invalid.'));

            return;
        }

        $displayOrder = ((int) QuizQuestion::query()->where('quiz_id', $this->selectedQuiz->id)->max('display_order')) + 1;

        QuizQuestion::query()->create([
            'quiz_id' => $this->selectedQuiz->id,
            'question_type' => 'objective',
            'prompt' => trim($validated['question_prompt']),
            'allows_multiple' => (bool) $this->question_allows_multiple,
            'options' => $normalizedOptions->all(),
            'correct_options' => $correctOptionIndexes->all(),
            'points' => $validated['question_points'],
            'display_order' => $displayOrder,
        ]);

        $this->reset(['question_prompt', 'question_allows_multiple', 'question_points', 'question_correct_options']);
        $this->question_points = '1';
        $this->question_options = ['', '', '', ''];

        $this->successToast(__('Objective question added successfully.'));
    }

    public function addTheoryQuestion(): void
    {
        if (! $this->selectedQuiz) {
            abort(403);
        }

        Gate::authorize('update', $this->selectedQuiz);

        $validated = $this->validate([
            'theory_question_prompt' => ['required', 'string', 'max:4000'],
            'theory_question_points' => ['required', 'numeric', 'min:0.25', 'max:100'],
            'theory_question_rubric' => ['nullable', 'string', 'max:4000'],
        ]);

        $displayOrder = ((int) QuizQuestion::query()->where('quiz_id', $this->selectedQuiz->id)->max('display_order')) + 1;

        QuizQuestion::query()->create([
            'quiz_id' => $this->selectedQuiz->id,
            'question_type' => 'theory',
            'prompt' => trim($validated['theory_question_prompt']),
            'rubric_text' => $validated['theory_question_rubric'] !== '' ? $validated['theory_question_rubric'] : null,
            'allows_multiple' => false,
            'options' => [],
            'correct_options' => [],
            'points' => $validated['theory_question_points'],
            'display_order' => $displayOrder,
        ]);

        $this->reset(['theory_question_prompt', 'theory_question_points', 'theory_question_rubric']);
        $this->theory_question_points = '1';

        $this->successToast(__('Theory question added successfully.'));
    }

    public function submitQuizAttempt(int $quizId): void
    {
        $quiz = Quiz::query()
            ->whereHas('course', fn ($query) => $query->whereIn('id', $this->availableCourses->pluck('id')->all()))
            ->with('questions')
            ->findOrFail($quizId);

        Gate::authorize('view', $quiz);

        if (! auth()->user()->studentProfile()->exists()) {
            abort(403);
        }

        $isEnrolled = Enrollment::query()
            ->where('course_id', $quiz->course_id)
            ->where('user_id', auth()->id())
            ->whereIn('status', ['active', 'enrolled'])
            ->exists();

        if (! $isEnrolled) {
            abort(403);
        }

        $questions = $quiz->questions->values();

        if ($questions->isEmpty()) {
            $this->addError('attemptAnswers', __('This quiz has no questions configured yet.'));

            return;
        }

        $answers = $this->attemptAnswers[$quizId] ?? [];
        $payload = [];
        $totalAwarded = 0.0;
        $totalPossible = (float) $questions->sum(fn (QuizQuestion $question): float => (float) $question->points);
        $hasTheoryQuestions = false;

        foreach ($questions as $question) {
            $rawAnswer = $answers[$question->id] ?? null;

            if ($question->question_type === 'theory') {
                $hasTheoryQuestions = true;
                $answerText = trim((string) $rawAnswer);

                if ($answerText === '') {
                    $this->addError("attemptAnswers.$quizId.$question->id", __('Please answer all questions before submitting.'));

                    return;
                }

                $payload[(string) $question->id] = [
                    'question_type' => 'theory',
                    'answer_text' => $answerText,
                    'awarded_points' => null,
                    'graded' => false,
                    'feedback' => null,
                ];

                continue;
            }

            if ($question->allows_multiple) {
                $selected = collect(is_array($rawAnswer) ? $rawAnswer : [])
                    ->filter(fn ($value): bool => (bool) $value)
                    ->keys()
                    ->map(fn ($value): int => (int) $value)
                    ->sort()
                    ->values();
            } else {
                if ($rawAnswer === null || $rawAnswer === '') {
                    $this->addError("attemptAnswers.$quizId.$question->id", __('Please answer all questions before submitting.'));

                    return;
                }

                $selected = collect([(int) $rawAnswer]);
            }

            if ($selected->isEmpty()) {
                $this->addError("attemptAnswers.$quizId.$question->id", __('Please answer all questions before submitting.'));

                return;
            }

            $correct = collect($question->correct_options ?? [])->map(fn ($value): int => (int) $value)->sort()->values();
            $isCorrect = $selected->values()->all() === $correct->values()->all();
            $awardedPoints = $isCorrect ? (float) $question->points : 0.0;

            $payload[(string) $question->id] = [
                'question_type' => 'objective',
                'selected_options' => $selected->values()->all(),
                'correct_options' => $correct->values()->all(),
                'is_correct' => $isCorrect,
                'awarded_points' => round($awardedPoints, 2),
            ];

            $totalAwarded += $awardedPoints;
        }

        $score = $totalPossible > 0
            ? round(($totalAwarded / $totalPossible) * (float) $quiz->max_score, 2)
            : 0.0;

        $gradingStatus = $hasTheoryQuestions ? 'pending_manual' : 'graded';

        $response = QuizResponse::query()->updateOrCreate(
            [
                'quiz_id' => $quiz->id,
                'user_id' => auth()->id(),
            ],
            [
                'response_data' => [
                    'answers' => $payload,
                    'grading_status' => $gradingStatus,
                    'total_awarded_points' => round($totalAwarded, 2),
                    'total_possible_points' => round($totalPossible, 2),
                ],
                'score' => $score,
                'submitted_at' => now(),
                'time_taken_seconds' => null,
                'is_passed' => (! $hasTheoryQuestions && $quiz->pass_score !== null)
                    ? $score >= (float) $quiz->pass_score
                    : null,
            ],
        );

        if (! $hasTheoryQuestions) {
            $this->syncQuizAggregateGradeForStudent((int) auth()->id(), (int) $quiz->course_id);

            AssessmentLog::query()->updateOrCreate(
                [
                    'user_id' => $response->user_id,
                    'course_id' => $quiz->course_id,
                    'assessment_type' => 'quiz',
                    'assessment_name' => 'Quiz: '.$quiz->title,
                ],
                [
                    'score' => $response->score,
                    'max_score' => $quiz->max_score,
                    'assessed_by' => auth()->id(),
                    'assessed_at' => now(),
                    'notes' => null,
                    'created_at' => now(),
                ],
            );
        }

        $this->successToast($hasTheoryQuestions
            ? __('Quiz submitted. Waiting for instructor to grade theory answers.')
            : __('Quiz submitted and auto-graded successfully.'));
    }

    public function submitObjectiveAttempt(int $quizId): void
    {
        $this->submitQuizAttempt($quizId);
    }

    public function gradeTheoryResponse(int $responseId, int $questionId): void
    {
        $response = QuizResponse::query()
            ->whereHas('quiz', fn ($query) => $query->whereIn('course_id', $this->availableCourses->pluck('id')->all()))
            ->with(['quiz.questions'])
            ->findOrFail($responseId);

        Gate::authorize('update', $response->quiz);

        $question = $response->quiz->questions->firstWhere('id', $questionId);

        if (! $question || $question->question_type !== 'theory') {
            abort(404);
        }

        $scoreInput = $this->theoryQuestionScores[$responseId][$questionId] ?? null;
        $feedbackInput = $this->theoryQuestionFeedbacks[$responseId][$questionId] ?? null;

        $validated = validator([
            'score' => $scoreInput,
            'feedback' => $feedbackInput,
        ], [
            'score' => ['required', 'numeric', 'min:0', 'max:'.$question->points],
            'feedback' => ['nullable', 'string', 'max:4000'],
        ])->validate();

        DB::transaction(function () use ($response, $validated, $responseId, $questionId): void {
            $data = is_array($response->response_data) ? $response->response_data : [];
            $answers = is_array($data['answers'] ?? null) ? $data['answers'] : [];

            if (! isset($answers[(string) $questionId]) || ! is_array($answers[(string) $questionId])) {
                $answers[(string) $questionId] = [
                    'question_type' => 'theory',
                    'answer_text' => null,
                    'awarded_points' => null,
                    'graded' => false,
                    'feedback' => null,
                ];
            }

            $answers[(string) $questionId]['awarded_points'] = round((float) $validated['score'], 2);
            $answers[(string) $questionId]['graded'] = true;
            $answers[(string) $questionId]['feedback'] = $validated['feedback'] !== '' ? $validated['feedback'] : null;

            $totalPossible = (float) $response->quiz->questions->sum(fn (QuizQuestion $quizQuestion): float => (float) $quizQuestion->points);
            $totalAwarded = 0.0;
            $hasPendingTheory = false;

            foreach ($response->quiz->questions as $quizQuestion) {
                $entry = $answers[(string) $quizQuestion->id] ?? null;

                if (! is_array($entry)) {
                    if ($quizQuestion->question_type === 'theory') {
                        $hasPendingTheory = true;
                    }

                    continue;
                }

                $awardedPoints = $entry['awarded_points'] ?? null;

                if ($quizQuestion->question_type === 'theory' && ! ($entry['graded'] ?? false)) {
                    $hasPendingTheory = true;
                }

                if (is_numeric($awardedPoints)) {
                    $totalAwarded += (float) $awardedPoints;
                }
            }

            $gradingStatus = $hasPendingTheory ? 'pending_manual' : 'graded';
            $score = $totalPossible > 0
                ? round(($totalAwarded / $totalPossible) * (float) $response->quiz->max_score, 2)
                : 0.0;

            $data['answers'] = $answers;
            $data['grading_status'] = $gradingStatus;
            $data['total_awarded_points'] = round($totalAwarded, 2);
            $data['total_possible_points'] = round($totalPossible, 2);

            $response->update([
                'response_data' => $data,
                'score' => $score,
                'is_passed' => ($gradingStatus === 'graded' && $response->quiz->pass_score !== null)
                    ? $score >= (float) $response->quiz->pass_score
                    : null,
            ]);

            if ($gradingStatus === 'graded') {
                AssessmentLog::query()->updateOrCreate(
                    [
                        'user_id' => $response->user_id,
                        'course_id' => $response->quiz->course_id,
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

                $this->syncQuizAggregateGradeForStudent((int) $response->user_id, (int) $response->quiz->course_id);
            }

            $this->theoryQuestionScores[$responseId][$questionId] = (string) round((float) $validated['score'], 2);
            $this->theoryQuestionFeedbacks[$responseId][$questionId] = $answers[(string) $questionId]['feedback'];
        });

        $this->successToast(__('Theory response graded successfully.'));
    }

    public function gradeQuizResponse(int $responseId): void
    {
        $response = QuizResponse::query()
            ->whereHas('quiz', fn ($query) => $query->whereIn('course_id', $this->availableCourses->pluck('id')->all()))
            ->with('quiz')
            ->findOrFail($responseId);

        $studentEnrolled = Enrollment::query()
            ->where('course_id', $response->quiz->course_id)
            ->where('user_id', $response->user_id)
            ->whereIn('status', ['active', 'enrolled'])
            ->exists();

        if (! $studentEnrolled) {
            abort(403);
        }

        Gate::authorize('update', $response->quiz);

        $scoreInput = $this->quizResponseScores[$responseId] ?? $response->score;

        $validated = validator([
            'score' => $scoreInput,
        ], [
            'score' => ['required', 'numeric', 'min:0', 'max:'.$response->quiz->max_score],
        ])->validate();

        DB::transaction(function () use ($response, $validated, $responseId): void {
            $response->update([
                'score' => round((float) $validated['score'], 2),
                'is_passed' => $response->quiz->pass_score !== null
                    ? (float) $validated['score'] >= (float) $response->quiz->pass_score
                    : null,
            ]);

            AssessmentLog::query()->updateOrCreate(
                [
                    'user_id' => $response->user_id,
                    'course_id' => $response->quiz->course_id,
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

            $this->syncQuizAggregateGradeForStudent((int) $response->user_id, (int) $response->quiz->course_id);

            $this->quizResponseScores[$responseId] = (string) $response->score;
        });

        $this->successToast(__('Quiz scored successfully.'));
    }

    protected function syncQuizAggregateGradeForStudent(int $studentId, int $courseId): void
    {
        $responses = QuizResponse::query()
            ->where('user_id', $studentId)
            ->whereNotNull('score')
            ->whereHas('quiz', fn ($query) => $query->where('course_id', $courseId))
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
            'course_id' => $courseId,
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
}; ?>

<div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
    <x-ui.toast :message="$toastMessage" :variant="$toastVariant" />

    <div>
        <div class="text-sm text-zinc-500">
            <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 hover:underline dark:hover:text-zinc-200">{{ __('Dashboard') }}</a>
            <span class="mx-2">/</span>
            <span>{{ __('Quizzes') }}</span>
        </div>
        <flux:heading size="xl" class="mt-2">{{ __('Quiz Module') }}</flux:heading>
        <flux:subheading>{{ __('Instructor quiz list, grading panel, and student quiz view by course.') }}</flux:subheading>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:select wire:model.live="course_id" :label="__('Course')" required>
            <option value="">{{ __('Select course') }}</option>
            @foreach ($this->availableCourses as $course)
                <option value="{{ $course->id }}">{{ $course->title }} ({{ $course->code }})</option>
            @endforeach
        </flux:select>

        @if (! $this->selectedCourse)
            <p class="mt-4 text-sm text-zinc-500">{{ __('No accessible course selected.') }}</p>
        @endif
    </div>

    @if ($this->selectedCourse)
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Course Overview') }}</h2>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $this->selectedCourse->title }} · {{ $this->selectedCourse->code }}</p>
        </div>

        @if ($this->canManageSelectedCourse)
            <form wire:submit="createQuiz" class="space-y-4 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Create Quiz') }}</flux:heading>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="quiz_title" :label="__('Title')" type="text" required />
                    <flux:input wire:model="quiz_max_score" :label="__('Max Score')" type="number" step="0.01" min="1" required />
                    <flux:input wire:model="quiz_pass_score" :label="__('Pass Score')" type="number" step="0.01" min="0" />
                    <flux:input wire:model="quiz_time_limit_minutes" :label="__('Time Limit (minutes)')" type="number" min="1" />
                </div>

                <div>
                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Description') }}</label>
                    <textarea wire:model="quiz_description" rows="4" class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm outline-none ring-indigo-500 focus:ring dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"></textarea>
                </div>

                <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-200">
                    <input wire:model="quiz_show_results_immediately" type="checkbox" class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500" />
                    {{ __('Show results immediately to students') }}
                </label>

                <flux:button variant="primary" type="submit">{{ __('Create Quiz') }}</flux:button>
            </form>

            @if ($this->selectedQuiz)
                <form wire:submit="addObjectiveQuestion" class="space-y-4 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="lg">{{ __('Objective Question Builder') }}</flux:heading>
                    <flux:subheading>{{ __('Adding questions to: :quiz', ['quiz' => $this->selectedQuiz->title]) }}</flux:subheading>

                    <div>
                        <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Question Prompt') }}</label>
                        <textarea wire:model="question_prompt" rows="3" class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm outline-none ring-indigo-500 focus:ring dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"></textarea>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <flux:input wire:model="question_points" :label="__('Points')" type="number" step="0.25" min="0.25" required />
                        <label class="flex items-center gap-2 pt-7 text-sm text-zinc-700 dark:text-zinc-200">
                            <input wire:model="question_allows_multiple" type="checkbox" class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500" />
                            {{ __('Allow multiple correct answers') }}
                        </label>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        @foreach ($question_options as $index => $option)
                            <div class="space-y-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                <flux:input wire:model="question_options.{{ $index }}" :label="__('Option :n', ['n' => $index + 1])" type="text" required />
                                <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-200">
                                    <input wire:model="question_correct_options" type="checkbox" value="{{ $index }}" class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500" />
                                    {{ __('Mark as correct') }}
                                </label>
                            </div>
                        @endforeach
                    </div>

                    <flux:button variant="primary" type="submit">{{ __('Add Objective Question') }}</flux:button>
                </form>

                <form wire:submit="addTheoryQuestion" class="space-y-4 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="lg">{{ __('Theory Question Builder') }}</flux:heading>
                    <flux:subheading>{{ __('Add short/long answer theory questions requiring manual grading.') }}</flux:subheading>

                    <div>
                        <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Theory Prompt') }}</label>
                        <textarea wire:model="theory_question_prompt" rows="3" class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm outline-none ring-indigo-500 focus:ring dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"></textarea>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <flux:input wire:model="theory_question_points" :label="__('Points')" type="number" step="0.25" min="0.25" required />
                        <div>
                            <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Grading rubric (optional)') }}</label>
                            <textarea wire:model="theory_question_rubric" rows="2" class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm outline-none ring-indigo-500 focus:ring dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"></textarea>
                        </div>
                    </div>

                    <flux:button variant="primary" type="submit">{{ __('Add Theory Question') }}</flux:button>
                </form>
            @endif

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="lg">{{ __('Instructor Quiz List') }}</flux:heading>

                    <div class="mt-4 space-y-3">
                        @forelse ($this->quizzes as $quiz)
                            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-medium text-zinc-900 dark:text-zinc-100">{{ $quiz->title }}</h3>
                                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ __('Max :max · :questions questions · :responses responses', ['max' => $quiz->max_score, 'questions' => $quiz->questions_count, 'responses' => $quiz->responses_count]) }}
                                        </p>
                                    </div>
                                    <flux:button size="sm" variant="ghost" wire:click="openGradingPanel({{ $quiz->id }})">
                                        {{ __('Open') }}
                                    </flux:button>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-zinc-500">{{ __('No quizzes created for this course yet.') }}</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="lg">{{ __('Response Grading Panel') }}</flux:heading>

                    @if ($this->selectedQuiz)
                        <div class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">
                            {{ __('Quiz: :title (Max :max)', ['title' => $this->selectedQuiz->title, 'max' => $this->selectedQuiz->max_score]) }}
                        </div>

                        <div class="mt-4 space-y-4">
                            @forelse ($this->selectedQuizResponses as $response)
                                @php($responseData = is_array($response->response_data) ? $response->response_data : [])
                                @php($responseAnswers = is_array($responseData['answers'] ?? null) ? $responseData['answers'] : [])
                                @php($theoryQuestions = $this->selectedQuizQuestions->where('question_type', 'theory')->values())

                                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $response->student?->name ?? __('Unknown') }}</div>
                                            <div class="text-xs text-zinc-500">{{ optional($response->submitted_at)->format('M d, Y H:i') }}</div>
                                        </div>
                                        <div class="text-xs text-zinc-500">
                                            {{ __('Status: :status', ['status' => $responseData['grading_status'] ?? 'graded']) }}
                                        </div>
                                    </div>

                                    <div class="mt-3 flex items-end gap-3">
                                        <div>
                                            <label class="text-xs text-zinc-500">{{ __('Overall Score') }}</label>
                                            <input
                                                wire:model="quizResponseScores.{{ $response->id }}"
                                                type="number"
                                                min="0"
                                                max="{{ $this->selectedQuiz->max_score }}"
                                                step="0.01"
                                                class="mt-1 w-28 rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                                                placeholder="{{ $response->score ?? '0' }}"
                                            />
                                        </div>
                                        <flux:button size="sm" variant="primary" wire:click="gradeQuizResponse({{ $response->id }})">
                                            {{ __('Save Overall') }}
                                        </flux:button>
                                    </div>

                                    @if ($theoryQuestions->isNotEmpty())
                                        <div class="mt-4 space-y-3 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                                            <div class="text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ __('Theory Answers') }}</div>

                                            @foreach ($theoryQuestions as $question)
                                                @php($entry = $responseAnswers[(string) $question->id] ?? null)
                                                <div class="rounded-md border border-zinc-200 p-3 dark:border-zinc-700">
                                                    <div class="text-sm font-medium text-zinc-800 dark:text-zinc-100">
                                                        {{ $question->prompt }}
                                                        <span class="text-xs text-zinc-500">({{ $question->points }} {{ __('pts') }})</span>
                                                    </div>

                                                    @if ($question->rubric_text)
                                                        <div class="mt-1 text-xs text-zinc-500">{{ __('Rubric: :rubric', ['rubric' => $question->rubric_text]) }}</div>
                                                    @endif

                                                    <div class="mt-2 rounded bg-zinc-50 p-2 text-sm text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                                                        {{ $entry['answer_text'] ?? __('No answer submitted.') }}
                                                    </div>

                                                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                                                        <flux:input
                                                            wire:model="theoryQuestionScores.{{ $response->id }}.{{ $question->id }}"
                                                            :label="__('Awarded Points')"
                                                            type="number"
                                                            step="0.25"
                                                            min="0"
                                                            max="{{ $question->points }}"
                                                            placeholder="{{ $entry['awarded_points'] ?? '0' }}"
                                                        />
                                                        <div>
                                                            <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Feedback (optional)') }}</label>
                                                            <textarea wire:model="theoryQuestionFeedbacks.{{ $response->id }}.{{ $question->id }}" rows="2" class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm outline-none ring-indigo-500 focus:ring dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"></textarea>
                                                        </div>
                                                    </div>

                                                    <div class="mt-3 text-right">
                                                        <flux:button size="sm" variant="primary" wire:click="gradeTheoryResponse({{ $response->id }}, {{ $question->id }})">
                                                            {{ __('Save Theory Grade') }}
                                                        </flux:button>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <p class="text-sm text-zinc-500">{{ __('No responses yet for the selected quiz.') }}</p>
                            @endforelse
                        </div>
                    @else
                        <p class="mt-3 text-sm text-zinc-500">{{ __('Select a quiz from the instructor list to open grading.') }}</p>
                    @endif
                </div>
            </div>
        @else
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Student Quiz View') }}</flux:heading>

                <div class="mt-4 space-y-4">
                    @forelse ($this->quizzes as $quiz)
                        @php($myResponse = $this->myResponsesByQuiz->get($quiz->id))
                        @php($myResponseData = is_array($myResponse?->response_data) ? $myResponse->response_data : [])
                        @php($allQuestions = $quiz->questions->values())

                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="font-medium text-zinc-900 dark:text-zinc-100">{{ $quiz->title }}</h3>
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $quiz->description ?: __('No description provided.') }}</p>
                                </div>
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Max: :max · :count questions', ['max' => $quiz->max_score, 'count' => $allQuestions->count()]) }}
                                </div>
                            </div>

                            @if ($allQuestions->isNotEmpty())
                                <form wire:submit.prevent="submitQuizAttempt({{ $quiz->id }})" class="mt-4 space-y-4">
                                    @foreach ($allQuestions as $question)
                                        <div wire:key="quiz-{{ $quiz->id }}-question-{{ $question->id }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                            <div class="text-sm font-medium text-zinc-800 dark:text-zinc-100">
                                                {{ $loop->iteration }}. {{ $question->prompt }}
                                                <span class="text-xs text-zinc-500">({{ $question->points }} {{ __('pts') }})</span>
                                            </div>

                                            @if ($question->question_type === 'theory')
                                                <div class="mt-2">
                                                    <textarea wire:model="attemptAnswers.{{ $quiz->id }}.{{ $question->id }}" rows="4" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm outline-none ring-indigo-500 focus:ring dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100" placeholder="{{ __('Type your answer...') }}"></textarea>
                                                </div>
                                            @else
                                                <div class="mt-2 space-y-2">
                                                    @foreach ($question->options as $optionIndex => $optionText)
                                                        <label wire:key="quiz-{{ $quiz->id }}-question-{{ $question->id }}-option-{{ $optionIndex }}" class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-200">
                                                            @if ($question->allows_multiple)
                                                                <input
                                                                    type="checkbox"
                                                                    wire:model="attemptAnswers.{{ $quiz->id }}.{{ $question->id }}.{{ $optionIndex }}"
                                                                    class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500"
                                                                />
                                                            @else
                                                                <input
                                                                    type="radio"
                                                                    wire:model="attemptAnswers.{{ $quiz->id }}.{{ $question->id }}"
                                                                    value="{{ $optionIndex }}"
                                                                    class="border-zinc-300 text-indigo-600 focus:ring-indigo-500"
                                                                />
                                                            @endif
                                                            <span>{{ $optionText }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach

                                    <flux:button size="sm" variant="primary" type="submit">{{ __('Submit Quiz') }}</flux:button>
                                </form>
                            @endif

                            <div class="mt-3 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800">
                                <div class="text-zinc-600 dark:text-zinc-300">
                                    {{ __('Your score: :score', ['score' => $myResponse?->score ?? __('Not graded yet')]) }}
                                </div>
                                <div class="mt-1 text-zinc-500 dark:text-zinc-400">
                                    {{ __('Submitted: :date', ['date' => $myResponse?->submitted_at?->format('M d, Y H:i') ?? __('Not submitted')]) }}
                                </div>
                                <div class="mt-1 text-zinc-500 dark:text-zinc-400">
                                    {{ __('Status: :status', ['status' => $myResponseData['grading_status'] ?? ($myResponse?->is_passed === null ? __('Pending') : ($myResponse->is_passed ? __('Passed') : __('Not passed')))]) }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-zinc-500">{{ __('No quizzes created for this course yet.') }}</p>
                    @endforelse
                </div>
            </div>
        @endif
    @endif
</div>
