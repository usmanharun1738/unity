<div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
    <div class="flex items-center justify-between gap-2">
        <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Modules & Learning Materials') }}</h2>
        <flux:button
            size="sm"
            variant="ghost"
            wire:click="toggleModulesCollapsed"
            :icon="$is_modules_collapsed ? 'chevron-down' : 'chevron-up'"
        >
            {{ $is_modules_collapsed ? __('Expand') : __('Collapse') }}
        </flux:button>
    </div>

    @if (! $is_modules_collapsed)
        @if ($this->canAccessLearningContent)
            @if ($this->canManageCourse)
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <form wire:submit="createModule" class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:heading size="sm">{{ __('Create module') }}</flux:heading>
                        <flux:input wire:model="module_title" :label="__('Title')" type="text" required />
                        <flux:input wire:model="module_week_number" :label="__('Week number')" type="number" min="1" max="52" />
                        <flux:input wire:model="module_position" :label="__('Sort order')" type="number" min="0" max="1000" />
                        <flux:input wire:model="module_description" :label="__('Description')" type="text" />
                        <flux:button variant="primary" type="submit">{{ __('Create module') }}</flux:button>
                    </form>

                    <form wire:submit="uploadModuleMaterial" class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:heading size="sm">{{ __('Upload module material') }}</flux:heading>
                        <flux:select wire:model="material_module_id" :label="__('Module')" required>
                            <option value="">{{ __('Select module') }}</option>
                            @foreach ($this->modules as $moduleOption)
                                <option value="{{ $moduleOption->id }}">{{ $moduleOption->title }}</option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="material_title" :label="__('Title')" type="text" required />
                        <flux:input wire:model="material_description" :label="__('Description')" type="text" />
                        <flux:input wire:model="material_file" :label="__('File')" type="file" required />
                        <flux:button variant="primary" type="submit">{{ __('Upload material') }}</flux:button>
                    </form>
                </div>
            @endif

            <div class="mt-4 space-y-3">
                @forelse ($this->modules as $module)
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $module->title }}</h3>
                                <div class="text-xs text-zinc-500">
                                    {{ $module->week_number ? __('Week :week', ['week' => $module->week_number]) : __('No week assigned') }}
                                </div>
                            </div>
                            @if ($this->canManageCourse)
                                <flux:button size="sm" variant="danger" wire:click="deleteModule({{ $module->id }})" wire:confirm="{{ __('Delete this module and all its files?') }}">
                                    {{ __('Delete module') }}
                                </flux:button>
                            @endif
                        </div>

                        @if ($module->description)
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $module->description }}</p>
                        @endif

                        <div class="mt-3 space-y-2">
                            @forelse ($module->materials as $material)
                                <div class="flex flex-col gap-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
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
                            @empty
                                <div class="text-sm text-zinc-500">{{ __('No materials uploaded yet for this module.') }}</div>
                            @endforelse
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                        {{ __('No modules have been created yet.') }}
                    </div>
                @endforelse
            </div>
        @else
            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-200">
                {{ __('Enroll in this class to access module content and downloadable files.') }}
            </div>
        @endif
    @endif
</div>
