<?php

use App\Enums\RoleName;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\FacultyProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $now = now();
        $startOfCurrentMonth = $now->copy()->startOfMonth();
        $startOfPreviousMonth = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $endOfPreviousMonth = $now->copy()->subMonthNoOverflow()->endOfMonth();

        $kpiCards = [
            [
                'label' => 'Total students',
                'value' => User::whereHas('roles', function ($query): void {
                    $query->where('name', RoleName::Student->value);
                })->count(),
            ],
            ['label' => 'Faculty', 'value' => FacultyProfile::count()],
            ['label' => 'Classes', 'value' => Course::count()],
            ['label' => 'Subjects', 'value' => Course::count()],
        ];

        $departmentDistribution = Department::query()
            ->leftJoin('courses', 'courses.department_id', '=', 'departments.id')
            ->leftJoin('enrollments', 'enrollments.course_id', '=', 'courses.id')
            ->select('departments.name', DB::raw('COUNT(enrollments.id) as count'))
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn (object $row): array => [
                'name' => (string) $row->name,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        $monthlyEnrollmentCounts = collect(range(5, 0))
            ->map(function (int $monthsAgo) use ($now): array {
                $start = $now->copy()->subMonthsNoOverflow($monthsAgo)->startOfMonth();
                $end = $start->copy()->endOfMonth();

                return [
                    'label' => $start->format('M'),
                    'value' => Enrollment::whereBetween('created_at', [$start, $end])->count(),
                ];
            })
            ->all();

        $monthComparison = [
            'current' => Enrollment::where('created_at', '>=', $startOfCurrentMonth)->count(),
            'previous' => Enrollment::whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth])->count(),
        ];

        return view('dashboard', [
            'kpiCards' => $kpiCards,
            'departmentDistribution' => $departmentDistribution,
            'monthlyEnrollmentCounts' => $monthlyEnrollmentCounts,
            'monthComparison' => $monthComparison,
        ]);
    })->name('dashboard');

    Route::livewire('classes/{course}/home', 'pages::courses.home')->name('courses.home');
    Route::livewire('classes/browse', 'pages::courses.browse')->name('courses.browse');
});

Route::middleware(['auth', 'verified', 'role:admin|department-staff'])
    ->group(function (): void {
        Route::livewire('departments', 'pages::departments.index')->name('departments.index');
        Route::livewire('departments/{department}', 'pages::departments.show')->name('departments.show');

        Route::livewire('subjects', 'pages::subjects.index')->name('subjects.index');
        Route::livewire('subjects/{course}', 'pages::subjects.show')->name('subjects.show');

        Route::livewire('faculty', 'pages::faculty.index')->name('faculty.index');
        Route::livewire('faculty/{facultyProfile}', 'pages::faculty.show')->name('faculty.show');

        Route::livewire('classes', 'pages::courses.index')->name('courses.index');
        Route::livewire('classes/{course}', 'pages::courses.show')->name('courses.show');
    });

Route::middleware(['auth', 'verified'])
    ->group(function (): void {
        Route::livewire('enrollments', 'pages::enrollments.index')->name('enrollments.index');
    });

require __DIR__.'/settings.php';
