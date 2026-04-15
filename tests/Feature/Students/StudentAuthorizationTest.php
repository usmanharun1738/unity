<?php

namespace Tests\Feature\Students;

use App\Enums\RoleName;
use App\Livewire\Pages\Students\Show;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FacultyProfile;
use App\Models\Grade;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $permissions
     */
    private function ensurePermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    public function test_faculty_cannot_view_student_outside_assigned_courses(): void
    {
        Role::findOrCreate(RoleName::Faculty->value, 'web');
        Role::findOrCreate(RoleName::Student->value, 'web');
        $this->ensurePermissions(['students.view-profile']);

        $viewerFaculty = User::factory()->create();
        $viewerFaculty->assignRole(RoleName::Faculty->value);
        $viewerFaculty->givePermissionTo('students.view-profile');
        $viewerFacultyProfile = FacultyProfile::factory()->create(['user_id' => $viewerFaculty->id]);

        $otherFaculty = User::factory()->create();
        $otherFaculty->assignRole(RoleName::Faculty->value);
        $otherFacultyProfile = FacultyProfile::factory()->create(['user_id' => $otherFaculty->id]);

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create(['user_id' => $student->id]);

        $otherFacultyCourse = Course::factory()->create([
            'faculty_profile_id' => $otherFacultyProfile->id,
            'department_id' => $otherFacultyProfile->department_id,
        ]);

        Enrollment::factory()->create([
            'user_id' => $student->id,
            'course_id' => $otherFacultyCourse->id,
            'status' => 'active',
        ]);

        $this->actingAs($viewerFaculty)
            ->get(route('students.show-profile', $student))
            ->assertForbidden();

        $this->assertNotNull($viewerFacultyProfile);
    }

    public function test_faculty_can_view_student_in_assigned_course(): void
    {
        Role::findOrCreate(RoleName::Faculty->value, 'web');
        Role::findOrCreate(RoleName::Student->value, 'web');
        $this->ensurePermissions(['students.view-profile']);

        $faculty = User::factory()->create();
        $faculty->assignRole(RoleName::Faculty->value);
        $faculty->givePermissionTo('students.view-profile');
        $facultyProfile = FacultyProfile::factory()->create(['user_id' => $faculty->id]);

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create(['user_id' => $student->id]);

        $course = Course::factory()->create([
            'faculty_profile_id' => $facultyProfile->id,
            'department_id' => $facultyProfile->department_id,
        ]);

        Enrollment::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        $this->actingAs($faculty)
            ->get(route('students.show-profile', $student))
            ->assertOk();
    }

    public function test_faculty_cannot_submit_scores_above_hundred(): void
    {
        Role::findOrCreate(RoleName::Faculty->value, 'web');
        Role::findOrCreate(RoleName::Student->value, 'web');
        $this->ensurePermissions(['students.view-profile', 'students.manage-assessments', 'grades.manage']);

        $faculty = User::factory()->create();
        $faculty->assignRole(RoleName::Faculty->value);
        $faculty->givePermissionTo(['students.view-profile', 'students.manage-assessments', 'grades.manage']);
        $facultyProfile = FacultyProfile::factory()->create(['user_id' => $faculty->id]);

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create(['user_id' => $student->id]);

        $course = Course::factory()->create([
            'faculty_profile_id' => $facultyProfile->id,
            'department_id' => $facultyProfile->department_id,
        ]);

        Enrollment::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        $this->actingAs($faculty);

        Livewire::test(Show::class, ['user' => $student])
            ->call('openAssessmentForm', $course->id)
            ->set('selected_course_id', $course->id)
            ->set('exam_score', '150')
            ->call('saveAssessmentScores')
            ->assertHasErrors(['exam_score' => ['max']]);

        $this->assertDatabaseCount((new Grade)->getTable(), 0);
    }

    public function test_faculty_without_grades_approve_cannot_approve_or_reset_grade(): void
    {
        Role::findOrCreate(RoleName::Faculty->value, 'web');
        Role::findOrCreate(RoleName::Student->value, 'web');
        $this->ensurePermissions(['students.view-profile', 'students.manage-assessments', 'grades.manage', 'grades.approve']);

        $faculty = User::factory()->create();
        $faculty->assignRole(RoleName::Faculty->value);
        $faculty->givePermissionTo(['students.view-profile', 'students.manage-assessments', 'grades.manage']);
        $facultyProfile = FacultyProfile::factory()->create(['user_id' => $faculty->id]);

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create(['user_id' => $student->id]);

        $course = Course::factory()->create([
            'faculty_profile_id' => $facultyProfile->id,
            'department_id' => $facultyProfile->department_id,
        ]);

        Enrollment::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        $grade = Grade::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'is_approved_by_admin' => false,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $this->actingAs($faculty);

        Livewire::test(Show::class, ['user' => $student])
            ->call('approveGrade', $grade->id)
            ->assertForbidden();

        Grade::query()->whereKey($grade->id)->update([
            'is_approved_by_admin' => true,
        ]);

        Livewire::test(Show::class, ['user' => $student])
            ->call('revokeGradeApproval', $grade->id)
            ->assertForbidden();

        $this->assertDatabaseHas('grades', [
            'id' => $grade->id,
            'is_approved_by_admin' => true,
        ]);
    }

    public function test_department_staff_with_grades_approve_can_approve_and_reset_grade(): void
    {
        Role::findOrCreate(RoleName::DepartmentStaff->value, 'web');
        Role::findOrCreate(RoleName::Faculty->value, 'web');
        Role::findOrCreate(RoleName::Student->value, 'web');
        $this->ensurePermissions(['students.view-profile', 'grades.approve']);

        $departmentStaff = User::factory()->create();
        $departmentStaff->assignRole(RoleName::DepartmentStaff->value);
        $departmentStaff->givePermissionTo(['students.view-profile', 'grades.approve']);

        $faculty = User::factory()->create();
        $faculty->assignRole(RoleName::Faculty->value);
        $facultyProfile = FacultyProfile::factory()->create(['user_id' => $faculty->id]);

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create(['user_id' => $student->id]);

        $course = Course::factory()->create([
            'faculty_profile_id' => $facultyProfile->id,
            'department_id' => $facultyProfile->department_id,
        ]);

        Enrollment::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        $grade = Grade::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'is_approved_by_admin' => false,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $this->actingAs($departmentStaff);

        Livewire::test(Show::class, ['user' => $student])
            ->call('approveGrade', $grade->id);

        $this->assertDatabaseHas('grades', [
            'id' => $grade->id,
            'is_approved_by_admin' => true,
            'approved_by' => $departmentStaff->id,
        ]);

        Livewire::test(Show::class, ['user' => $student])
            ->call('revokeGradeApproval', $grade->id);

        $this->assertDatabaseHas('grades', [
            'id' => $grade->id,
            'is_approved_by_admin' => false,
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function test_user_with_role_but_missing_permission_gets_forbidden(): void
    {
        Role::findOrCreate(RoleName::Faculty->value, 'web');

        $faculty = User::factory()->create();
        $faculty->assignRole(RoleName::Faculty->value);
        FacultyProfile::factory()->create(['user_id' => $faculty->id]);

        $this->actingAs($faculty)
            ->get(route('students.my-classes'))
            ->assertForbidden();
    }

    public function test_admin_can_render_students_directory(): void
    {
        Role::findOrCreate(RoleName::Admin->value, 'web');
        $this->ensurePermissions(['students.view-any']);

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $admin->givePermissionTo('students.view-any');

        $this->actingAs($admin)
            ->get(route('students.directory'))
            ->assertOk();
    }
}
