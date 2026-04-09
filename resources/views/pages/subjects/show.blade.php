<?php

use App\Livewire\Concerns\HasToastFeedback;
use App\Models\Course;
use App\Models\FacultyProfile;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Subject Details')] class extends Component
{
    use HasToastFeedback;

    public Course $subject;

    public bool $editing = false;

    public string $title = '';

    public string $description = '';

    public function mount(Course $course): void
    {
        Gate::authorize('view', $course);

        $this->subject = $course->load(['department', 'facultyProfile.user']);
        $this->pullToastFromSession();
        $this->title = $this->subject->title;
        $this->description = $this->subject->description ?? '';
    }

    public function refreshSubject(): void
    {
        $this->subject->refresh()->load(['department', 'facultyProfile.user']);
        $this->title = $this->subject->title;
        $this->description = $this->subject->description ?? '';
    }

    public function saveSubject(): void
    {
        Gate::authorize('update', $this->subject);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->subject->update($validated);
        $this->editing = false;
        $this->refreshSubject();
        $this->successToast(__('Subject updated successfully.'));
        $this->dispatch('subject-updated');
    }

    public function deleteSubject(): void
    {
        Gate::authorize('delete', $this->subject);

        $this->successToast(__('Subject deleted successfully.'), persist: true);
        $this->subject->delete();

        $this->redirect(route('subjects.index'), navigate: true);
    }

    #[Computed]
    public function relatedCourses()
    {
        return Course::query()
            ->with(['facultyProfile.user'])
            ->where('department_id', $this->subject->department_id)
            ->whereKeyNot($this->subject->id)
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function facultyMembers()
    {
        if (! $this->subject->department_id) {
            return collect();
        }

        return FacultyProfile::query()
            ->with('user')
            ->where('department_id', $this->subject->department_id)
            ->limit(5)
            ->get();
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
    <x-ui.toast :message="$toastMessage" :variant="$toastVariant" />
    <div class="space-y-4">
        <div class="text-sm text-zinc-500">
            <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 hover:underline dark:hover:text-zinc-200">{{ __('Dashboard') }}</a>
            <span class="mx-2">/</span>
            <a href="{{ route('subjects.index') }}" wire:navigate class="hover:text-zinc-700 hover:underline dark:hover:text-zinc-200">{{ __('Subjects') }}</a>
            <span class="mx-2">/</span>
            <span>{{ __('Subject Details') }}</span>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:button size="sm" variant="ghost" :href="route('subjects.index')" wire:navigate icon="arrow-left">{{ __('Back') }}</flux:button>

            <div class="flex gap-2">
                <flux:button variant="ghost" icon="arrow-path" wire:click="refreshSubject">{{ __('Refresh') }}</flux:button>
                <flux:button variant="ghost" icon="pencil" wire:click="$toggle('editing')">{{ __('Edit') }}</flux:button>
                <flux:button
                    variant="danger"
                    icon="trash"
                    wire:click="deleteSubject"
                    wire:confirm="{{ __('Delete this subject? This action cannot be undone.') }}"
                >
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        @if ($editing)
            <form wire:submit="saveSubject" class="grid gap-3">
                <flux:input wire:model="title" :label="__('Subject name')" type="text" required />
                <flux:input wire:model="description" :label="__('Description')" type="text" />
                <div class="flex items-center gap-3">
                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                    <flux:button variant="ghost" type="button" wire:click="$set('editing', false)">{{ __('Cancel') }}</flux:button>
                    <x-action-message on="subject-updated">{{ __('Updated.') }}</x-action-message>
                </div>
            </form>
        @else
            <flux:heading size="xl">{{ $subject->title }}</flux:heading>
            <flux:text class="mt-2">{{ $subject->description ?: __('No subject overview available.') }}</flux:text>
        @endif
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Subject') }}</flux:heading>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-zinc-50 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-300">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Code') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Department') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Description') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="px-4 py-3">{{ $subject->code }}</td>
                        <td class="px-4 py-3 font-medium">{{ $subject->title }}</td>
                        <td class="px-4 py-3">{{ $subject->department?->name }}</td>
                        <td class="px-4 py-3 text-zinc-500">{{ str($subject->description)->limit(100) }}</td>
                        <td class="px-4 py-3 text-right"><flux:button size="sm" variant="ghost">{{ __('View') }}</flux:button></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700"><flux:heading size="lg">{{ __('Classes') }}</flux:heading></div>
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->relatedCourses as $course)
                    <div class="flex items-center justify-between px-4 py-3 text-sm">
                        <div>
                            <div class="font-medium">{{ $course->title }}</div>
                            <div class="text-zinc-500">{{ $course->facultyProfile?->user?->name ?? __('Unassigned') }}</div>
                        </div>
                        <flux:button size="sm" variant="ghost" :href="route('courses.show', $course)" wire:navigate>{{ __('View') }}</flux:button>
                    </div>
                @empty
                    <div class="px-4 py-6 text-sm text-zinc-500">{{ __('No related classes.') }}</div>
                @endforelse
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700"><flux:heading size="lg">{{ __('Teachers') }}</flux:heading></div>
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->facultyMembers as $faculty)
                    <div class="flex items-center justify-between px-4 py-3 text-sm">
                        <div>
                            <div class="font-medium">{{ $faculty->user?->name }}</div>
                            <div class="text-zinc-500">{{ '@'.$faculty->user?->email }}</div>
                        </div>
                        <span class="rounded-full bg-blue-50 px-2 py-1 text-xs text-blue-700 dark:bg-blue-900/30 dark:text-blue-200">{{ __('Teacher') }}</span>
                    </div>
                @empty
                    <div class="px-4 py-6 text-sm text-zinc-500">{{ __('No teachers listed.') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
