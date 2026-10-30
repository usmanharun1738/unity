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
