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
