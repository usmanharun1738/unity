<?php

use App\Enums\RoleName;
use App\Models\Course;
use App\Models\Department;
use App\Models\FacultyProfile;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Classes')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $title = '';

    public string $code = '';

    public ?int $department_id = null;

    public ?int $faculty_profile_id = null;

    public string $description = '';

    public int $credits = 3;

    public ?int $capacity = null;

    public string $semester = '';

    public bool $showCreateForm = false;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value]), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function courses()
    {
        return Course::query()
            ->with(['department', 'facultyProfile.user'])
            ->when($this->search !== '', function (Builder $query): void {
                $query->where(function (Builder $builder): void {
                    $builder
                        ->where('title', 'like', "%{$this->search}%")
                        ->orWhere('code', 'like', "%{$this->search}%")
                        ->orWhereHas('department', fn (Builder $departmentQuery) => $departmentQuery->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->latest()
            ->paginate(7);
    }

    #[Computed]
    public function departments()
    {
        return Department::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function facultyProfiles()
    {
        return FacultyProfile::query()
            ->with('user:id,name')
            ->orderBy('employee_code')
            ->get();
    }

    public function createCourse(): void
    {
        abort_unless(auth()->user()?->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value]), 403);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:courses,code'],
            'department_id' => ['required', 'exists:departments,id'],
            'faculty_profile_id' => ['nullable', 'exists:faculty_profiles,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'credits' => ['required', 'integer', 'between:1,12'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'semester' => ['nullable', 'string', 'max:50'],
        ]);

        Course::query()->create($validated + ['is_active' => true]);

        $this->reset([
            'title',
            'code',
            'department_id',
            'faculty_profile_id',
            'description',
            'credits',
            'capacity',
            'semester',
            'showCreateForm',
        ]);

        $this->credits = 3;

        $this->dispatch('course-created');
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="text-sm text-zinc-500">
                    {{ __('Dashboard UI') }} <span class="mx-2">/</span> {{ __('Classes') }}
                </div>
                <flux:heading size="xl" class="mt-2">{{ __('Classes') }}</flux:heading>
                <flux:subheading>{{ __('Quick access to essential metrics and management tools.') }}</flux:subheading>
            </div>

            <flux:button variant="primary" icon="plus" wire:click="$toggle('showCreateForm')">
                {{ __('Create a class') }}
            </flux:button>
        </div>

        @if ($showCreateForm)
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Create Class') }}</flux:heading>

                <form wire:submit="createCourse" class="mt-4 grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="title" :label="__('Class name')" type="text" required />
                    <flux:input wire:model="code" :label="__('Class code')" type="text" required />

                    <flux:select wire:model="department_id" :label="__('Department')" required>
                        <option value="">{{ __('Select department') }}</option>
                        @foreach ($this->departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="faculty_profile_id" :label="__('Teacher')">
                        <option value="">{{ __('Unassigned') }}</option>
                        @foreach ($this->facultyProfiles as $faculty)
                            <option value="{{ $faculty->id }}">{{ $faculty->user?->name }} ({{ $faculty->employee_code }})</option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="credits" :label="__('Credits')" type="number" min="1" max="12" required />
                    <flux:input wire:model="capacity" :label="__('Capacity')" type="number" min="1" />
                    <flux:input wire:model="semester" :label="__('Semester')" type="text" />
                    <flux:input wire:model="description" :label="__('Description')" type="text" />

                    <div class="md:col-span-2 flex items-center gap-3">
                        <flux:button variant="primary" type="submit">{{ __('Save class') }}</flux:button>
                        <flux:button type="button" variant="ghost" wire:click="$set('showCreateForm', false)">{{ __('Cancel') }}</flux:button>
                        <x-action-message on="course-created">{{ __('Created.') }}</x-action-message>
                    </div>
                </form>
            </div>
        @endif

        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by name')" />
            <flux:button variant="ghost" icon="funnel">{{ __('Filters') }}</flux:button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-zinc-50 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-300">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ __('Class name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Subject') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Teacher') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Capacity') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('Details') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($this->courses as $course)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $course->title }}</div>
                                    <div class="text-xs text-zinc-500">{{ $course->code }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-blue-50 px-2 py-1 text-xs text-blue-700 dark:bg-blue-900/30 dark:text-blue-200">
                                        {{ $course->department?->name }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    {{ $course->facultyProfile?->user?->name ?? __('Unassigned') }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs {{ $course->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-200' }}">
                                        {{ $course->is_active ? __('Active') : __('Inactive') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ $course->capacity ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <flux:button size="sm" variant="ghost" :href="route('courses.show', $course)" wire:navigate>
                                        {{ __('View') }}
                                    </flux:button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-zinc-500">{{ __('No classes found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            {{ $this->courses->onEachSide(1)->links() }}
        </div>
</div>
