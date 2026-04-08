<?php

use App\Enums\RoleName;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Department Details')] class extends Component
{
    public Department $department;

    public function mount(Department $department): void
    {
        abort_unless(auth()->user()?->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value]), 403);

        $this->department = $department;
    }

    #[Computed]
    public function totalStudents(): int
    {
        return User::query()
            ->whereHas('enrollments.course', function ($query): void {
                $query->where('department_id', $this->department->id);
            })
            ->distinct('users.id')
            ->count('users.id');
    }

    #[Computed]
    public function courses(): Collection
    {
        return $this->department
            ->courses()
            ->with(['facultyProfile.user'])
            ->orderBy('title')
            ->get();
    }

    #[Computed]
    public function faculty(): Collection
    {
        return $this->department
            ->facultyProfiles()
            ->with('user')
            ->orderBy('employee_code')
            ->get();
    }

    #[Computed]
    public function recentEnrollments(): Collection
    {
        return Enrollment::query()
            ->whereHas('course', fn ($query) => $query->where('department_id', $this->department->id))
            ->with(['student', 'course'])
            ->latest('enrolled_at')
            ->limit(5)
            ->get();
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
        <div class="space-y-4">
            <flux:button size="sm" variant="ghost" :href="route('departments.index')" wire:navigate icon="arrow-left">
                {{ __('Back') }}
            </flux:button>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="text-sm text-zinc-500">
                        {{ __('Departments') }} <span class="mx-2">/</span> {{ __('Department Details') }}
                    </div>
                    <flux:heading size="xl" class="mt-2">{{ $department->name }}</flux:heading>
                </div>

                <div class="flex gap-2">
                    <flux:button variant="ghost" icon="arrow-path">{{ __('Refresh') }}</flux:button>
                    <flux:button variant="ghost" icon="pencil">{{ __('Edit') }}</flux:button>
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text>{{ __('Total Courses') }}</flux:text>
                <flux:heading size="xl" class="mt-2">{{ $this->courses->count() }}</flux:heading>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text>{{ __('Faculty Members') }}</flux:text>
                <flux:heading size="xl" class="mt-2">{{ $this->faculty->count() }}</flux:heading>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text>{{ __('Enrolled Students') }}</flux:text>
                <flux:heading size="xl" class="mt-2">{{ $this->totalStudents }}</flux:heading>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Courses') }}</flux:heading>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-zinc-50 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-300">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ __('Code') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Class name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Teacher') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Capacity') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('Details') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($this->courses as $course)
                            <tr>
                                <td class="px-4 py-3">{{ $course->code }}</td>
                                <td class="px-4 py-3 font-medium">{{ $course->title }}</td>
                                <td class="px-4 py-3">{{ $course->facultyProfile?->user?->name ?? __('Unassigned') }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs {{ $course->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-200' }}">
                                        {{ $course->is_active ? __('Active') : __('Inactive') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ $course->capacity ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <flux:button size="sm" variant="ghost" :href="route('courses.show', $course)" wire:navigate>{{ __('View') }}</flux:button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-zinc-500">{{ __('No courses for this department yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <flux:heading size="lg">{{ __('Faculty') }}</flux:heading>
                </div>
                <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->faculty as $faculty)
                        <div class="flex items-center justify-between px-4 py-3 text-sm">
                            <div>
                                <div class="font-medium">{{ $faculty->user?->name }}</div>
                                <div class="text-zinc-500">{{ '@'.$faculty->user?->email }}</div>
                            </div>
                            <span class="rounded-full bg-blue-50 px-2 py-1 text-xs text-blue-700 dark:bg-blue-900/30 dark:text-blue-200">{{ $faculty->title ?? __('Faculty') }}</span>
                        </div>
                    @empty
                        <div class="px-4 py-6 text-sm text-zinc-500">{{ __('No faculty assigned.') }}</div>
                    @endforelse
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <flux:heading size="lg">{{ __('Recent Enrollments') }}</flux:heading>
                </div>
                <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->recentEnrollments as $enrollment)
                        <div class="flex items-center justify-between px-4 py-3 text-sm">
                            <div>
                                <div class="font-medium">{{ $enrollment->student?->name }}</div>
                                <div class="text-zinc-500">{{ $enrollment->course?->title }}</div>
                            </div>
                            <span class="text-zinc-500">{{ optional($enrollment->enrolled_at)->format('M d, Y') }}</span>
                        </div>
                    @empty
                        <div class="px-4 py-6 text-sm text-zinc-500">{{ __('No enrollments yet.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
