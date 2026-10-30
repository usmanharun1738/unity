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
