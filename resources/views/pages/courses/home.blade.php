<?php

use App\Actions\Courses\EnrollStudentInCourse;
use App\Livewire\Concerns\HasToastFeedback;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Course Home')] class extends Component
{
    use HasToastFeedback;

    public Course $course;

    public string $enrollment_key = '';

    public function mount(Course $course): void
    {
        Gate::authorize('view', $course);
        $this->course = $course->load(['department', 'facultyProfile.user', 'enrollments']);
        $this->pullToastFromSession();
    }

    #[Computed]
    public function isEnrolled(): bool
    {
        return $this->course->enrollments->contains('user_id', auth()->id());
    }

    public function enroll(): void
    {
        Gate::authorize('create', Enrollment::class);

        $validated = $this->validate([
            'enrollment_key' => ['required', 'string', 'max:32'],
        ]);

        try {
            app(EnrollStudentInCourse::class)->handle(auth()->user(), $this->course, $validated['enrollment_key']);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }

            $this->errorToast(__('Enrollment failed. Please check the enrollment key.'));

            return;
        }

        $this->course->refresh()->load(['department', 'facultyProfile.user', 'enrollments']);
        $this->reset('enrollment_key');
        $this->successToast(__('You are now enrolled in this class.'));
    }
}; ?>

<div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
    <x-ui.toast :message="$toastMessage" :variant="$toastVariant" />

    <div class="space-y-4">
        <div class="text-sm text-zinc-500">
            <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 hover:underline dark:hover:text-zinc-200">{{ __('Dashboard') }}</a>
            <span class="mx-2">/</span>
            <a href="{{ route('enrollments.index') }}" wire:navigate class="hover:text-zinc-700 hover:underline dark:hover:text-zinc-200">{{ __('Classes') }}</a>
            <span class="mx-2">/</span>
            <span>{{ __('Course Home') }}</span>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ $course->title }}</flux:heading>
                <flux:subheading>{{ $course->department?->name }} · {{ $course->code }}</flux:subheading>
            </div>

            @if (auth()->user()->hasAnyRole(['admin', 'department-staff']))
                <flux:button variant="ghost" :href="route('courses.show', $course)" wire:navigate>
                    {{ __('Manage class') }}
                </flux:button>
            @endif
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm lg:col-span-2 dark:border-zinc-700 dark:bg-zinc-900">
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

        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Join this class') }}</h2>
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Enter the enrollment key shared by your instructor to join.') }}</p>

            @if ($this->isEnrolled)
                <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200">
                    {{ __('You are already enrolled in this class.') }}
                </div>
            @else
                <form wire:submit="enroll" class="mt-4 space-y-4">
                    <flux:input wire:model="enrollment_key" :label="__('Enrollment key')" type="text" required />
                    <flux:button variant="primary" type="submit" class="w-full">{{ __('Join class') }}</flux:button>
                </form>
            @endif

            <div class="mt-4 text-sm text-zinc-500">
                {{ __('Course code') }}: {{ $course->code }}
            </div>
        </div>
    </div>
</div>
