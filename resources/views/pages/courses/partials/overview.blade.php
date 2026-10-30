<div class="grid gap-4 lg:grid-cols-3">
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm lg:col-span-3 dark:border-zinc-700 dark:bg-zinc-900">
        <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Class Overview') }}</h2>
        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $course->description ?: __('No description provided yet.') }}</p>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="text-sm text-zinc-500">{{ __('Instructor') }}</div>
                <div class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">{{ $course->facultyProfile?->user?->name ?? __('Unassigned') }}</div>
            </div>

            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="text-sm text-zinc-500">{{ __('Department') }}</div>
                <div class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">{{ $course->department?->name }}</div>
            </div>

            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="text-sm text-zinc-500">{{ __('Capacity') }}</div>
                <div class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">{{ $course->capacity ?? '—' }}</div>
            </div>

            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="text-sm text-zinc-500">{{ __('Enrollment status') }}</div>
                <div class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">
                    {{ $this->isEnrolled ? __('Enrolled') : ($course->is_active ? __('Open') : __('Archived')) }}
                </div>
            </div>
        </div>
    </div>
</div>
