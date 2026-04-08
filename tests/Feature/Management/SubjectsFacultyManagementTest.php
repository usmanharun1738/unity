<?php

namespace Tests\Feature\Management;

use App\Enums\RoleName;
use App\Models\Course;
use App\Models\FacultyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
