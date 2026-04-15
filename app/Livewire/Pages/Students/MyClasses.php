<?php

namespace App\Livewire\Pages\Students;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('My Class Students')]
class MyClasses extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $search = '';

    public ?int $course_id = null;

    public string $sort_by = 'name';

    public string $sort_direction = 'asc';

    public int $per_page = 15;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);

        abort_unless(auth()->user()->can('students.view-any'), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCourseId(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        if (! in_array($this->sort_by, ['name', 'student_number', 'enrolled_at'], true)) {
            $this->sort_by = 'name';
        }

        $this->resetPage();
    }

    public function updatedSortDirection(): void
    {
        if (! in_array($this->sort_direction, ['asc', 'desc'], true)) {
            $this->sort_direction = 'asc';
        }

        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->per_page, [10, 15, 25, 50], true)) {
            $this->per_page = 15;
        }

        $this->resetPage();
    }

    #[Computed]
    public function myCourses()
    {
        return auth()->user()
            ->facultyProfile
            ->courses()
            ->where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title']);
    }

    #[Computed]
    public function students()
    {
        return User::query()
            ->with(['studentProfile', 'enrollments.course'])
            ->whereHas('enrollments', function (Builder $query): void {
                $query->whereHas('course', function (Builder $subQuery): void {
                    $subQuery->where('faculty_profile_id', auth()->user()->facultyProfile->id);
                })->when($this->course_id, function (Builder $q): void {
                    $q->where('course_id', $this->course_id);
                });
            })
            ->when($this->search, function (Builder $query): void {
                $query->where(function (Builder $q): void {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhereHas('studentProfile', function (Builder $subQuery): void {
                            $subQuery->where('student_number', 'like', "%{$this->search}%");
                        });
                });
            })
            ->when($this->sort_by === 'name', function (Builder $query): void {
                $query->orderBy('name', $this->sort_direction);
            })
            ->when($this->sort_by === 'student_number', function (Builder $query): void {
                $query->orderByRaw(
                    "COALESCE((SELECT student_number FROM student_profiles WHERE student_profiles.user_id = users.id), '') {$this->sort_direction}"
                );
            })
            ->when($this->sort_by === 'enrolled_at', function (Builder $query): void {
                $query->orderByRaw(
                    "COALESCE((SELECT MAX(enrolled_at) FROM enrollments WHERE enrollments.user_id = users.id), '') {$this->sort_direction}"
                );
            })
            ->distinct()
            ->paginate($this->per_page);
    }

    public function render()
    {
        return view('pages.students.my-classes');
    }
}
