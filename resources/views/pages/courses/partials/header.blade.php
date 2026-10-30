<div class="space-y-4">
    <div class="text-sm text-zinc-500">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 hover:underline dark:hover:text-zinc-200">{{ __('Dashboard') }}</a>
        <span class="mx-2">/</span>
        <a href="{{ route('courses.browse') }}" wire:navigate class="hover:text-zinc-700 hover:underline dark:hover:text-zinc-200">{{ __('Classes') }}</a>
        <span class="mx-2">/</span>
        <span>{{ __('Course Home') }}</span>
    </div>

    <div>
        <flux:heading size="xl">{{ $course->title }}</flux:heading>
        <flux:subheading>{{ $course->department?->name }} · <span class="font-mono uppercase tracking-wider">{{ $course->code }}</span></flux:subheading>

        @if ($this->canManageCourse || $this->isEnrolled)
            <div class="mt-3">
                <flux:button
                    size="sm"
                    variant="ghost"
                    :href="route('quizzes.index', ['course' => $course->id])"
                    wire:navigate
                    icon="question-mark-circle"
                >
                    {{ __('Open Quiz Module') }}
                </flux:button>
            </div>
        @endif
    </div>
</div>
