<?php

use App\Enums\RoleName;
use App\Models\Course;
use App\Models\FacultyProfile;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Faculty Profile')] class extends Component
{
    public FacultyProfile $facultyProfile;

    public function mount(FacultyProfile $facultyProfile): void
    {
        abort_unless(auth()->user()?->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value]), 403);

        $this->facultyProfile = $facultyProfile->load(['user', 'department']);
    }

    #[Computed]
    public function courses()
    {
        return Course::query()
            ->with('department')
            ->where('faculty_profile_id', $this->facultyProfile->id)
            ->orderBy('title')
            ->get();
    }

    #[Computed]
    public function departmentCourses()
    {
        return Course::query()
            ->with('department')
            ->where('department_id', $this->facultyProfile->department_id)
            ->whereKeyNot($this->courses->pluck('id'))
            ->limit(5)
            ->get();
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
    <div class="space-y-4">
        <div class="text-sm text-zinc-500">{{ __('Faculty') }} <span class="mx-2">/</span> {{ __('Profile') }}</div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:button size="sm" variant="ghost" :href="route('faculty.index')" wire:navigate icon="arrow-left">{{ __('Back') }}</flux:button>

            <div class="flex gap-2">
                <flux:button variant="ghost" icon="arrow-path">{{ __('Refresh') }}</flux:button>
                <flux:button variant="ghost" icon="pencil">{{ __('Edit') }}</flux:button>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex items-center gap-4">
            <flux:avatar :name="$facultyProfile->user?->name" :initials="$facultyProfile->user?->initials()" />
            <div>
                <flux:heading size="xl">{{ $facultyProfile->user?->name }}</flux:heading>
                <flux:text>{{ '@'.$facultyProfile->user?->email }}</flux:text>
            </div>
            <span class="rounded-full bg-blue-50 px-2 py-1 text-xs text-blue-700 dark:bg-blue-900/30 dark:text-blue-200">{{ __('Teacher') }}</span>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700"><flux:heading size="lg">{{ __('Subjects') }}</flux:heading></div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-zinc-50 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-300">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Code') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Subject') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Department') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Description') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->courses as $course)
                        <tr>
                            <td class="px-4 py-3">{{ $course->code }}</td>
                            <td class="px-4 py-3 font-medium">{{ $course->title }}</td>
                            <td class="px-4 py-3">{{ $course->department?->name }}</td>
                            <td class="px-4 py-3 text-zinc-500">{{ str($course->description)->limit(80) }}</td>
                            <td class="px-4 py-3 text-right"><flux:button size="sm" variant="ghost" :href="route('courses.show', $course)" wire:navigate>{{ __('View') }}</flux:button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-zinc-500">{{ __('No assigned subjects.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700"><flux:heading size="lg">{{ __('Department Classes') }}</flux:heading></div>
        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse ($this->departmentCourses as $course)
                <div class="flex items-center justify-between px-4 py-3 text-sm">
                    <div>
                        <div class="font-medium">{{ $course->title }}</div>
                        <div class="text-zinc-500">{{ $course->department?->name }}</div>
                    </div>
                    <flux:button size="sm" variant="ghost" :href="route('courses.show', $course)" wire:navigate>{{ __('View') }}</flux:button>
                </div>
            @empty
                <div class="px-4 py-6 text-sm text-zinc-500">{{ __('No extra department classes.') }}</div>
            @endforelse
        </div>
    </div>
</div>
