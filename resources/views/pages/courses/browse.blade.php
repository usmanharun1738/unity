<?php

use App\Enums\RoleName;
use App\Models\Course;
use App\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Class Catalog')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $department_id = null;

    public string $semester = '';

    public bool $my_only = false;

    public bool $show_archived = false;

    public string $sort_by = 'created_at';

    public string $sort_direction = 'desc';

    public int $per_page = 9;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDepartmentId(): void
    {
        $this->resetPage();
    }

    public function updatedSemester(): void
    {
        $this->resetPage();
    }

    public function updatedMyOnly(): void
    {
        $this->resetPage();
    }

    public function updatedShowArchived(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        if (! in_array($this->sort_by, ['created_at', 'title', 'code', 'semester', 'enrollments_count'], true)) {
            $this->sort_by = 'created_at';
        }

        $this->resetPage();
    }

    public function updatedSortDirection(): void
    {
        if (! in_array($this->sort_direction, ['asc', 'desc'], true)) {
            $this->sort_direction = 'desc';
        }

        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->per_page, [6, 9, 12, 24], true)) {
            $this->per_page = 9;
        }

        $this->resetPage();
    }

    #[Computed]
    public function isManager(): bool
    {
        return auth()->user()->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value]);
    }

    #[Computed]
    public function departments()
    {
        return Department::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function semesters(): array
    {
        return Course::query()
            ->whereNotNull('semester')
            ->distinct()
            ->orderBy('semester')
            ->pluck('semester')
            ->all();
    }

    #[Computed]
    public function enrolledCourseIds(): array
    {
        return auth()->user()->enrollments()->pluck('course_id')->all();
    }

    #[Computed]
    public function courses()
    {
        return Course::query()
            ->with(['department', 'facultyProfile.user'])
            ->withCount('enrollments')
            ->when($this->search !== '', function (Builder $query): void {
                $query->where(function (Builder $builder): void {
                    $builder
                        ->where('title', 'like', "%{$this->search}%")
                        ->orWhere('code', 'like', "%{$this->search}%")
                        ->orWhereHas('department', fn (Builder $departmentQuery) => $departmentQuery->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->department_id, fn (Builder $query) => $query->where('department_id', $this->department_id))
            ->when($this->semester !== '', fn (Builder $query) => $query->where('semester', $this->semester))
            ->when(! $this->isManager, function (Builder $query): void {
                $query->where(function (Builder $builder): void {
                    $builder
                        ->where('is_active', true)
                        ->orWhereHas('enrollments', fn (Builder $enrollmentQuery) => $enrollmentQuery->where('user_id', auth()->id()));
                });
            })
            ->when($this->isManager && ! $this->show_archived, fn (Builder $query) => $query->where('is_active', true))
            ->when($this->my_only, fn (Builder $query) => $query->whereHas('enrollments', fn (Builder $enrollmentQuery) => $enrollmentQuery->where('user_id', auth()->id())))
            ->orderBy(
                in_array($this->sort_by, ['created_at', 'title', 'code', 'semester', 'enrollments_count'], true)
                    ? $this->sort_by
                    : 'created_at',
                in_array($this->sort_direction, ['asc', 'desc'], true)
                    ? $this->sort_direction
                    : 'desc',
            )
            ->paginate($this->per_page);
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="text-sm text-zinc-500">
                <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 hover:underline dark:hover:text-zinc-200">{{ __('Dashboard') }}</a>
                <span class="mx-2">/</span>
                <span>{{ __('Classes') }}</span>
            </div>
            <flux:heading size="xl" class="mt-2">{{ __('Class Catalog') }}</flux:heading>
            <flux:subheading>{{ __('Browse available classes and open course homepages.') }}</flux:subheading>
        </div>

        <flux:button :href="route('enrollments.index')" wire:navigate variant="ghost">
            {{ __('Go to enrollments') }}
        </flux:button>
    </div>

    <div class="grid gap-3 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900 md:grid-cols-5">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search classes')" class="md:col-span-2" />

        <flux:select wire:model.live="department_id" :label="__('Department')">
            <option value="">{{ __('All departments') }}</option>
            @foreach ($this->departments as $department)
                <option value="{{ $department->id }}">{{ $department->name }}</option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="semester" :label="__('Semester')">
            <option value="">{{ __('All semesters') }}</option>
            @foreach ($this->semesters as $semester)
                <option value="{{ $semester }}">{{ $semester }}</option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="sort_by" :label="__('Sort by')">
            <option value="created_at">{{ __('Newest') }}</option>
            <option value="title">{{ __('Title') }}</option>
            <option value="code">{{ __('Code') }}</option>
            <option value="semester">{{ __('Semester') }}</option>
            <option value="enrollments_count">{{ __('Enrollment count') }}</option>
        </flux:select>

        <flux:select wire:model.live="sort_direction" :label="__('Direction')">
            <option value="desc">{{ __('Descending') }}</option>
            <option value="asc">{{ __('Ascending') }}</option>
        </flux:select>

        <flux:select wire:model.live="per_page" :label="__('Per page')">
            <option value="6">6</option>
            <option value="9">9</option>
            <option value="12">12</option>
            <option value="24">24</option>
        </flux:select>

        <div class="flex flex-col justify-end gap-2">
            <flux:checkbox wire:model.live="my_only" :label="__('My classes only')" />
            @if ($this->isManager)
                <flux:checkbox wire:model.live="show_archived" :label="__('Show archived')" />
            @endif
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($this->courses as $course)
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ $course->title }}</h3>
                        <p class="text-sm text-zinc-500">{{ $course->code }}</p>
                    </div>
                    <span class="rounded-full px-2 py-1 text-xs {{ $course->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-200' }}">
                        {{ $course->is_active ? __('Active') : __('Archived') }}
                    </span>
                </div>

                <div class="mt-3 space-y-1 text-sm text-zinc-500">
                    <div>{{ __('Department') }}: {{ $course->department?->name }}</div>
                    <div>{{ __('Instructor') }}: {{ $course->facultyProfile?->user?->name ?? __('Unassigned') }}</div>
                    <div>{{ __('Semester') }}: {{ $course->semester ?: '—' }}</div>
                    <div>{{ __('Enrolled') }}: {{ $course->enrollments_count }}</div>
                </div>

                <div class="mt-4 flex items-center gap-2">
                    <flux:button size="sm" variant="primary" :href="route('courses.home', $course)" wire:navigate>
                        {{ __('Open course') }}
                    </flux:button>

                    @if ($this->isManager)
                        <flux:button size="sm" variant="ghost" :href="route('courses.show', $course)" wire:navigate>
                            {{ __('Manage') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-2xl border border-zinc-200 bg-white p-8 text-center text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900">
                {{ __('No classes matched your filters.') }}
            </div>
        @endforelse
    </div>

    <div>
        {{ $this->courses->onEachSide(1)->links() }}
    </div>
</div>
