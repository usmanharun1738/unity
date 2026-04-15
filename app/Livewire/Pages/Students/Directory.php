<?php

namespace App\Livewire\Pages\Students;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Student Directory')]
class Directory extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $search = '';

    public ?int $department_id = null;

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

    public function updatedDepartmentId(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        if (! in_array($this->sort_by, ['name', 'student_number', 'major'], true)) {
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
    public function departments()
    {
        return Department::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function students()
    {
        $departmentName = null;

        if ($this->department_id !== null) {
            $departmentName = Department::query()
                ->whereKey($this->department_id)
                ->value('name');
        }

        return User::query()
            ->with('studentProfile')
            ->whereHas('roles', function (Builder $query): void {
                $query->where('name', 'student');
            })
            ->when($this->search, function (Builder $query): void {
                $query->where(function (Builder $q): void {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhereHas('studentProfile', function (Builder $subQuery): void {
                            $subQuery->where('student_number', 'like', "%{$this->search}%");
                        });
                });
            })
            ->when($departmentName, function (Builder $query) use ($departmentName): void {
                $query->whereHas('studentProfile', function (Builder $subQuery) use ($departmentName): void {
                    $subQuery->where('major', 'like', "%{$departmentName}%");
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
            ->when($this->sort_by === 'major', function (Builder $query): void {
                $query->orderByRaw(
                    "COALESCE((SELECT major FROM student_profiles WHERE student_profiles.user_id = users.id), '') {$this->sort_direction}"
                );
            })
            ->paginate($this->per_page);
    }

    public function render()
    {
        return view('pages.students.directory');
    }
}
