<?php

use App\Livewire\Concerns\HasToastFeedback;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Enrollments')] class extends Component
{
    use HasToastFeedback;

    public ?int $course_id = null;

    public string $code = '';

    public string $email = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Enrollment::class);
        $this->pullToastFromSession();
        $this->email = auth()->user()->email;
    }

    #[Computed]
    public function courses()
    {
        return Course::query()->where('is_active', true)->orderBy('title')->get(['id', 'title', 'code']);
    }

    #[Computed]
    public function myEnrollments()
    {
        return Enrollment::query()
            ->with('course.department')
            ->where('user_id', auth()->id())
            ->latest('enrolled_at')
            ->get();
    }

    public function enroll(): void
    {
        Gate::authorize('create', Enrollment::class);

        $validated = $this->validate([
            'course_id' => [
                'required',
                Rule::exists('courses', 'id')->where(fn ($query) => $query->where('is_active', true)),
                Rule::unique('enrollments', 'course_id')->where(fn ($query) => $query->where('user_id', auth()->id())),
            ],
            'code' => ['required', 'string', 'max:20'],
        ]);

        $course = Course::query()->findOrFail($validated['course_id']);

        if (strcasecmp($course->code, trim($validated['code'])) !== 0) {
            $this->addError('code', __('The class code does not match the selected class.'));
            $this->errorToast(__('Enrollment failed. Please check the class code.'));

            return;
        }

        Enrollment::query()->create([
            'user_id' => auth()->id(),
            'course_id' => $course->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $this->reset('course_id', 'code');
        $this->successToast(__('Enrollment completed successfully.'));
        $this->dispatch('enrollment-created');
    }
}; ?>

<div class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6 lg:p-8">
    <x-ui.toast :message="$toastMessage" :variant="$toastVariant" />
    <div>
        <div class="text-sm text-zinc-500">
            <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 hover:underline dark:hover:text-zinc-200">{{ __('Dashboard') }}</a>
            <span class="mx-2">/</span>
            <span>{{ __('Enrollments') }}</span>
        </div>
        <flux:heading size="xl" class="mt-2">{{ __('Enroll in a class') }}</flux:heading>
        <flux:subheading>{{ __('Select a class to enroll as the current user.') }}</flux:subheading>
    </div>

    <div class="mx-auto w-full max-w-2xl rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Enrollment Form') }}</flux:heading>
        </div>

        <form wire:submit="enroll" class="space-y-4 p-5">
            <flux:select wire:model="course_id" :label="__('Class')" required>
                <option value="">{{ __('Select class') }}</option>
                @foreach ($this->courses as $course)
                    <option value="{{ $course->id }}">{{ $course->title }}</option>
                @endforeach
            </flux:select>

            <flux:input wire:model="email" :label="__('Email address')" type="email" readonly />
            <flux:input wire:model="code" :label="__('Code')" type="text" required />

            <div class="flex items-center gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button variant="primary" type="submit" class="w-full">{{ __('Enroll now') }}</flux:button>
                <x-action-message on="enrollment-created">{{ __('Enrolled.') }}</x-action-message>
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700"><flux:heading size="lg">{{ __('My Enrollments') }}</flux:heading></div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-zinc-50 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-300">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Class') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Department') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Enrolled At') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->myEnrollments as $enrollment)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $enrollment->course?->title }}</td>
                            <td class="px-4 py-3">{{ $enrollment->course?->department?->name }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200">{{ ucfirst($enrollment->status) }}</span>
                            </td>
                            <td class="px-4 py-3 text-zinc-500">{{ optional($enrollment->enrolled_at)->format('M d, Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-zinc-500">{{ __('No enrollments yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
