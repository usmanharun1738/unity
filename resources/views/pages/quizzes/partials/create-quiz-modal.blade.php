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
