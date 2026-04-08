<?php

namespace Tests\Feature\Management;

use App\Enums\RoleName;
use App\Models\Course;
use App\Models\Department;
use App\Models\FacultyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DepartmentCourseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_management_pages(): void
    {
        Role::findOrCreate(RoleName::Admin->value, 'web');

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $department = Department::factory()->create();
        $course = Course::factory()->create(['department_id' => $department->id]);

        $this->actingAs($admin)
            ->get(route('departments.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('departments.show', $department))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('courses.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('courses.show', $course))
            ->assertOk();
    }

    public function test_department_staff_can_access_management_pages(): void
    {
        Role::findOrCreate(RoleName::DepartmentStaff->value, 'web');

        $staff = User::factory()->create();
        $staff->assignRole(RoleName::DepartmentStaff->value);

        $department = Department::factory()->create();
        $course = Course::factory()->create(['department_id' => $department->id]);

        $this->actingAs($staff)
            ->get(route('departments.index'))
            ->assertOk();

        $this->actingAs($staff)
            ->get(route('departments.show', $department))
            ->assertOk();

        $this->actingAs($staff)
            ->get(route('courses.index'))
            ->assertOk();

        $this->actingAs($staff)
            ->get(route('courses.show', $course))
            ->assertOk();
    }

    public function test_student_cannot_access_management_pages(): void
    {
        Role::findOrCreate(RoleName::Student->value, 'web');

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);

        $department = Department::factory()->create();
        $course = Course::factory()->create(['department_id' => $department->id]);

        $this->actingAs($student)
            ->get(route('departments.index'))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('departments.show', $department))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('courses.index'))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('courses.show', $course))
            ->assertForbidden();
    }

    public function test_admin_can_create_department_from_livewire_page(): void
    {
        Role::findOrCreate(RoleName::Admin->value, 'web');

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test('pages::departments.index')
            ->set('name', 'Biology')
            ->set('code', 'BIO')
            ->set('description', 'Life sciences')
            ->call('createDepartment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('departments', [
            'name' => 'Biology',
            'code' => 'BIO',
        ]);
    }

    public function test_admin_can_create_course_from_livewire_page(): void
    {
        Role::findOrCreate(RoleName::Admin->value, 'web');

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $department = Department::factory()->create();
        $facultyProfile = FacultyProfile::factory()->create(['department_id' => $department->id]);

        Livewire::actingAs($admin)
            ->test('pages::courses.index')
            ->set('title', 'Finance Basics - Section A')
            ->set('code', 'FIN101A')
            ->set('department_id', $department->id)
            ->set('faculty_profile_id', $facultyProfile->id)
            ->set('description', 'Budgeting and valuation')
            ->set('credits', 3)
            ->set('capacity', 40)
            ->set('semester', 'Spring')
            ->call('createCourse')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('courses', [
            'title' => 'Finance Basics - Section A',
            'code' => 'FIN101A',
            'department_id' => $department->id,
        ]);
    }
}
