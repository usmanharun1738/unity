<?php

namespace Tests\Feature\Enrollments;

use App\Enums\RoleName;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FacultyProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstructorEnrollmentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    /**
     * @return array{instructor: User, course: Course}
     */
    private function createInstructorAndCourse(): array
    {
        Role::findOrCreate(RoleName::Faculty->value, 'web');
        Role::findOrCreate(RoleName::Student->value, 'web');

        $instructor = User::factory()->create();
        $instructor->assignRole(RoleName::Faculty->value);

        $facultyProfile = FacultyProfile::factory()->create([
            'user_id' => $instructor->id,
        ]);

        $course = Course::factory()->create([
            'faculty_profile_id' => $facultyProfile->id,
        ]);

        return compact('instructor', 'course');
    }

    public function test_instructor_can_generate_enrollment_key_for_own_course(): void
    {
        ['instructor' => $instructor, 'course' => $course] = $this->createInstructorAndCourse();

        $previousKey = $course->enrollment_key;

        Livewire::actingAs($instructor)
            ->test('pages::courses.home', ['course' => $course])
            ->call('generateEnrollmentKey')
            ->assertHasNoErrors();

        $course->refresh();

        $this->assertNotNull($course->enrollment_key);
        $this->assertNotSame($previousKey, $course->enrollment_key);
    }

    public function test_instructor_can_add_student_by_email(): void
    {
        ['instructor' => $instructor, 'course' => $course] = $this->createInstructorAndCourse();

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create(['user_id' => $student->id]);

        Livewire::actingAs($instructor)
            ->test('pages::courses.home', ['course' => $course])
            ->set('add_student_email', $student->email)
            ->call('addStudentByEmail')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('enrollments', [
            'course_id' => $course->id,
            'user_id' => $student->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_instructor_can_remove_enrolled_student(): void
    {
        ['instructor' => $instructor, 'course' => $course] = $this->createInstructorAndCourse();

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create(['user_id' => $student->id]);

        $enrollment = Enrollment::query()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        Livewire::actingAs($instructor)
            ->test('pages::courses.home', ['course' => $course])
            ->call('removeStudent', $enrollment->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('enrollments', [
            'id' => $enrollment->id,
        ]);
    }

    public function test_instructor_cannot_add_non_student_by_email(): void
    {
        ['instructor' => $instructor, 'course' => $course] = $this->createInstructorAndCourse();

        $faculty = User::factory()->create();
        $faculty->assignRole(RoleName::Faculty->value);

        Livewire::actingAs($instructor)
            ->test('pages::courses.home', ['course' => $course])
            ->set('add_student_email', $faculty->email)
            ->call('addStudentByEmail')
            ->assertHasErrors(['add_student_email']);

        $this->assertDatabaseMissing('enrollments', [
            'course_id' => $course->id,
            'user_id' => $faculty->id,
        ]);
    }

    public function test_instructor_missing_enrollments_manage_permission_cannot_generate_key(): void
    {
        $facultyRole = Role::findByName(RoleName::Faculty->value, 'web');
        $facultyRole->revokePermissionTo('enrollments.manage');

        ['instructor' => $instructor, 'course' => $course] = $this->createInstructorAndCourse();

        Livewire::actingAs($instructor)
            ->test('pages::courses.home', ['course' => $course])
            ->call('generateEnrollmentKey')
            ->assertForbidden();
    }
}
