<div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
    <div class="flex items-center justify-between gap-2">
        <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Assignments') }}</h2>
        <flux:button
            size="sm"
            variant="ghost"
            wire:click="toggleAssignmentsCollapsed"
            :icon="$is_assignments_collapsed ? 'chevron-down' : 'chevron-up'"
        >
            {{ $is_assignments_collapsed ? __('Expand') : __('Collapse') }}
        </flux:button>
    </div>

    @if (! $is_assignments_collapsed)
        @if ($this->canAccessLearningContent)
            @if ($this->canManageCourse && auth()->user()->can('assignments.manage'))
                <form wire:submit="createAssignment" class="mt-4 grid gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700 md:grid-cols-2">
                    <flux:input wire:model="assignment_title" :label="__('Title')" type="text" required />
                    <flux:input wire:model="assignment_due_date" :label="__('Due date')" type="datetime-local" required />
                    <flux:input wire:model="assignment_max_score" :label="__('Max score')" type="number" min="1" max="999.99" step="0.01" required />
                    <div class="md:col-span-2 space-y-1">
                        <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Description') }}</label>
                        <textarea
                            wire:model="assignment_description"
                            rows="4"
                            required
                            class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm outline-none ring-indigo-500 focus:ring dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                        ></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <flux:button variant="primary" type="submit">{{ __('Create assignment') }}</flux:button>
                    </div>
                </form>
            @endif

            @if ($this->isEnrolled && auth()->user()->studentProfile()->exists())
                <form wire:submit="submitAssignment" class="mt-4 grid gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700 md:grid-cols-2">
                    <flux:select wire:model="submission_assignment_id" :label="__('Assignment')" required>
                        <option value="">{{ __('Select assignment') }}</option>
                        @foreach ($this->assignments as $assignmentOption)
                            <option value="{{ $assignmentOption->id }}">{{ $assignmentOption->title }}</option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="submission_file" :label="__('Submission file')" type="file" required />
                    <div class="md:col-span-2">
                        <flux:button variant="primary" type="submit">{{ __('Submit assignment') }}</flux:button>
                    </div>
                </form>
            @endif

            <div class="mt-4 space-y-3">
                @forelse ($this->assignments as $assignment)
                    @php
                        $mySubmission = $this->myAssignmentSubmissions->get($assignment->id);
                    @endphp
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $assignment->title }}</h3>
                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $assignment->description }}</p>
                                <div class="mt-2 text-xs text-zinc-500">
                                    {{ __('Due: :due · Max score: :max', ['due' => $assignment->due_date?->format('M d, Y h:i A'), 'max' => $assignment->max_score]) }}
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($this->canManageCourse)
                                    <flux:badge>{{ __('Submissions: :count', ['count' => $assignment->submissions_count]) }}</flux:badge>
                                @endif

                                @if ($mySubmission)
                                    <flux:badge :variant="$mySubmission->is_late ? 'warning' : 'success'">
                                        {{ $mySubmission->is_late ? __('Submitted late') : __('Submitted') }}
                                    </flux:badge>

                                    @if ($mySubmission->file_path)
                                        <flux:button
                                            size="xs"
                                            variant="ghost"
                                            icon="arrow-down-tray"
                                            :href="route('courses.assignments.submissions.download', ['course' => $this->course->id, 'submission' => $mySubmission->id])"
                                        >
                                            {{ __('My File') }}
                                        </flux:button>
                                    @endif

                                    @if ($mySubmission->score !== null)
                                        <flux:badge variant="success">{{ __('Score: :score', ['score' => $mySubmission->score]) }}</flux:badge>
                                    @endif
                                @endif

                                @if ($this->canManageCourse && auth()->user()->can('assignments.manage'))
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        wire:click="deleteAssignment({{ $assignment->id }})"
                                        wire:confirm="{{ __('Delete this assignment and all submissions?') }}"
                                    >
                                        {{ __('Delete') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>

                        @if ($this->canManageCourse && auth()->user()->can('assignments.manage'))
                            <div class="mt-4 space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                                <h4 class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Submission Grading') }}</h4>

                                @forelse ($assignment->submissions as $submission)
                                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                            <div class="text-sm">
                                                <div class="font-medium text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                                                    <span>{{ $submission->student?->name }}</span>
                                                    @if ($submission->student?->studentProfile?->student_number)
                                                        <span class="font-mono text-xs text-slate-500 dark:text-slate-400 tabular-nums font-semibold">({{ $submission->student->studentProfile->student_number }})</span>
                                                    @endif
                                                </div>
                                                <div class="text-xs text-zinc-500">{{ $submission->student?->email }}</div>
                                                <div class="mt-1 text-xs text-zinc-500">
                                                    {{ __('Submitted: :date', ['date' => $submission->submission_date?->format('M d, Y h:i A')]) }}
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                @if ($submission->file_path)
                                                    <flux:button
                                                        size="sm"
                                                        variant="filled"
                                                        icon="arrow-down-tray"
                                                        :href="route('courses.assignments.submissions.download', ['course' => $this->course->id, 'submission' => $submission->id])"
                                                    >
                                                        {{ __('Download File') }}
                                                    </flux:button>
                                                @endif
                                                @if ($submission->is_late)
                                                    <flux:badge variant="warning">{{ __('Late') }}</flux:badge>
                                                @endif
                                                @if ($submission->graded_at)
                                                    <flux:badge variant="success">{{ __('Graded') }}</flux:badge>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="mt-3 grid gap-3 md:grid-cols-3">
                                            <flux:input
                                                wire:model="submissionScores.{{ $submission->id }}"
                                                :label="__('Score (out of :max)', ['max' => $assignment->max_score])"
                                                type="number"
                                                min="0"
                                                max="{{ $assignment->max_score }}"
                                                step="0.01"
                                                placeholder="{{ $submission->score }}"
                                            />

                                            <div class="md:col-span-2 space-y-1">
                                                <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Feedback') }}</label>
                                                <textarea
                                                    wire:model="submissionFeedbacks.{{ $submission->id }}"
                                                    rows="2"
                                                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm outline-none ring-indigo-500 focus:ring dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                                                    placeholder="{{ $submission->feedback ?? __('Optional feedback') }}"
                                                ></textarea>
                                            </div>
                                        </div>

                                        <div class="mt-3 flex justify-end">
                                            <flux:button size="sm" variant="primary" wire:click="gradeAssignmentSubmission({{ $submission->id }})">
                                                {{ __('Save score & feedback') }}
                                            </flux:button>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-sm text-zinc-500">{{ __('No submissions yet for this assignment.') }}</div>
                                @endforelse
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="rounded-xl border border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                        {{ __('No assignments published yet.') }}
                    </div>
                @endforelse
            </div>
        @else
            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-200">
                {{ __('Enroll in this class to access assignments.') }}
            </div>
        @endif
    @endif
</div>
