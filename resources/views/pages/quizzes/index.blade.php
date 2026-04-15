<?php

use App\Livewire\Concerns\HasToastFeedback;
use App\Models\AssessmentLog;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Quiz;
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

    /** @var array<int, string|int|float|null> */
    public array $quizResponseScores = [];

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
            ->with(['responses.student'])
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

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="lg">{{ __('Instructor Quiz List') }}</flux:heading>

                    <div class="mt-4 space-y-3">
                        @forelse ($this->quizzes as $quiz)
                            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-medium text-zinc-900 dark:text-zinc-100">{{ $quiz->title }}</h3>
                                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Max :max · :count responses', ['max' => $quiz->max_score, 'count' => $quiz->responses_count]) }}</p>
                                    </div>
                                    <flux:button size="sm" variant="ghost" wire:click="openGradingPanel({{ $quiz->id }})">
                                        {{ __('Grade Responses') }}
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

                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-zinc-50 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-300">
                                    <tr>
                                        <th class="px-3 py-2 font-medium">{{ __('Student') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Submitted') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Score') }}</th>
                                        <th class="px-3 py-2 font-medium text-right">{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                    @forelse ($this->selectedQuizResponses as $response)
                                        <tr>
                                            <td class="px-3 py-2">{{ $response->student?->name ?? __('Unknown') }}</td>
                                            <td class="px-3 py-2 text-zinc-500">{{ optional($response->submitted_at)->format('M d, Y H:i') }}</td>
                                            <td class="px-3 py-2">
                                                <input
                                                    wire:model="quizResponseScores.{{ $response->id }}"
                                                    type="number"
                                                    min="0"
                                                    max="{{ $this->selectedQuiz->max_score }}"
                                                    step="0.01"
                                                    class="w-28 rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                                                    placeholder="{{ $response->score ?? '0' }}"
                                                />
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                                <flux:button size="sm" variant="primary" wire:click="gradeQuizResponse({{ $response->id }})">
                                                    {{ __('Save') }}
                                                </flux:button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-3 py-6 text-center text-zinc-500">{{ __('No responses yet for the selected quiz.') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
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
                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="font-medium text-zinc-900 dark:text-zinc-100">{{ $quiz->title }}</h3>
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $quiz->description ?: __('No description provided.') }}</p>
                                </div>
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Max: :max', ['max' => $quiz->max_score]) }}
                                </div>
                            </div>

                            <div class="mt-3 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800">
                                <div class="text-zinc-600 dark:text-zinc-300">
                                    {{ __('Your score: :score', ['score' => $myResponse?->score ?? __('Not graded yet')]) }}
                                </div>
                                <div class="mt-1 text-zinc-500 dark:text-zinc-400">
                                    {{ __('Submitted: :date', ['date' => $myResponse?->submitted_at?->format('M d, Y H:i') ?? __('Not submitted')]) }}
                                </div>
                                <div class="mt-1 text-zinc-500 dark:text-zinc-400">
                                    {{ __('Status: :status', ['status' => $myResponse?->is_passed === null ? __('Pending') : ($myResponse->is_passed ? __('Passed') : __('Not passed'))]) }}
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
