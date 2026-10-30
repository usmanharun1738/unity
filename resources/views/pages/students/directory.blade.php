<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Student Directory') }}</flux:heading>
            <flux:subheading>{{ __('View, filter, and manage student profiles and academic statuses.') }}</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:badge size="sm" color="zinc" class="font-mono tabular-nums">
                {{ trans_choice('{0} 0 students|{1} :count student|[2,*] :count students', $this->students->total(), ['count' => $this->students->total()]) }}
            </flux:badge>
        </div>
    </div>

    <!-- Filter & Toolbar Card -->
    <div class="rounded-xl border border-zinc-200/80 bg-white p-3 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <!-- Left Controls: Search & Department Filter -->
            <div class="flex flex-1 flex-col gap-2.5 sm:flex-row sm:items-center">
                <div class="relative flex-1 min-w-[220px] max-w-md">
                    <flux:input
                        wire:model.live.debounce-300ms="search"
                        type="text"
                        size="sm"
                        icon="magnifying-glass"
                        :placeholder="__('Search by name or student number...')"
                        clearable
                    />
                </div>

                <div class="w-full sm:w-56 shrink-0">
                    <flux:select
                        wire:model.live="department_id"
                        size="sm"
                    >
                        <option value="">{{ __('All departments') }}</option>
                        @foreach ($this->departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            <!-- Right Controls: Sorting, Direction, Per Page & Reset -->
            <div class="flex flex-wrap items-center justify-between sm:justify-end gap-2.5 pt-2 border-t border-zinc-100 lg:border-t-0 lg:pt-0 dark:border-zinc-800 shrink-0">
                <!-- Sort Group -->
                <div class="flex items-center gap-1.5">
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider shrink-0">{{ __('Sort') }}</span>
                    <flux:select wire:model.live="sort_by" size="sm" class="w-36">
                        <option value="name">{{ __('Name') }}</option>
                        <option value="student_number">{{ __('Student ID') }}</option>
                        <option value="major">{{ __('Major') }}</option>
                    </flux:select>
                    <flux:button
                        size="sm"
                        variant="subtle"
                        wire:click="$set('sort_direction', $sort_direction === 'asc' ? 'desc' : 'asc')"
                        :icon="$sort_direction === 'asc' ? 'arrow-up' : 'arrow-down'"
                        :tooltip="$sort_direction === 'asc' ? __('Ascending') : __('Descending')"
                        aria-label="{{ __('Toggle sort direction') }}"
                    />
                </div>

                <div class="h-4 w-px bg-zinc-200 dark:bg-zinc-700 hidden sm:block"></div>

                <!-- Per Page -->
                <div class="flex items-center gap-1.5">
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider shrink-0">{{ __('Show') }}</span>
                    <flux:select wire:model.live="per_page" size="sm" class="w-20">
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </flux:select>
                </div>

                @if ($this->hasActiveFilters)
                    <div class="h-4 w-px bg-zinc-200 dark:bg-zinc-700 hidden sm:block"></div>

                    <flux:button
                        size="sm"
                        variant="ghost"
                        wire:click="resetFilters"
                        icon="arrow-path"
                        class="text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                    >
                        {{ __('Reset') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </div>

    <!-- Students Table Card -->
    <div class="overflow-hidden rounded-xl border border-zinc-200/80 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-zinc-200/80 bg-zinc-50/75 dark:border-zinc-800 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Student') }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Student ID') }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Major / Department') }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Year Level') }}
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200/70 dark:divide-zinc-800">
                    @forelse ($this->students as $student)
                        <tr class="transition-colors hover:bg-zinc-50/80 dark:hover:bg-zinc-800/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <flux:avatar :name="$student->name" size="sm" />
                                    <div class="min-w-0">
                                        <p class="font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $student->name }}</p>
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400 truncate">{{ $student->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <code class="font-mono text-xs bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200 px-2 py-1 rounded-md border border-slate-200/80 dark:border-slate-700 font-semibold tabular-nums tracking-wide">
                                    {{ $student->studentProfile?->student_number ?? '—' }}
                                </code>
                            </td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                {{ $student->studentProfile?->major ?? '—' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <flux:badge
                                    :color="$student->studentProfile?->year_level === 4 ? 'emerald' : 'zinc'"
                                    size="sm"
                                    class="font-mono tabular-nums"
                                >
                                    Year {{ $student->studentProfile?->year_level ?? '—' }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <flux:button
                                    href="{{ route('students.show', $student) }}"
                                    variant="ghost"
                                    size="sm"
                                    icon="arrow-right"
                                    wire:navigate
                                >
                                    {{ __('View') }}
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center">
                                <div class="mx-auto flex max-w-sm flex-col items-center justify-center text-center">
                                    <div class="flex size-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500">
                                        <flux:icon name="magnifying-glass" class="size-6" />
                                    </div>
                                    <h3 class="mt-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('No students found') }}</h3>
                                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                        @if ($this->hasActiveFilters)
                                            {{ __('No students match your active search or filters. Try adjusting your criteria.') }}
                                        @else
                                            {{ __('There are no registered students in the directory yet.') }}
                                        @endif
                                    </p>
                                    @if ($this->hasActiveFilters)
                                        <div class="mt-4">
                                            <flux:button size="sm" variant="outline" wire:click="resetFilters">
                                                {{ __('Clear all filters') }}
                                            </flux:button>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-zinc-200/80 px-4 py-3 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('Showing :from to :to of :total results', [
                    'from' => $this->students->firstItem() ?? 0,
                    'to' => $this->students->lastItem() ?? 0,
                    'total' => $this->students->total(),
                ]) }}
            </p>

            <div class="flex items-center gap-2">
                <span class="text-xs text-zinc-500 dark:text-zinc-400 tabular-nums me-1">
                    {{ __('Page :current of :last', [
                        'current' => $this->students->currentPage(),
                        'last' => max(1, $this->students->lastPage()),
                    ]) }}
                </span>

                @if ($this->students->onFirstPage())
                    <flux:button variant="ghost" size="sm" icon="chevron-left" disabled />
                @else
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="chevron-left"
                        wire:click="previousPage"
                        aria-label="{{ __('Previous page') }}"
                    />
                @endif

                @if ($this->students->hasMorePages())
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="chevron-right"
                        wire:click="nextPage"
                        aria-label="{{ __('Next page') }}"
                    />
                @else
                    <flux:button variant="ghost" size="sm" icon="chevron-right" disabled />
                @endif
            </div>
        </div>
    </div>
</div>
