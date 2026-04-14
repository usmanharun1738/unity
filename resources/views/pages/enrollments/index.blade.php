<?php

use App\Actions\Courses\EnrollStudentInCourse;
use App\Livewire\Concerns\HasToastFeedback;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Enrollments')] class extends Component
{
    use HasToastFeedback;

    public ?int $course_id = null;

    public string $enrollment_key = '';

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

    #[Computed]
    public function canSelfEnroll(): bool
    {
        return auth()->user()->studentProfile()->exists();
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
            'enrollment_key' => ['required', 'string', 'max:32'],
        ]);

        $course = Course::query()->findOrFail($validated['course_id']);

        try {
            app(EnrollStudentInCourse::class)->handle(auth()->user(), $course, $validated['enrollment_key']);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }

            $this->errorToast(__('Enrollment failed. Please check the enrollment key.'));

            return;
        }

        $this->reset('course_id', 'enrollment_key');
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

    @if ($this->canSelfEnroll)
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
                <flux:input wire:model="enrollment_key" :label="__('Enrollment key')" type="text" required />

                <div class="flex items-center gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <flux:button variant="primary" type="submit" class="w-full">{{ __('Enroll now') }}</flux:button>
                    <x-action-message on="enrollment-created">{{ __('Enrolled.') }}</x-action-message>
                </div>
            </form>
        </div>
    @else
        <div class="mx-auto w-full max-w-2xl rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-200">
            {{ __('Self-enrollment is only available for student accounts.') }}
        </div>
    @endif

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
                            <td class="px-4 py-3 font-medium">
                                <a href="{{ route('courses.home', $enrollment->course) }}" wire:navigate class="hover:underline">
                                    {{ $enrollment->course?->title }}
                                </a>
                            </td>
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
