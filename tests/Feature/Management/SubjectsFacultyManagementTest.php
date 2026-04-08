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

class SubjectsFacultyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_subjects_and_faculty_pages(): void
    {
        Role::findOrCreate(RoleName::Admin->value, 'web');

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $faculty = FacultyProfile::factory()->create();
        $subject = Course::factory()->create(['faculty_profile_id' => $faculty->id]);

        $this->actingAs($admin)->get(route('subjects.index'))->assertOk();
        $this->actingAs($admin)->get(route('subjects.show', $subject))->assertOk();

        $this->actingAs($admin)->get(route('faculty.index'))->assertOk();
        $this->actingAs($admin)->get(route('faculty.show', $faculty))->assertOk();
    }

    public function test_department_staff_can_access_subjects_and_faculty_pages(): void
    {
        Role::findOrCreate(RoleName::DepartmentStaff->value, 'web');

        $staff = User::factory()->create();
        $staff->assignRole(RoleName::DepartmentStaff->value);

        $faculty = FacultyProfile::factory()->create();
        $subject = Course::factory()->create(['faculty_profile_id' => $faculty->id]);

        $this->actingAs($staff)->get(route('subjects.index'))->assertOk();
        $this->actingAs($staff)->get(route('subjects.show', $subject))->assertOk();

        $this->actingAs($staff)->get(route('faculty.index'))->assertOk();
        $this->actingAs($staff)->get(route('faculty.show', $faculty))->assertOk();
    }

    public function test_student_cannot_access_subjects_and_faculty_pages(): void
    {
        Role::findOrCreate(RoleName::Student->value, 'web');

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);

        $faculty = FacultyProfile::factory()->create();
        $subject = Course::factory()->create(['faculty_profile_id' => $faculty->id]);

        $this->actingAs($student)->get(route('subjects.index'))->assertForbidden();
        $this->actingAs($student)->get(route('subjects.show', $subject))->assertForbidden();

        $this->actingAs($student)->get(route('faculty.index'))->assertForbidden();
        $this->actingAs($student)->get(route('faculty.show', $faculty))->assertForbidden();
    }

    public function test_admin_can_create_subject_from_subjects_page(): void
    {
        Role::findOrCreate(RoleName::Admin->value, 'web');

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $department = Department::factory()->create();

        Livewire::actingAs($admin)
            ->test('pages::subjects.index')
            ->set('title', 'Statistics')
            ->set('code', 'STAT101')
            ->set('department_id', $department->id)
            ->set('description', 'Statistics foundation')
            ->call('createSubject')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('courses', [
            'title' => 'Statistics',
            'code' => 'STAT101',
            'department_id' => $department->id,
        ]);
    }

    public function test_admin_can_update_subject_from_subject_detail_page(): void
    {
        Role::findOrCreate(RoleName::Admin->value, 'web');

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $subject = Course::factory()->create([
            'title' => 'Old Subject',
            'description' => 'Old description',
        ]);

        Livewire::actingAs($admin)
            ->test('pages::subjects.show', ['course' => $subject])
            ->set('title', 'Updated Subject')
            ->set('description', 'Updated description')
            ->call('saveSubject')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('courses', [
            'id' => $subject->id,
            'title' => 'Updated Subject',
            'description' => 'Updated description',
        ]);
    }

    public function test_admin_can_create_faculty_profile_from_faculty_page(): void
    {
        Role::findOrCreate(RoleName::Admin->value, 'web');

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $teacherUser = User::factory()->create();
        $department = Department::factory()->create();

        Livewire::actingAs($admin)
            ->test('pages::faculty.index')
            ->set('user_id', $teacherUser->id)
            ->set('department_id', $department->id)
            ->set('employee_code', 'EMP-9001')
            ->set('title', 'Professor')
            ->set('bio', 'Teaches advanced mathematics')
            ->call('createFacultyProfile')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('faculty_profiles', [
            'user_id' => $teacherUser->id,
            'department_id' => $department->id,
            'employee_code' => 'EMP-9001',
        ]);

        $this->assertTrue($teacherUser->fresh()->hasRole(RoleName::Faculty->value));
    }

    public function test_admin_can_update_faculty_profile_from_profile_page(): void
    {
        Role::findOrCreate(RoleName::Admin->value, 'web');

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $faculty = FacultyProfile::factory()->create([
            'title' => 'Lecturer',
            'bio' => 'Original bio',
        ]);
        $newDepartment = Department::factory()->create();

        Livewire::actingAs($admin)
            ->test('pages::faculty.show', ['facultyProfile' => $faculty])
            ->set('department_id', $newDepartment->id)
            ->set('title', 'Senior Lecturer')
            ->set('bio', 'Updated bio')
            ->call('saveProfile')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('faculty_profiles', [
            'id' => $faculty->id,
            'department_id' => $newDepartment->id,
            'title' => 'Senior Lecturer',
            'bio' => 'Updated bio',
        ]);
    }

    public function test_admin_can_delete_subject_from_subject_detail_page(): void
    {
        Role::findOrCreate(RoleName::Admin->value, 'web');

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $subject = Course::factory()->create();

        Livewire::actingAs($admin)
            ->test('pages::subjects.show', ['course' => $subject])
            ->call('deleteSubject')
            ->assertRedirect(route('subjects.index'));

        $this->assertDatabaseMissing('courses', [
            'id' => $subject->id,
        ]);
    }

    public function test_admin_can_delete_faculty_profile_from_profile_page(): void
    {
        Role::findOrCreate(RoleName::Admin->value, 'web');
        Role::findOrCreate(RoleName::Faculty->value, 'web');

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $facultyUser = User::factory()->create();
        $facultyUser->assignRole(RoleName::Faculty->value);
        $faculty = FacultyProfile::factory()->create(['user_id' => $facultyUser->id]);

        Livewire::actingAs($admin)
            ->test('pages::faculty.show', ['facultyProfile' => $faculty])
            ->call('deleteProfile')
            ->assertRedirect(route('faculty.index'));

        $this->assertDatabaseMissing('faculty_profiles', [
            'id' => $faculty->id,
        ]);

        $this->assertFalse($facultyUser->fresh()->hasRole(RoleName::Faculty->value));
    }
}
