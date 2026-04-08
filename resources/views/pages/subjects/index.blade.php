<?php

use App\Enums\RoleName;
use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Subjects')] class extends Component
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
    public function subjects()
    {
        return Course::query()
            ->with('department')
            ->when($this->search !== '', function (Builder $query): void {
                $query->where(function (Builder $builder): void {
                    $builder
                        ->where('title', 'like', "%{$this->search}%")
                        ->orWhere('code', 'like', "%{$this->search}%")
                        ->orWhereHas('department', fn (Builder $departmentQuery) => $departmentQuery->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->orderBy('title')
            ->paginate(7);
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="text-sm text-zinc-500">{{ __('Dashboard UI') }} <span class="mx-2">/</span> {{ __('Subjects') }}</div>
            <flux:heading size="xl" class="mt-2">{{ __('Subjects') }}</flux:heading>
            <flux:subheading>{{ __('Quick access to essential metrics and management tools.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus">{{ __('Add') }}</flux:button>
    </div>

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
                        <th class="px-4 py-3 font-medium">{{ __('Subject') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Department') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Description') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->subjects as $subject)
                        <tr>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200">{{ $subject->code }}</span>
                            </td>
                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $subject->title }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-blue-50 px-2 py-1 text-xs text-blue-700 dark:bg-blue-900/30 dark:text-blue-200">{{ $subject->department?->name }}</span>
                            </td>
                            <td class="px-4 py-3 text-zinc-500 dark:text-zinc-300">{{ str($subject->description)->limit(90) }}</td>
                            <td class="px-4 py-3 text-right">
                                <flux:button size="sm" variant="ghost" :href="route('subjects.show', $subject)" wire:navigate>{{ __('View') }}</flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-zinc-500">{{ __('No subjects found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>
        {{ $this->subjects->onEachSide(1)->links() }}
    </div>
</div>
