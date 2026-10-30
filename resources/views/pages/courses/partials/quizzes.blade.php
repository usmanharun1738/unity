<div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
    <div class="flex items-center justify-between gap-2">
        <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Quizzes') }}</h2>
        <div class="flex items-center gap-2">
            @if ($this->canManageCourse || $this->isEnrolled)
                <flux:button
                    size="sm"
                    variant="ghost"
                    :href="route('quizzes.index', ['course' => $course->id])"
                    wire:navigate
                    icon="arrow-top-right-on-square"
                >
                    {{ __('Open Quiz Module') }}
                </flux:button>
            @endif
            <flux:button
                size="sm"
                variant="ghost"
                wire:click="toggleQuizzesCollapsed"
                :icon="$is_quizzes_collapsed ? 'chevron-down' : 'chevron-up'"
            >
                {{ $is_quizzes_collapsed ? __('Expand') : __('Collapse') }}
            </flux:button>
        </div>
    </div>

    @if (! $is_quizzes_collapsed)
        @if ($this->canAccessLearningContent)
            <div class="mt-4 space-y-3">
                @forelse ($this->quizzes as $quiz)
                    @php
                        $myResponse = $this->myQuizResponses->get($quiz->id);
                    @endphp
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $quiz->title }}</h3>
                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $quiz->description ?: __('No description provided.') }}</p>
                                <div class="mt-2 text-xs text-zinc-500">
                                    {{ __('Max score: :max · Responses: :count', ['max' => $quiz->max_score, 'count' => $quiz->responses_count]) }}
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($myResponse)
                                    @if ($myResponse->score !== null)
                                        <flux:badge variant="success">{{ __('Score: :score / :max', ['score' => $myResponse->score, 'max' => $quiz->max_score]) }}</flux:badge>
                                    @elseif ($myResponse->submitted_at)
                                        <flux:badge variant="warning">{{ __('Submitted (Pending grading)') }}</flux:badge>
                                    @else
                                        <flux:badge>{{ __('In progress') }}</flux:badge>
                                    @endif
                                @endif

                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    :href="route('quizzes.index', ['course' => $course->id])"
                                    wire:navigate
                                >
                                    {{ __('View Quiz') }}
                                </flux:button>
                            </div>
                        </div>

                        @if ($this->canManageCourse && auth()->user()->can('quizzes.manage'))
                            <div class="mt-4 space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                                <h4 class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Response Grading') }}</h4>

                                @forelse ($quiz->responses as $response)
                                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                            <div class="text-sm">
                                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $response->student?->name ?? __('Unknown') }}</div>
                                                <div class="text-xs text-zinc-500">{{ $response->student?->email }}</div>
                                                <div class="mt-1 text-xs text-zinc-500">
                                                    {{ __('Submitted: :date', ['date' => $response->submitted_at?->format('M d, Y h:i A') ?? __('In progress')]) }}
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                @if ($response->score !== null)
                                                    <flux:badge variant="success">{{ __('Graded: :score', ['score' => $response->score]) }}</flux:badge>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="mt-3 flex items-end gap-3">
                                            <div>
                                                <flux:input
                                                    wire:model="quizResponseScores.{{ $response->id }}"
                                                    :label="__('Score (out of :max)', ['max' => $quiz->max_score])"
                                                    type="number"
                                                    min="0"
                                                    max="{{ $quiz->max_score }}"
                                                    step="0.01"
                                                    placeholder="{{ $response->score ?? '0' }}"
                                                />
                                            </div>
                                            <flux:button size="sm" variant="primary" wire:click="gradeQuizResponse({{ $response->id }})">
                                                {{ __('Save Score') }}
                                            </flux:button>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-sm text-zinc-500">{{ __('No responses yet for this quiz.') }}</div>
                                @endforelse
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="rounded-xl border border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                        {{ __('No quizzes published yet.') }}
                    </div>
                @endforelse
            </div>
        @else
            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-200">
                {{ __('Enroll in this class to access quizzes.') }}
            </div>
        @endif
    @endif
</div>
