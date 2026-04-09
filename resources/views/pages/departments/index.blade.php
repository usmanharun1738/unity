<?php

use App\Livewire\Concerns\HasToastFeedback;
use App\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Departments')] class extends Component
{
    use HasToastFeedback;
    use WithPagination;

    public string $search = '';

    public string $name = '';

    public string $code = '';

    public string $description = '';

    public bool $showCreateForm = false;

    public function mount(): void
    {
        Gate::authorize('viewAny', Department::class);
        $this->pullToastFromSession();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function departments()
    {
        return Department::query()
            ->withCount('courses')
            ->when($this->search !== '', function (Builder $query): void {
                $query->where(function (Builder $builder): void {
                    $builder
                        ->where('name', 'like', "%{$this->search}%")
                        ->orWhere('code', 'like', "%{$this->search}%")
                        ->orWhere('description', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('name')
            ->paginate(7);
    }

    public function createDepartment(): void
    {
        Gate::authorize('create', Department::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:16', 'unique:departments,code'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        Department::query()->create($validated + ['is_active' => true]);

        $this->reset(['name', 'code', 'description', 'showCreateForm']);
        $this->successToast(__('Department created successfully.'));
        $this->dispatch('department-created');
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
        <x-ui.toast :message="$toastMessage" :variant="$toastVariant" />
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="text-sm text-zinc-500">
                    <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 hover:underline dark:hover:text-zinc-200">{{ __('Dashboard') }}</a>
                    <span class="mx-2">/</span>
                    <span>{{ __('Departments') }}</span>
                </div>
                <flux:heading size="xl" class="mt-2">{{ __('Departments') }}</flux:heading>
                <flux:subheading>{{ __('Quick access to essential metrics and management tools.') }}</flux:subheading>
            </div>

            <flux:button variant="primary" icon="plus" wire:click="$toggle('showCreateForm')">
                {{ __('Add') }}
            </flux:button>
        </div>

        @if ($showCreateForm)
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Create Department') }}</flux:heading>

                <form wire:submit="createDepartment" class="mt-4 grid gap-4 md:grid-cols-3">
                    <flux:input wire:model="name" :label="__('Name')" type="text" required />
                    <flux:input wire:model="code" :label="__('Code')" type="text" required />
                    <flux:input wire:model="description" :label="__('Description')" type="text" />

                    <div class="md:col-span-3 flex items-center gap-3">
                        <flux:button variant="primary" type="submit">{{ __('Save department') }}</flux:button>
                        <flux:button type="button" variant="ghost" wire:click="$set('showCreateForm', false)">{{ __('Cancel') }}</flux:button>
                        <x-action-message on="department-created">{{ __('Created.') }}</x-action-message>
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
                            <th class="px-4 py-3 font-medium">{{ __('Code') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Courses') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Description') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($this->departments as $department)
                            <tr>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200">
                                        {{ $department->code }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $department->name }}</td>
                                <td class="px-4 py-3">{{ $department->courses_count }}</td>
                                <td class="px-4 py-3 text-zinc-500 dark:text-zinc-300">{{ str($department->description)->limit(90) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <flux:button size="sm" variant="ghost" :href="route('departments.show', $department)" wire:navigate>
                                        {{ __('View') }}
                                    </flux:button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-zinc-500">{{ __('No departments found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            {{ $this->departments->onEachSide(1)->links() }}
        </div>
</div>
