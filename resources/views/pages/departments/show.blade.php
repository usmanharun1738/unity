<?php

use App\Livewire\Concerns\HasToastFeedback;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Department Details')] class extends Component
{
    use HasToastFeedback;

    public Department $department;

    public bool $editing = false;

    public string $name = '';

    public string $code = '';

    public string $description = '';

    public bool $is_active = true;

    public function mount(Department $department): void
    {
        Gate::authorize('view', $department);
        $this->department = $department;
        $this->pullToastFromSession();
        $this->syncFormState();
    }

    public function refreshDepartment(): void
    {
        $this->department->refresh();
        $this->syncFormState();
    }

    protected function syncFormState(): void
    {
        $this->name = $this->department->name;
        $this->code = $this->department->code;
        $this->description = $this->department->description ?? '';
        $this->is_active = $this->department->is_active;
    }

    public function saveDepartment(): void
    {
        Gate::authorize('update', $this->department);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:16', 'unique:departments,code,'.$this->department->id],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        $this->department->update($validated);
        $this->editing = false;
        $this->refreshDepartment();
        $this->successToast(__('Department updated successfully.'));
    }

    public function deleteDepartment(): void
    {
        Gate::authorize('delete', $this->department);

        $hasCourses = $this->department->courses()->exists();
        $hasFaculty = $this->department->facultyProfiles()->exists();

        if ($hasCourses || $hasFaculty) {
            $this->errorToast(__('This department has related classes or faculty and cannot be deleted yet.'));

            return;
        }

        $this->successToast(__('Department deleted successfully.'), persist: true);
        $this->department->delete();

        $this->redirect(route('departments.index'), navigate: true);
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
        <x-ui.toast :message="$toastMessage" :variant="$toastVariant" />
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
                    <flux:button variant="ghost" icon="arrow-path" wire:click="refreshDepartment">{{ __('Refresh') }}</flux:button>
                    <flux:button variant="ghost" icon="pencil" wire:click="$toggle('editing')">{{ __('Edit') }}</flux:button>
                    <flux:button
                        variant="danger"
                        icon="trash"
                        wire:click="deleteDepartment"
                        wire:confirm="{{ __('Delete this department? This action cannot be undone.') }}"
                    >
                        {{ __('Delete') }}
                    </flux:button>
                </div>
            </div>
        </div>

        @if ($editing)
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <form wire:submit="saveDepartment" class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="name" :label="__('Department name')" type="text" required />
                    <flux:input wire:model="code" :label="__('Code')" type="text" required />
                    <flux:input wire:model="description" :label="__('Description')" type="text" class="md:col-span-2" />
                    <flux:checkbox wire:model="is_active" :label="__('Active department')" class="md:col-span-2" />

                    <div class="md:col-span-2 flex items-center gap-3">
                        <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                        <flux:button variant="ghost" type="button" wire:click="$set('editing', false)">{{ __('Cancel') }}</flux:button>
                    </div>
                </form>
            </div>
        @endif

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
