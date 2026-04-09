<?php

use App\Livewire\Concerns\HasToastFeedback;
use App\Models\Course;
use App\Models\Department;
use App\Models\FacultyProfile;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Class Detail')] class extends Component
{
    use HasToastFeedback;

    public Course $course;

    public bool $editing = false;

    public string $title = '';

    public string $code = '';

    public string $enrollment_key = '';

    public ?int $department_id = null;

    public ?int $faculty_profile_id = null;

    public string $description = '';

    public int $credits = 3;

    public ?int $capacity = null;

    public string $semester = '';

    public bool $is_active = true;

    public function mount(Course $course): void
    {
        Gate::authorize('view', $course);
        $this->course = $course->load(['department', 'facultyProfile.user']);
        $this->pullToastFromSession();
        $this->syncFormState();
    }

    public function refreshCourse(): void
    {
        $this->course->refresh()->load(['department', 'facultyProfile.user']);
        $this->syncFormState();
    }

    protected function syncFormState(): void
    {
        $this->title = $this->course->title;
        $this->code = $this->course->code;
        $this->enrollment_key = $this->course->enrollment_key ?? '';
        $this->department_id = $this->course->department_id;
        $this->faculty_profile_id = $this->course->faculty_profile_id;
        $this->description = $this->course->description ?? '';
        $this->credits = $this->course->credits;
        $this->capacity = $this->course->capacity;
        $this->semester = $this->course->semester ?? '';
        $this->is_active = $this->course->is_active;
    }

    #[Computed]
    public function departments()
    {
        return Department::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function facultyProfiles()
    {
        return FacultyProfile::query()->with('user:id,name')->orderBy('employee_code')->get();
    }

    public function saveCourse(): void
    {
        Gate::authorize('update', $this->course);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:courses,code,'.$this->course->id],
            'enrollment_key' => ['required', 'string', 'max:32', 'unique:courses,enrollment_key,'.$this->course->id],
            'department_id' => ['required', 'exists:departments,id'],
            'faculty_profile_id' => ['nullable', 'exists:faculty_profiles,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'credits' => ['required', 'integer', 'between:1,12'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'semester' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);

        $this->course->update($validated);
        $this->editing = false;
        $this->refreshCourse();
        $this->successToast(__('Class updated successfully.'));
    }

    public function deleteCourse(): void
    {
        Gate::authorize('delete', $this->course);

        if ($this->course->enrollments()->exists()) {
            $this->errorToast(__('This class has enrollments and cannot be deleted yet.'));

            return;
        }

        $this->successToast(__('Class deleted successfully.'), persist: true);
        $this->course->delete();

        $this->redirect(route('courses.index'), navigate: true);
    }

    public function toggleArchiveStatus(): void
    {
        Gate::authorize('update', $this->course);

        $this->course->update([
            'is_active' => ! $this->course->is_active,
        ]);

        $this->refreshCourse();
        $this->successToast($this->course->is_active ? __('Class restored successfully.') : __('Class archived successfully.'));
    }

    #[Computed]
    public function enrolledCount(): int
    {
        return $this->course->enrollments()->count();
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
    <x-ui.toast :message="$toastMessage" :variant="$toastVariant" />
        <div class="space-y-4">
            <div class="text-sm text-zinc-500">
                <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 hover:underline dark:hover:text-zinc-200">{{ __('Dashboard') }}</a>
                <span class="mx-2">/</span>
                <a href="{{ route('courses.index') }}" wire:navigate class="hover:text-zinc-700 hover:underline dark:hover:text-zinc-200">{{ __('Classes') }}</a>
                <span class="mx-2">/</span>
                <span>{{ __('Class Detail') }}</span>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <flux:button size="sm" variant="ghost" :href="route('courses.index')" wire:navigate icon="arrow-left">
                    {{ __('Back') }}
                </flux:button>

                <div class="flex gap-2">
                    <flux:button variant="ghost" icon="arrow-path" wire:click="refreshCourse">{{ __('Refresh') }}</flux:button>
                    <flux:button variant="ghost" icon="pencil" wire:click="$toggle('editing')">{{ __('Edit') }}</flux:button>
                    <flux:button variant="ghost" icon="archive-box" wire:click="toggleArchiveStatus">
                        {{ $course->is_active ? __('Archive') : __('Restore') }}
                    </flux:button>
                    <flux:button
                        variant="danger"
                        icon="trash"
                        wire:click="deleteCourse"
                        wire:confirm="{{ __('Delete this class? This action cannot be undone.') }}"
                    >
                        {{ __('Delete') }}
                    </flux:button>
                </div>
            </div>
        </div>

        @if ($editing)
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <form wire:submit="saveCourse" class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="title" :label="__('Class name')" type="text" required />
                    <flux:input wire:model="code" :label="__('Class code')" type="text" required />
                    <flux:input wire:model="enrollment_key" :label="__('Enrollment key')" type="text" required />

                    <flux:select wire:model="department_id" :label="__('Department')" required>
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
                    <flux:checkbox wire:model="is_active" :label="__('Active class')" class="md:col-span-2" />

                    <div class="md:col-span-2 flex items-center gap-3">
                        <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                        <flux:button variant="ghost" type="button" wire:click="$set('editing', false)">{{ __('Cancel') }}</flux:button>
                    </div>
                </form>
            </div>
        @endif

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
                        <flux:text>{{ __('Enrollment Key') }}</flux:text>
                        <div class="mt-2 text-sm font-medium">{{ $course->enrollment_key }}</div>
                        <div class="text-sm text-zinc-500">{{ __('Students use this key to join the class.') }}</div>
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
