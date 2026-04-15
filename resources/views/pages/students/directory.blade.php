<div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold">{{ __('Student Directory') }}</h1>
            <p class="text-zinc-600 dark:text-zinc-400">{{ __('View and manage all students') }}</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="grid gap-4 md:grid-cols-2">
        <flux:input
            wire:model.live.debounce-500ms="search"
            type="text"
            icon="magnifying-glass"
            :placeholder="__('Search by name or student number...')"
        />

        <flux:select
            wire:model.live="department_id"
            :label="__('Department')"
            placeholder="{{ __('All departments') }}"
        >
            @foreach ($this->departments as $dept)
                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
            @endforeach
        </flux:select>
    </div>

    <!-- Sorting & Pagination Controls -->
    <div class="flex items-center justify-between gap-2 flex-wrap">
        <div class="flex items-center gap-2">
            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Sort by') }}</span>
            <flux:select wire:model.live="sort_by" size="sm" class="w-32">
                <option value="name">{{ __('Name') }}</option>
                <option value="student_number">{{ __('Student Number') }}</option>
                <option value="major">{{ __('Major') }}</option>
            </flux:select>
            <flux:button
                size="sm"
                variant="ghost"
                wire:click="$set('sort_direction', $sort_direction === 'asc' ? 'desc' : 'asc')"
                :icon="$sort_direction === 'asc' ? 'arrow-up' : 'arrow-down'"
            />
        </div>

        <flux:select wire:model.live="per_page" size="sm" class="w-24">
            <option value="10">10</option>
            <option value="15">15</option>
            <option value="25">25</option>
            <option value="50">50</option>
        </flux:select>
    </div>

    <!-- Students Table -->
    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-sm">
            <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-zinc-700 dark:text-zinc-300">
                        {{ __('Name') }}
                    </th>
                    <th class="px-4 py-3 text-left font-semibold text-zinc-700 dark:text-zinc-300">
                        {{ __('Student Number') }}
                    </th>
                    <th class="px-4 py-3 text-left font-semibold text-zinc-700 dark:text-zinc-300">
                        {{ __('Major') }}
                    </th>
                    <th class="px-4 py-3 text-left font-semibold text-zinc-700 dark:text-zinc-300">
                        {{ __('Year Level') }}
                    </th>
                    <th class="px-4 py-3 text-center font-semibold text-zinc-700 dark:text-zinc-300">
                        {{ __('Actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->students as $student)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <flux:avatar :name="$student->name" size="sm" />
                                <div>
                                    <p class="font-medium">{{ $student->name }}</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $student->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <code class="text-xs bg-zinc-100 px-2 py-1 rounded dark:bg-zinc-800">
                                {{ $student->studentProfile?->student_number ?? '-' }}
                            </code>
                        </td>
                        <td class="px-4 py-3">
                            {{ $student->studentProfile?->major ?? '-' }}
                        </td>
                        <td class="px-4 py-3">
                            <flux:badge
                                :variant="$student->studentProfile?->year_level === 4 ? 'success' : 'default'"
                            >
                                Year {{ $student->studentProfile?->year_level ?? '-' }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3 text-center">
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
                        <td colspan="5" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">
                            {{ __('No students found') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="flex items-center justify-between">
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Showing :from to :to of :total results', [
                'from' => $this->students->firstItem() ?? 0,
                'to' => $this->students->lastItem() ?? 0,
                'total' => $this->students->total(),
            ]) }}
        </p>

        <div class="flex gap-2">
            @if ($this->students->onFirstPage())
                <flux:button variant="ghost" size="sm" icon="chevron-left" disabled />
            @else
                <flux:button
                    variant="ghost"
                    size="sm"
                    icon="chevron-left"
                    wire:click="previousPage"
                />
            @endif

            @if ($this->students->hasMorePages())
                <flux:button
                    variant="ghost"
                    size="sm"
                    icon="chevron-right"
                    wire:click="nextPage"
                />
            @else
                <flux:button variant="ghost" size="sm" icon="chevron-right" disabled />
            @endif
        </div>
    </div>
</div>
