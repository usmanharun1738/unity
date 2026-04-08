<?php

use App\Enums\RoleName;
use App\Models\Department;
use App\Models\FacultyProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new #[Title('Faculty')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $user_id = null;

    public ?int $department_id = null;

    public string $employee_code = '';

    public string $title = 'Lecturer';

    public string $bio = '';

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
    public function facultyMembers()
    {
        return FacultyProfile::query()
            ->with(['user', 'department'])
            ->when($this->search !== '', function (Builder $query): void {
                $query->where(function (Builder $builder): void {
                    $builder
                        ->where('employee_code', 'like', "%{$this->search}%")
                        ->orWhereHas('user', fn (Builder $userQuery) => $userQuery
                            ->where('name', 'like', "%{$this->search}%")
                            ->orWhere('email', 'like', "%{$this->search}%"));
                });
            })
            ->latest()
            ->paginate(8);
    }

    #[Computed]
    public function availableUsers()
    {
        return User::query()
            ->whereDoesntHave('facultyProfile')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    #[Computed]
    public function departments()
    {
        return Department::query()->orderBy('name')->get(['id', 'name']);
    }

    public function createFacultyProfile(): void
    {
        abort_unless(auth()->user()?->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value]), 403);

        $validated = $this->validate([
            'user_id' => ['required', 'exists:users,id', 'unique:faculty_profiles,user_id'],
            'department_id' => ['required', 'exists:departments,id'],
            'employee_code' => ['required', 'string', 'max:32', 'unique:faculty_profiles,employee_code'],
            'title' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:2000'],
        ]);

        FacultyProfile::query()->create($validated);

        $user = User::query()->findOrFail($validated['user_id']);
        Role::findOrCreate(RoleName::Faculty->value, 'web');
        $user->assignRole(RoleName::Faculty->value);

        $this->reset(['user_id', 'department_id', 'employee_code', 'title', 'bio', 'showCreateForm']);
        $this->title = 'Lecturer';
        $this->dispatch('faculty-created');
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="text-sm text-zinc-500">{{ __('Dashboard UI') }} <span class="mx-2">/</span> {{ __('Faculty') }}</div>
            <flux:heading size="xl" class="mt-2">{{ __('Faculty') }}</flux:heading>
            <flux:subheading>{{ __('Browse and manage faculty members.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="$toggle('showCreateForm')">{{ __('Add') }}</flux:button>
    </div>

    @if ($showCreateForm)
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Create Faculty Profile') }}</flux:heading>

            <form wire:submit="createFacultyProfile" class="mt-4 grid gap-4 md:grid-cols-2">
                <flux:select wire:model="user_id" :label="__('User')" required>
                    <option value="">{{ __('Select user') }}</option>
                    @foreach ($this->availableUsers as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="department_id" :label="__('Department')" required>
                    <option value="">{{ __('Select department') }}</option>
                    @foreach ($this->departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="employee_code" :label="__('Employee code')" type="text" required />
                <flux:input wire:model="title" :label="__('Title')" type="text" />
                <flux:input wire:model="bio" :label="__('Bio')" type="text" class="md:col-span-2" />

                <div class="md:col-span-2 flex items-center gap-3">
                    <flux:button variant="primary" type="submit">{{ __('Save faculty') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="$set('showCreateForm', false)">{{ __('Cancel') }}</flux:button>
                    <x-action-message on="faculty-created">{{ __('Created.') }}</x-action-message>
                </div>
            </form>
        </div>
    @endif

    <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by name or email')" />
        <flux:button variant="ghost" icon="funnel">{{ __('Filters') }}</flux:button>
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-zinc-50 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-300">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Email') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Role') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->facultyMembers as $faculty)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $faculty->user?->name }}</div>
                                <div class="text-zinc-500">{{ '@'.$faculty->user?->email }}</div>
                            </td>
                            <td class="px-4 py-3 text-zinc-500">{{ $faculty->user?->email }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-blue-50 px-2 py-1 text-xs text-blue-700 dark:bg-blue-900/30 dark:text-blue-200">{{ __('Teacher') }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <flux:button size="sm" variant="ghost" :href="route('faculty.show', $faculty)" wire:navigate>{{ __('View') }}</flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-zinc-500">{{ __('No faculty records found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>
        {{ $this->facultyMembers->onEachSide(1)->links() }}
    </div>
</div>
