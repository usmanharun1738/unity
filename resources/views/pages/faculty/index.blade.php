<?php

use App\Enums\RoleName;
use App\Models\FacultyProfile;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Faculty')] class extends Component
{
    use WithPagination;

    public string $search = '';

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
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
    <div>
        <div class="text-sm text-zinc-500">{{ __('Dashboard UI') }} <span class="mx-2">/</span> {{ __('Faculty') }}</div>
        <flux:heading size="xl" class="mt-2">{{ __('Faculty') }}</flux:heading>
        <flux:subheading>{{ __('Browse and manage faculty members.') }}</flux:subheading>
    </div>

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
