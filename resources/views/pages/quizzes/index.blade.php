<?php

use App\Livewire\Concerns\HasToastFeedback;
use App\Models\AssessmentLog;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Quiz;
use App\Services\Grading\GradeCalculatorService;
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

        $response = QuizResponse::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', auth()->id())
            ->first();

        if ($response?->submitted_at) {
            $this->addError('attemptAnswers', __('This quiz has already been submitted and is no longer available.'));

            return;
        }

        if (! $response || ! $response->started_at || ($quiz->time_limit_minutes !== null && ! $response->expires_at)) {
            $response = $this->startQuizAttemptRecord($quiz);
        }

        if ($response->expires_at && now()->greaterThan($response->expires_at)) {
            $this->addError('attemptAnswers', __('This quiz timer has expired.'));

            return;
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

        $submittedAt = now();

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
                'started_at' => $response->started_at ?? $submittedAt,
                'expires_at' => $response->expires_at,
                'submitted_at' => $submittedAt,
                'time_taken_seconds' => $response->started_at ? $response->started_at->diffInSeconds($submittedAt) : null,
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

        if (class_exists(\App\Events\QuizCompleted::class)) {
            event(new \App\Events\QuizCompleted($response));
        }

        $this->successToast($hasTheoryQuestions
            ? __('Quiz submitted. Waiting for instructor to grade theory answers.')
            : __('Quiz submitted and auto-graded successfully.'));
    }

    public function startQuizAttempt(int $quizId): void
    {
        $quiz = Quiz::query()
            ->whereHas('course', fn ($query) => $query->whereIn('id', $this->availableCourses->pluck('id')->all()))
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

        $response = $this->startQuizAttemptRecord($quiz);

        if ($response->submitted_at) {
            $this->addError('attemptAnswers', __('This quiz has already been submitted and is no longer available.'));

            return;
        }

        $this->successToast(__('Quiz started. The timer is running.'));
    }

    private function startQuizAttemptRecord(Quiz $quiz): QuizResponse
    {
        $now = now();

        $response = QuizResponse::query()->firstOrCreate(
            [
                'quiz_id' => $quiz->id,
                'user_id' => auth()->id(),
            ],
            [
                'response_data' => [
                    'answers' => [],
                    'grading_status' => 'in_progress',
                ],
                'score' => null,
                'started_at' => $now,
                'expires_at' => $quiz->time_limit_minutes !== null
                    ? $now->copy()->addMinutes((int) $quiz->time_limit_minutes)
                    : null,
                'submitted_at' => null,
                'time_taken_seconds' => null,
                'is_passed' => null,
            ],
        );

        if (! $response->started_at) {
            $response->started_at = $now;
        }

        if ($quiz->time_limit_minutes !== null && ! $response->expires_at) {
            $response->expires_at = $response->started_at->copy()->addMinutes((int) $quiz->time_limit_minutes);
        }

        if ($response->isDirty(['started_at', 'expires_at'])) {
            $response->save();
        }

        return $response->fresh();
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

        $scoresChanged = $grade->isDirty(['quiz_score']);
        app(GradeCalculatorService::class)->recalculate($grade, $scoresChanged);
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
            @include('pages.quizzes.partials.create-quiz-modal')

            @include('pages.quizzes.partials.question-builder')

            <div class="grid gap-4 lg:grid-cols-2">
                @include('pages.quizzes.partials.quiz-list')
                @include('pages.quizzes.partials.grading-panel')
            </div>
        @else
            @include('pages.quizzes.partials.attempt-runner')
        @endif
    @endif
</div>
