<?php

use App\Actions\Courses\EnrollStudentInCourse;
use App\Actions\Courses\EnrollStudentInCourseByInstructor;
use App\Actions\Courses\GenerateEnrollmentKey;
use App\Enums\RoleName;
use App\Livewire\Concerns\HasToastFeedback;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Course Home')] class extends Component
{
    use HasToastFeedback;
    use WithFileUploads;

    public Course $course;

    public string $enrollment_key = '';

    public string $syllabus_content = '';

    public string $module_title = '';

    public ?int $module_week_number = null;

    public string $module_description = '';

    public int $module_position = 0;

    public string $material_title = '';

    public string $material_description = '';

    public ?int $material_module_id = null;

    /** @var mixed */
    public $material_file = null;

    public string $syllabus_file_title = '';

    public string $syllabus_file_description = '';

    /** @var mixed */
    public $syllabus_file = null;

    public string $add_student_email = '';

    public function mount(Course $course): void
    {
        Gate::authorize('view', $course);
        $this->course = $course->load(['department', 'facultyProfile.user', 'enrollments']);
        $this->syllabus_content = $this->course->syllabus_content ?? '';
        $this->pullToastFromSession();
    }

    public function refreshCourse(): void
    {
        $this->course->refresh()->load(['department', 'facultyProfile.user', 'enrollments']);
    }

    #[Computed]
    public function isEnrolled(): bool
    {
        return $this->course->enrollments->contains('user_id', auth()->id());
    }

    #[Computed]
    public function isManager(): bool
    {
        return auth()->user()->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value]);

    }

    #[Computed]
    public function isInstructor(): bool
    {
        return $this->course->faculty_profile_id && $this->course->facultyProfile?->user_id === auth()->id();
    }

    #[Computed]
    public function canAccessLearningContent(): bool
    {
        return $this->isManager || $this->isInstructor || $this->isEnrolled;
    }

    #[Computed]
    public function canManageCourse(): bool
    {
        return $this->isManager || $this->isInstructor;
    }

    #[Computed]
    public function modules()
    {
        return $this->course->modules()
            ->with(['materials' => fn ($query) => $query->where('is_syllabus', false)->latest()])
            ->get();
    }

    #[Computed]
    public function syllabusFiles()
    {
        return $this->course->materials()
            ->where('is_syllabus', true)
            ->latest()
            ->get();
    }

    public function enroll(): void
    {
        Gate::authorize('create', Enrollment::class);

        $validated = $this->validate([
            'enrollment_key' => ['required', 'string', 'max:32'],
        ]);

        try {
            app(EnrollStudentInCourse::class)->handle(auth()->user(), $this->course, $validated['enrollment_key']);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }

            $this->errorToast(__('Enrollment failed. Please check the enrollment key.'));

            return;
        }

        $this->refreshCourse();
        $this->reset('enrollment_key');
        $this->successToast(__('You are now enrolled in this class.'));
    }

    public function saveSyllabus(): void
    {
        Gate::authorize('update', $this->course);

        $validated = $this->validate([
            'syllabus_content' => ['nullable', 'string', 'max:20000'],
        ]);

        $this->course->update([
            'syllabus_content' => $validated['syllabus_content'] !== '' ? $validated['syllabus_content'] : null,
            'syllabus_updated_at' => now(),
        ]);

        $this->refreshCourse();
        $this->successToast(__('Syllabus updated successfully.'));
    }

    public function createModule(): void
    {
        Gate::authorize('update', $this->course);

        $validated = $this->validate([
            'module_title' => ['required', 'string', 'max:150'],
            'module_week_number' => ['nullable', 'integer', 'between:1,52'],
            'module_description' => ['nullable', 'string', 'max:2000'],
            'module_position' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        CourseModule::query()->create([
            'course_id' => $this->course->id,
            'title' => $validated['module_title'],
            'week_number' => $validated['module_week_number'],
            'description' => $validated['module_description'] !== '' ? $validated['module_description'] : null,
            'position' => $validated['module_position'] ?? 0,
        ]);

        $this->reset(['module_title', 'module_week_number', 'module_description', 'module_position']);
        $this->refreshCourse();
        $this->successToast(__('Module created successfully.'));
    }

    public function uploadSyllabusFile(): void
    {
        Gate::authorize('update', $this->course);

        $validated = $this->validate([
            'syllabus_file_title' => ['required', 'string', 'max:150'],
            'syllabus_file_description' => ['nullable', 'string', 'max:400'],
            'syllabus_file' => ['required', 'file', 'max:10240'],
        ]);

        $path = $this->syllabus_file->store('course-materials/'.$this->course->id.'/syllabus', 'local');

        CourseMaterial::query()->create([
            'course_id' => $this->course->id,
            'course_module_id' => null,
            'uploaded_by' => auth()->id(),
            'title' => $validated['syllabus_file_title'],
            'description' => $validated['syllabus_file_description'] !== '' ? $validated['syllabus_file_description'] : null,
            'file_path' => $path,
            'original_name' => $this->syllabus_file->getClientOriginalName(),
            'mime_type' => $this->syllabus_file->getClientMimeType(),
            'size_bytes' => $this->syllabus_file->getSize() ?? 0,
            'is_syllabus' => true,
        ]);

        $this->reset(['syllabus_file_title', 'syllabus_file_description', 'syllabus_file']);
        $this->refreshCourse();
        $this->successToast(__('Syllabus file uploaded successfully.'));
    }

    public function uploadModuleMaterial(): void
    {
        Gate::authorize('update', $this->course);

        $validated = $this->validate([
            'material_title' => ['required', 'string', 'max:150'],
            'material_description' => ['nullable', 'string', 'max:400'],
            'material_module_id' => ['required', 'exists:course_modules,id'],
            'material_file' => ['required', 'file', 'max:10240'],
        ]);

        $module = CourseModule::query()
            ->where('course_id', $this->course->id)
            ->findOrFail((int) $validated['material_module_id']);

        $path = $this->material_file->store('course-materials/'.$this->course->id.'/modules/'.$module->id, 'local');

        CourseMaterial::query()->create([
            'course_id' => $this->course->id,
            'course_module_id' => $module->id,
            'uploaded_by' => auth()->id(),
            'title' => $validated['material_title'],
            'description' => $validated['material_description'] !== '' ? $validated['material_description'] : null,
            'file_path' => $path,
            'original_name' => $this->material_file->getClientOriginalName(),
            'mime_type' => $this->material_file->getClientMimeType(),
            'size_bytes' => $this->material_file->getSize() ?? 0,
            'is_syllabus' => false,
        ]);

        $this->reset(['material_title', 'material_description', 'material_module_id', 'material_file']);
        $this->refreshCourse();
        $this->successToast(__('Module material uploaded successfully.'));
    }

    public function deleteMaterial(int $materialId): void
    {
        Gate::authorize('update', $this->course);

        $material = CourseMaterial::query()
            ->where('course_id', $this->course->id)
            ->findOrFail($materialId);

        if (Storage::disk('local')->exists($material->file_path)) {
            Storage::disk('local')->delete($material->file_path);
        }

        $material->delete();
        $this->refreshCourse();
        $this->successToast(__('Material deleted.'));
    }

    public function deleteModule(int $moduleId): void
    {
        Gate::authorize('update', $this->course);

        $module = CourseModule::query()
            ->where('course_id', $this->course->id)
            ->with('materials')
            ->findOrFail($moduleId);

        foreach ($module->materials as $material) {
            if (Storage::disk('local')->exists($material->file_path)) {
                Storage::disk('local')->delete($material->file_path);
            }
        }

        $module->delete();

        if ($this->material_module_id === $moduleId) {
            $this->material_module_id = null;
        }

        $this->refreshCourse();
        $this->successToast(__('Module deleted.'));
    }

    public function generateEnrollmentKey(): void
    {
        Gate::authorize('update', $this->course);

        app(GenerateEnrollmentKey::class)->handle($this->course);
        $this->refreshCourse();
        $this->successToast(__('Enrollment key generated successfully.'));
    }

    public function addStudentByEmail(): void
    {
        Gate::authorize('update', $this->course);

        $validated = $this->validate([
            'add_student_email' => ['required', 'email', 'exists:users,email'],
        ]);

        $student = User::query()->where('email', $validated['add_student_email'])->firstOrFail();

        try {
            app(EnrollStudentInCourseByInstructor::class)->handle($this->course, $student);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError('add_student_email', $message);
                }
            }

            $this->errorToast(__('Failed to enroll student.'));

            return;
        }

        $this->reset('add_student_email');
        $this->refreshCourse();
        $this->successToast(__('Student enrolled successfully.'));
    }

    public function removeStudent(int $enrollmentId): void
    {
        Gate::authorize('update', $this->course);

        $enrollment = Enrollment::query()
            ->where('course_id', $this->course->id)
            ->findOrFail($enrollmentId);

        $enrollment->delete();
        $this->refreshCourse();
        $this->successToast(__('Student removed from course.'));
    }

    #[Computed]
    public function enrolledStudents()
    {
        return $this->course->students()->get();
    }
}; ?>
<div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
    <x-ui.toast :message="$toastMessage" :variant="$toastVariant" />

    <div class="space-y-4">
        <div class="text-sm text-zinc-500">
            <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 hover:underline dark:hover:text-zinc-200">{{ __('Dashboard') }}</a>
            <span class="mx-2">/</span>
            <a href="{{ route('enrollments.index') }}" wire:navigate class="hover:text-zinc-700 hover:underline dark:hover:text-zinc-200">{{ __('Classes') }}</a>
            <span class="mx-2">/</span>
            <span>{{ __('Course Home') }}</span>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ $course->title }}</flux:heading>
                <flux:subheading>{{ $course->department?->name }} · {{ $course->code }}</flux:subheading>
            </div>

            @if ($this->canManageCourse)
                <flux:button variant="ghost" :href="route('courses.show', $course)" wire:navigate>
                    {{ __('Manage class') }}
                </flux:button>
            @endif
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm lg:col-span-2 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Class Overview') }}</h2>
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $course->description ?: __('No description provided yet.') }}</p>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="text-sm text-zinc-500">{{ __('Instructor') }}</div>
                    <div class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">{{ $course->facultyProfile?->user?->name ?? __('Unassigned') }}</div>
                </div>

                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="text-sm text-zinc-500">{{ __('Department') }}</div>
                    <div class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">{{ $course->department?->name }}</div>
                </div>

                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="text-sm text-zinc-500">{{ __('Capacity') }}</div>
                    <div class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">{{ $course->capacity ?? '—' }}</div>
                </div>

                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="text-sm text-zinc-500">{{ __('Enrollment status') }}</div>
                    <div class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $this->isEnrolled ? __('Enrolled') : ($course->is_active ? __('Open') : __('Archived')) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Join this class') }}</h2>
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Enter the enrollment key shared by your instructor to join.') }}</p>

            @if ($this->isEnrolled)
                <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200">
                    {{ __('You are already enrolled in this class.') }}
                </div>
            @else
                <form wire:submit="enroll" class="mt-4 space-y-4">
                    <flux:input wire:model="enrollment_key" :label="__('Enrollment key')" type="text" required />
                    <flux:button variant="primary" type="submit" class="w-full">{{ __('Join class') }}</flux:button>
                </form>
            @endif

            <div class="mt-4 text-sm text-zinc-500">
                {{ __('Course code') }}: {{ $course->code }}
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Syllabus') }}</h2>
                @if ($course->syllabus_updated_at)
                    <span class="text-xs text-zinc-500">{{ __('Updated') }} {{ $course->syllabus_updated_at->diffForHumans() }}</span>
                @endif
            </div>

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
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Modules & Learning Materials') }}</h2>

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
        </div>

        @if ($this->canManageCourse)
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Enrollment Management') }}</h2>

                <div class="mt-4 space-y-6">
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <flux:button size="sm" wire:click="generateEnrollmentKey" wire:confirm="{{ __('Generate a new enrollment key? The current key will no longer work.') }}">
                                {{ __('Generate key') }}
                            </flux:button>
                        </div>

                        @if ($course->enrollment_key)
                            <div class="mt-4 flex flex-col gap-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-900/40 dark:bg-emerald-950/30">
                                <div class="text-xs font-medium text-emerald-700 dark:text-emerald-200">{{ __('Current enrollment key') }}</div>
                                <div class="font-mono text-lg font-semibold text-emerald-900 dark:text-emerald-100">{{ $course->enrollment_key }}</div>
                            </div>
                        @else
                            <div class="mt-4 text-sm text-zinc-500">{{ __('No enrollment key generated yet. Generate one to allow students to self-enroll.') }}</div>
                        @endif
                    </div>

                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <h3 class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('Add student by email') }}</h3>
                        <p class="mt-1 text-sm text-zinc-500">{{ __('Enroll a student directly without requiring an enrollment key.') }}</p>

                        <form wire:submit="addStudentByEmail" class="mt-4 space-y-3">
                            <flux:input wire:model="add_student_email" :label="__('Student email')" type="email" required />
                            <flux:button variant="primary" type="submit" class="w-full">{{ __('Enroll student') }}</flux:button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Enrolled Students') }}</h2>

                <div class="mt-4 space-y-2">
                    @forelse ($this->enrolledStudents as $student)
                        <div class="flex flex-col gap-2 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $student->name }}</div>
                                <div class="text-xs text-zinc-500">{{ $student->email }}</div>
                            </div>
                            @php
                                $enrollment = $student->enrollments()->where('course_id', $course->id)->first();
                            @endphp
                            @if ($enrollment)
                                <flux:button size="sm" variant="danger" wire:click="removeStudent({{ $enrollment->id }})" wire:confirm="{{ __('Remove this student from the course?') }}">
                                    {{ __('Remove') }}
                                </flux:button>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-xl border border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                            {{ __('No students enrolled yet.') }}
                        </div>
                    @endforelse
                </div>
            </div>
        @endif
    </div>
</div>

