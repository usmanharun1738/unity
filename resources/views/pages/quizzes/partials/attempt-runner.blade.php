<div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
    <flux:heading size="lg">{{ __('Student Quiz View') }}</flux:heading>

    <div class="mt-4 space-y-4">
        @forelse ($this->quizzes as $quiz)
            @php($myResponse = $this->myResponsesByQuiz->get($quiz->id))
            @php($myResponseData = is_array($myResponse?->response_data) ? $myResponse->response_data : [])
            @php($allQuestions = $quiz->questions->values())
            @php($hasActiveAttempt = $myResponse && ! $myResponse->submitted_at)
            @php($hasSubmittedAttempt = (bool) $myResponse?->submitted_at)
            @php($remainingSeconds = $hasActiveAttempt && $myResponse?->expires_at ? max(0, now()->diffInSeconds($myResponse->expires_at)) : null)

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
                    @if (! $myResponse)
                        <div class="mt-4 rounded-lg border border-dashed border-zinc-300 bg-zinc-50 p-4 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                            <p>{{ __('You have not started this quiz yet.') }}</p>
                            @if ($quiz->time_limit_minutes)
                                <p class="mt-1">{{ __('Time limit: :minutes minutes', ['minutes' => $quiz->time_limit_minutes]) }}</p>
                            @endif
                            <flux:button size="sm" variant="primary" class="mt-3" wire:click="startQuizAttempt({{ $quiz->id }})">
                                {{ __('Start Quiz') }}
                            </flux:button>
                        </div>
                    @elseif ($hasSubmittedAttempt)
                        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-100">
                            {{ __('You have already submitted this quiz. It is no longer available for editing.') }}
                        </div>
                    @elseif ($myResponse->expires_at && $myResponse->expires_at->isPast())
                        <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/20 dark:text-rose-100">
                            {{ __('This quiz timer has expired. Your attempt is locked.') }}
                        </div>
                    @else
                        @if ($myResponse->expires_at)
                            <div class="mt-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200"
                                x-data="{
                                    remaining: {{ $remainingSeconds ?? 0 }},
                                    display: '',
                                    timer: null,
                                    format(value) {
                                        const hours = Math.floor(value / 3600);
                                        const minutes = Math.floor((value % 3600) / 60);
                                        const seconds = value % 60;
                                        return [hours, minutes, seconds].map((part) => String(part).padStart(2, '0')).join(':');
                                    },
                                    tick() {
                                        this.display = this.format(this.remaining);
                                        if (this.remaining > 0) {
                                            this.timer = setTimeout(() => {
                                                this.remaining = Math.max(0, this.remaining - 1);
                                                this.tick();
                                            }, 1000);
                                        }
                                    },
                                }"
                                x-init="tick()"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Time remaining') }}</p>
                                        <p class="mt-1 text-lg font-semibold text-zinc-900 dark:text-zinc-100" x-text="display"></p>
                                    </div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ __('Submit before the timer reaches zero.') }}
                                    </div>
                                </div>
                            </div>
                        @endif

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
