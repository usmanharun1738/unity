<div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Syllabus') }}</h2>
        <div class="flex items-center gap-2">
            @if ($course->syllabus_updated_at)
                <span class="text-xs text-zinc-500">{{ __('Updated') }} {{ $course->syllabus_updated_at->diffForHumans() }}</span>
            @endif
            <flux:button
                size="sm"
                variant="ghost"
                wire:click="toggleSyllabusCollapsed"
                :icon="$is_syllabus_collapsed ? 'chevron-down' : 'chevron-up'"
            >
                {{ $is_syllabus_collapsed ? __('Expand') : __('Collapse') }}
            </flux:button>
        </div>
    </div>

    @if (! $is_syllabus_collapsed)
        @if ($this->canAccessLearningContent)
            <div class="mt-3 whitespace-pre-line text-sm text-zinc-600 dark:text-zinc-300">{{ $course->syllabus_content ?: __('No syllabus published yet.') }}</div>

            @if ($this->syllabusFiles->isNotEmpty())
                <div class="mt-4 space-y-2">
                    @foreach ($this->syllabusFiles as $material)
                        <div class="flex flex-col gap-2 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $material->title }}</div>
                                <div class="text-xs text-zinc-500">{{ $material->original_name }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:button size="sm" variant="ghost" :href="route('courses.materials.download', [$course, $material])">
                                    {{ __('Download') }}
                                </flux:button>
                                @if ($this->canManageCourse)
                                    <flux:button size="sm" variant="danger" wire:click="deleteMaterial({{ $material->id }})" wire:confirm="{{ __('Delete this file?') }}">
                                        {{ __('Delete') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @else
            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-200">
                {{ __('Enroll in this class to access syllabus and learning materials.') }}
            </div>
        @endif

        @if ($this->canManageCourse)
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <form wire:submit="saveSyllabus" class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700 md:col-span-2">
                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Syllabus content') }}</label>
                    <textarea wire:model="syllabus_content" rows="6" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm outline-none ring-indigo-500 focus:ring dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"></textarea>
                    <flux:button variant="primary" type="submit">{{ __('Save syllabus') }}</flux:button>
                </form>

                <form wire:submit="uploadSyllabusFile" class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700 md:col-span-2">
                    <flux:heading size="sm">{{ __('Upload syllabus file') }}</flux:heading>
                    <flux:input wire:model="syllabus_file_title" :label="__('Title')" type="text" required />
                    <flux:input wire:model="syllabus_file_description" :label="__('Description')" type="text" />
                    <flux:input wire:model="syllabus_file" :label="__('File')" type="file" required />
                    <flux:button variant="primary" type="submit">{{ __('Upload syllabus file') }}</flux:button>
                </form>
            </div>
        @endif
    @endif
</div>
