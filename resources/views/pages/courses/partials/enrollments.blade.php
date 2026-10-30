@if ($this->canManageCourse)
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Enrollment Management') }}</h2>
            <flux:button
                size="sm"
                variant="ghost"
                wire:click="toggleEnrollmentManagementCollapsed"
                :icon="$is_enrollment_management_collapsed ? 'chevron-down' : 'chevron-up'"
            >
                {{ $is_enrollment_management_collapsed ? __('Expand') : __('Collapse') }}
            </flux:button>
        </div>

        @if (! $is_enrollment_management_collapsed)
            <div class="mt-4 space-y-6">
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <flux:button size="sm" wire:click="generateEnrollmentKey" wire:confirm="{{ __('Generate a new enrollment key? The current key will no longer work.') }}">
                            {{ __('Generate key') }}
                        </flux:button>
                    </div>

                    @if ($course->enrollment_key)
                        <div class="mt-4 flex flex-col gap-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-900/40 dark:bg-emerald-950/30">
                            <div class="text-xs font-medium text-emerald-700 dark:text-emerald-200">{{ __('Current enrollment key') }}</div>
                            <div class="font-mono text-lg font-semibold text-emerald-900 dark:text-emerald-100">{{ $course->enrollment_key }}</div>
                        </div>
                    @else
                        <div class="mt-4 text-sm text-zinc-500">{{ __('No enrollment key generated yet. Generate one to allow students to self-enroll.') }}</div>
                    @endif
                </div>

                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <h3 class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('Add student by email') }}</h3>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('Enroll a student directly without requiring an enrollment key.') }}</p>

                    <form wire:submit="addStudentByEmail" class="mt-4 space-y-3">
                        <flux:input wire:model="add_student_email" :label="__('Student email')" type="email" required />
                        <flux:button variant="primary" type="submit" class="w-full">{{ __('Enroll student') }}</flux:button>
                    </form>
                </div>

                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <h3 class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('Enrolled Students') }}</h3>

                    <div class="mt-4 space-y-2">
                        @forelse ($this->enrolledStudents as $student)
                            <div class="flex flex-col gap-2 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                                        <span>{{ $student->name }}</span>
                                        @if ($student->studentProfile?->student_number)
                                            <span class="font-mono text-xs text-slate-500 dark:text-slate-400 tabular-nums font-semibold">({{ $student->studentProfile->student_number }})</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-zinc-500">{{ $student->email }}</div>
                                </div>
                                @php
                                    $enrollment = $student->enrollments()->where('course_id', $course->id)->first();
                                @endphp
                                @if ($enrollment)
                                    <flux:button size="sm" variant="danger" wire:click="removeStudent({{ $enrollment->id }})" wire:confirm="{{ __('Remove this student from the course?') }}">
                                        {{ __('Remove') }}
                                    </flux:button>
                                @endif
                            </div>
                        @empty
                            <div class="rounded-xl border border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                                {{ __('No students enrolled yet.') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    </div>
@endif
