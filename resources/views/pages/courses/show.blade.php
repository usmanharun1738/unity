<?php

use App\Enums\RoleName;
use App\Models\Course;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Class Detail')] class extends Component
{
    public Course $course;

    public function mount(Course $course): void
    {
        abort_unless(auth()->user()?->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value]), 403);

        $this->course = $course->load(['department', 'facultyProfile.user']);
    }

    public function refreshCourse(): void
    {
        $this->course->refresh()->load(['department', 'facultyProfile.user']);
    }

    #[Computed]
    public function enrolledCount(): int
    {
        return $this->course->enrollments()->count();
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
        <div class="space-y-4">
            <div class="text-sm text-zinc-500">
                {{ __('Classes') }} <span class="mx-2">/</span> {{ __('Class Detail') }}
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <flux:button size="sm" variant="ghost" :href="route('courses.index')" wire:navigate icon="arrow-left">
                    {{ __('Back') }}
                </flux:button>

                <div class="flex gap-2">
                    <flux:button variant="ghost" icon="arrow-path" wire:click="refreshCourse">{{ __('Refresh') }}</flux:button>
                    <flux:button variant="ghost" icon="pencil">{{ __('Edit') }}</flux:button>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="h-36 bg-linear-to-r from-indigo-500 via-violet-500 to-blue-500"></div>

            <div class="space-y-6 p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <flux:heading size="xl">{{ $course->title }}</flux:heading>
                        <flux:text class="mt-1">{{ $course->description ?: __('No description provided yet.') }}</flux:text>
                    </div>

                    <span class="rounded-full px-3 py-1 text-xs font-medium {{ $course->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-200' }}">
                        {{ $course->is_active ? __('Active') : __('Inactive') }}
                    </span>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text>{{ __('Instructor') }}</flux:text>
                        <div class="mt-2 text-sm font-medium">{{ $course->facultyProfile?->user?->name ?? __('Unassigned') }}</div>
                        @if ($course->facultyProfile?->user)
                            <div class="text-sm text-zinc-500">{{ '@'.$course->facultyProfile->user->email }}</div>
                        @endif
                    </div>

                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text>{{ __('Department') }}</flux:text>
                        <div class="mt-2 text-sm font-medium">{{ $course->department?->name ?? __('N/A') }}</div>
                        <div class="text-sm text-zinc-500">{{ $course->department?->description }}</div>
                    </div>

                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text>{{ __('Class Code') }}</flux:text>
                        <div class="mt-2 text-sm font-medium">{{ $course->code }}</div>
                        <div class="text-sm text-zinc-500">{{ __('Copy this code for enrollment requests.') }}</div>
                    </div>

                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text>{{ __('Class Metrics') }}</flux:text>
                        <div class="mt-2 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <div class="text-zinc-500">{{ __('Capacity') }}</div>
                                <div class="font-medium">{{ $course->capacity ?? '—' }}</div>
                            </div>
                            <div>
                                <div class="text-zinc-500">{{ __('Enrolled') }}</div>
                                <div class="font-medium">{{ $this->enrolledCount }}</div>
                            </div>
                            <div>
                                <div class="text-zinc-500">{{ __('Credits') }}</div>
                                <div class="font-medium">{{ $course->credits }}</div>
                            </div>
                            <div>
                                <div class="text-zinc-500">{{ __('Semester') }}</div>
                                <div class="font-medium">{{ $course->semester ?: '—' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
