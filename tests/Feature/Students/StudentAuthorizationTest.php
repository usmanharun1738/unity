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
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_faculty_cannot_view_student_outside_assigned_courses(): void
    {
        Role::findOrCreate(RoleName::Faculty->value, 'web');
        Role::findOrCreate(RoleName::Student->value, 'web');

        $viewerFaculty = User::factory()->create();
        $viewerFaculty->assignRole(RoleName::Faculty->value);
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
    }

    public function test_faculty_can_view_student_in_assigned_course(): void
    {
        Role::findOrCreate(RoleName::Faculty->value, 'web');
        Role::findOrCreate(RoleName::Student->value, 'web');

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

        $this->actingAs($faculty)
            ->get(route('students.show-profile', $student))
            ->assertOk();
    }

    public function test_faculty_cannot_submit_scores_above_hundred(): void
    {
        Role::findOrCreate(RoleName::Faculty->value, 'web');
        Role::findOrCreate(RoleName::Student->value, 'web');

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

        $this->actingAs($faculty);

        Livewire::test(Show::class, ['user' => $student])
            ->call('openAssessmentForm', $course->id)
            ->set('selected_course_id', $course->id)
            ->set('exam_score', '150')
            ->call('saveAssessmentScores')
            ->assertHasErrors(['exam_score' => ['max']]);

        $this->assertDatabaseCount((new Grade)->getTable(), 0);
    }

    public function test_admin_can_approve_and_reset_grade_approval(): void
    {
        Role::findOrCreate(RoleName::Admin->value, 'web');
        Role::findOrCreate(RoleName::Faculty->value, 'web');
        Role::findOrCreate(RoleName::Student->value, 'web');

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

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

        $this->actingAs($admin);

        Livewire::test(Show::class, ['user' => $student])
            ->call('approveGrade', $grade->id);

        $this->assertDatabaseHas('grades', [
            'id' => $grade->id,
            'is_approved_by_admin' => true,
            'approved_by' => $admin->id,
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
}
