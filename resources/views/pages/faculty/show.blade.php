<?php

use App\Enums\RoleName;
use App\Livewire\Concerns\HasToastFeedback;
use App\Models\Course;
use App\Models\Department;
use App\Models\FacultyProfile;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Faculty Profile')] class extends Component
{
    use HasToastFeedback;

    public FacultyProfile $facultyProfile;

    public bool $editing = false;

    public ?int $department_id = null;

    public string $title = '';

    public string $bio = '';

    public function mount(FacultyProfile $facultyProfile): void
    {
        Gate::authorize('view', $facultyProfile);

        $this->facultyProfile = $facultyProfile->load(['user', 'department']);
        $this->pullToastFromSession();
        $this->department_id = $this->facultyProfile->department_id;
        $this->title = $this->facultyProfile->title ?? '';
        $this->bio = $this->facultyProfile->bio ?? '';
    }

    #[Computed]
    public function departments()
    {
        return Department::query()->orderBy('name')->get(['id', 'name']);
    }

    public function refreshProfile(): void
    {
        $this->facultyProfile->refresh()->load(['user', 'department']);
        $this->department_id = $this->facultyProfile->department_id;
        $this->title = $this->facultyProfile->title ?? '';
        $this->bio = $this->facultyProfile->bio ?? '';
    }

    public function saveProfile(): void
    {
        Gate::authorize('update', $this->facultyProfile);

        $validated = $this->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'title' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->facultyProfile->update($validated);
        $this->editing = false;
        $this->refreshProfile();
        $this->successToast(__('Faculty profile updated successfully.'));
        $this->dispatch('faculty-updated');
    }

    public function deleteProfile(): void
    {
        Gate::authorize('delete', $this->facultyProfile);

        $user = $this->facultyProfile->user;

        $this->facultyProfile->delete();

        if ($user !== null && $user->hasRole(RoleName::Faculty->value)) {
            $user->removeRole(RoleName::Faculty->value);
        }

        $this->successToast(__('Faculty profile deleted successfully.'), persist: true);
        $this->redirect(route('faculty.index'), navigate: true);
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
    <x-ui.toast :message="$toastMessage" :variant="$toastVariant" />
    <div class="space-y-4">
        <div class="text-sm text-zinc-500">{{ __('Faculty') }} <span class="mx-2">/</span> {{ __('Profile') }}</div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:button size="sm" variant="ghost" :href="route('faculty.index')" wire:navigate icon="arrow-left">{{ __('Back') }}</flux:button>

            <div class="flex gap-2">
                <flux:button variant="ghost" icon="arrow-path" wire:click="refreshProfile">{{ __('Refresh') }}</flux:button>
                <flux:button variant="ghost" icon="pencil" wire:click="$toggle('editing')">{{ __('Edit') }}</flux:button>
                <flux:button
                    variant="danger"
                    icon="trash"
                    wire:click="deleteProfile"
                    wire:confirm="{{ __('Delete this faculty profile? This action cannot be undone.') }}"
                >
                    {{ __('Delete') }}
                </flux:button>
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

        @if ($editing)
            <form wire:submit="saveProfile" class="mt-6 grid gap-3 md:grid-cols-2">
                <flux:select wire:model="department_id" :label="__('Department')" required>
                    @foreach ($this->departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="title" :label="__('Title')" type="text" />
                <flux:input wire:model="bio" :label="__('Bio')" type="text" class="md:col-span-2" />

                <div class="md:col-span-2 flex items-center gap-3">
                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                    <flux:button variant="ghost" type="button" wire:click="$set('editing', false)">{{ __('Cancel') }}</flux:button>
                    <x-action-message on="faculty-updated">{{ __('Updated.') }}</x-action-message>
                </div>
            </form>
        @endif
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
