@if ($this->canManageCourse && auth()->user()->can('attendance.manage'))
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex items-center justify-between gap-2">
            <div>
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Class Attendance') }}</h2>
                <p class="text-xs text-zinc-500">{{ __('Track and record daily attendance for enrolled students.') }}</p>
            </div>
            <flux:button
                size="sm"
                variant="ghost"
                wire:click="toggleAttendanceCollapsed"
                :icon="$is_attendance_collapsed ? 'chevron-down' : 'chevron-up'"
            >
                {{ $is_attendance_collapsed ? __('Expand') : __('Collapse') }}
            </flux:button>
        </div>

        @if (! $is_attendance_collapsed)
            <div class="mt-4 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Session Date:') }}</label>
                        <flux:input wire:model.live="attendance_date" type="date" class="w-44" />
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:button size="sm" variant="filled" wire:click="markAllAttendance('present')">{{ __('Mark All Present') }}</flux:button>
                        <flux:button size="sm" variant="primary" wire:click="saveAttendance">{{ __('Save Attendance') }}</flux:button>
                    </div>
                </div>

                <div class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    @forelse ($this->enrolledStudents as $enrolledStudent)
                        <div class="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                                    <span>{{ $enrolledStudent->name }}</span>
                                    @if ($enrolledStudent->studentProfile?->student_number)
                                        <span class="font-mono text-xs text-slate-500 dark:text-slate-400 tabular-nums font-semibold">({{ $enrolledStudent->studentProfile->student_number }})</span>
                                    @endif
                                </div>
                                <div class="text-xs text-zinc-500">{{ $enrolledStudent->email }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:select wire:model="studentAttendanceStatus.{{ $enrolledStudent->id }}" class="w-36">
                                    <option value="present">{{ __('Present') }}</option>
                                    <option value="late">{{ __('Late') }}</option>
                                    <option value="absent">{{ __('Absent') }}</option>
                                    <option value="excused">{{ __('Excused') }}</option>
                                </flux:select>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-center text-sm text-zinc-500">{{ __('No enrolled students in this class.') }}</div>
                    @endforelse
                </div>
            </div>
        @endif
    </div>
@endif
