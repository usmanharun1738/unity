<?php

namespace Tests\Feature\Management;

use App\Enums\RoleName;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
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
            ->set('enrollment_key', 'FIN101A-UNITY')
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

    public function test_admin_can_delete_empty_department(): void
    {
        Role::findOrCreate(RoleName::Admin->value, 'web');

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $department = Department::factory()->create();

        Livewire::actingAs($admin)
            ->test('pages::departments.show', ['department' => $department])
            ->call('deleteDepartment')
            ->assertRedirect(route('departments.index'));

        $this->assertDatabaseMissing('departments', [
            'id' => $department->id,
        ]);
    }

    public function test_department_delete_is_blocked_when_related_records_exist(): void
    {
        Role::findOrCreate(RoleName::Admin->value, 'web');

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $department = Department::factory()->create();
        Course::factory()->create(['department_id' => $department->id]);

        Livewire::actingAs($admin)
            ->test('pages::departments.show', ['department' => $department])
            ->call('deleteDepartment')
            ->assertSet('toastMessage', 'This department has related classes or faculty and cannot be deleted yet.');

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
        ]);
    }

    public function test_admin_can_delete_class_without_enrollments(): void
    {
        Role::findOrCreate(RoleName::Admin->value, 'web');

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $course = Course::factory()->create();

        Livewire::actingAs($admin)
            ->test('pages::courses.show', ['course' => $course])
            ->call('deleteCourse')
            ->assertRedirect(route('courses.index'));

        $this->assertDatabaseMissing('courses', [
            'id' => $course->id,
        ]);
    }

    public function test_class_delete_is_blocked_when_enrollments_exist(): void
    {
        Role::findOrCreate(RoleName::Admin->value, 'web');

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $student = User::factory()->create();
        $course = Course::factory()->create();

        Enrollment::query()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test('pages::courses.show', ['course' => $course])
            ->call('deleteCourse')
            ->assertSet('toastMessage', 'This class has enrollments and cannot be deleted yet.');

        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
        ]);
    }

    public function test_admin_can_update_department_from_department_detail_page(): void
    {
        Role::findOrCreate(RoleName::Admin->value, 'web');

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $department = Department::factory()->create([
            'name' => 'Original Department',
            'code' => 'ORIG',
            'description' => 'Original description',
        ]);

        Livewire::actingAs($admin)
            ->test('pages::departments.show', ['department' => $department])
            ->set('name', 'Updated Department')
            ->set('code', 'UPDT')
            ->set('description', 'Updated description')
            ->set('is_active', false)
            ->call('saveDepartment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'name' => 'Updated Department',
            'code' => 'UPDT',
            'description' => 'Updated description',
            'is_active' => 0,
        ]);
    }

    public function test_admin_can_update_class_from_class_detail_page(): void
    {
        Role::findOrCreate(RoleName::Admin->value, 'web');

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $department = Department::factory()->create();
        $newDepartment = Department::factory()->create();
        $facultyProfile = FacultyProfile::factory()->create(['department_id' => $newDepartment->id]);

        $course = Course::factory()->create([
            'department_id' => $department->id,
            'title' => 'Original Class',
            'code' => 'ORIG101',
        ]);

        Livewire::actingAs($admin)
            ->test('pages::courses.show', ['course' => $course])
            ->set('title', 'Updated Class')
            ->set('code', 'UPDT101')
            ->set('department_id', $newDepartment->id)
            ->set('faculty_profile_id', $facultyProfile->id)
            ->set('description', 'Updated class description')
            ->set('credits', 4)
            ->set('capacity', 45)
            ->set('semester', 'Fall')
            ->set('is_active', false)
            ->call('saveCourse')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'title' => 'Updated Class',
            'code' => 'UPDT101',
            'department_id' => $newDepartment->id,
            'faculty_profile_id' => $facultyProfile->id,
            'credits' => 4,
            'capacity' => 45,
            'semester' => 'Fall',
            'is_active' => 0,
        ]);
    }
}
