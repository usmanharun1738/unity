<?php

namespace Tests\Feature\Assignments;

use App\Enums\RoleName;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FacultyProfile;
use App\Models\Grade;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CourseAssignmentsTest extends TestCase
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
        $instructor = User::factory()->create();
        $instructor->assignRole(RoleName::Faculty->value);

        $facultyProfile = FacultyProfile::factory()->create([
            'user_id' => $instructor->id,
        ]);

        $course = Course::factory()->create([
            'faculty_profile_id' => $facultyProfile->id,
            'is_active' => true,
        ]);

        return compact('instructor', 'course');
    }

    public function test_instructor_can_create_assignment_for_owned_course(): void
    {
        ['instructor' => $instructor, 'course' => $course] = $this->createInstructorAndCourse();

        Livewire::actingAs($instructor)
            ->test('pages::courses.home', ['course' => $course])
            ->set('assignment_title', 'Week 1 Reflection')
            ->set('assignment_description', 'Submit your summary report.')
            ->set('assignment_due_date', now()->addDays(7)->format('Y-m-d\TH:i'))
            ->set('assignment_max_score', '100')
            ->call('createAssignment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('assignments', [
            'course_id' => $course->id,
            'title' => 'Week 1 Reflection',
        ]);
    }

    public function test_enrolled_student_can_submit_assignment_file(): void
    {
        Storage::fake('local');
        ['course' => $course] = $this->createInstructorAndCourse();

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create(['user_id' => $student->id]);

        Enrollment::query()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $assignment = Assignment::factory()->create([
            'course_id' => $course->id,
            'due_date' => now()->addDays(3),
        ]);

        Livewire::actingAs($student)
            ->test('pages::courses.home', ['course' => $course])
            ->set('submission_assignment_id', $assignment->id)
            ->set('submission_file', UploadedFile::fake()->create('solution.pdf', 128, 'application/pdf'))
            ->call('submitAssignment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('assignment_submissions', [
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
        ]);

        $storedPath = (string) DB::table('assignment_submissions')
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $student->id)
            ->value('file_path');

        $this->assertTrue(Storage::disk('local')->exists($storedPath));
    }

    public function test_non_enrolled_student_cannot_submit_assignment_file(): void
    {
        Storage::fake('local');
        ['course' => $course] = $this->createInstructorAndCourse();

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create(['user_id' => $student->id]);

        $assignment = Assignment::factory()->create([
            'course_id' => $course->id,
            'due_date' => now()->addDays(3),
        ]);

        Livewire::actingAs($student)
            ->test('pages::courses.home', ['course' => $course])
            ->set('submission_assignment_id', $assignment->id)
            ->set('submission_file', UploadedFile::fake()->create('solution.pdf', 128, 'application/pdf'))
            ->call('submitAssignment')
            ->assertForbidden();

        $this->assertDatabaseMissing('assignment_submissions', [
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_instructor_missing_assignments_manage_permission_cannot_create_assignment(): void
    {
        $facultyRole = Role::findByName(RoleName::Faculty->value, 'web');
        $facultyRole->revokePermissionTo('assignments.manage');

        ['instructor' => $instructor, 'course' => $course] = $this->createInstructorAndCourse();

        Livewire::actingAs($instructor)
            ->test('pages::courses.home', ['course' => $course])
            ->set('assignment_title', 'Restricted Assignment')
            ->set('assignment_description', 'Should not be created.')
            ->set('assignment_due_date', now()->addDays(5)->format('Y-m-d\TH:i'))
            ->set('assignment_max_score', '100')
            ->call('createAssignment')
            ->assertForbidden();

        $this->assertDatabaseMissing('assignments', [
            'course_id' => $course->id,
            'title' => 'Restricted Assignment',
        ]);
    }

    public function test_instructor_can_grade_assignment_submission_and_sync_grade_and_log(): void
    {
        ['instructor' => $instructor, 'course' => $course] = $this->createInstructorAndCourse();

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create(['user_id' => $student->id]);

        Enrollment::query()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $assignment = Assignment::factory()->create([
            'course_id' => $course->id,
            'title' => 'Essay 1',
            'max_score' => 50,
            'due_date' => now()->addDays(2),
        ]);

        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'score' => null,
            'feedback' => null,
            'graded_by' => null,
            'graded_at' => null,
        ]);

        Livewire::actingAs($instructor)
            ->test('pages::courses.home', ['course' => $course])
            ->set('submissionScores.'.$submission->id, '40')
            ->set('submissionFeedbacks.'.$submission->id, 'Good structure, improve citations.')
            ->call('gradeAssignmentSubmission', $submission->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('assignment_submissions', [
            'id' => $submission->id,
            'score' => 40,
            'feedback' => 'Good structure, improve citations.',
            'graded_by' => $instructor->id,
        ]);

        $grade = Grade::query()
            ->where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->first();

        $this->assertNotNull($grade);
        $this->assertSame('80.00', number_format((float) $grade->assignment_score, 2, '.', ''));

        $this->assertDatabaseHas('assessment_logs', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'assessment_type' => 'assignment',
            'assessment_name' => 'Assignment: Essay 1',
            'score' => 40,
            'max_score' => 50,
        ]);
    }

    public function test_instructor_missing_assignments_manage_permission_cannot_grade_submission(): void
    {
        $facultyRole = Role::findByName(RoleName::Faculty->value, 'web');
        $facultyRole->revokePermissionTo('assignments.manage');

        ['instructor' => $instructor, 'course' => $course] = $this->createInstructorAndCourse();

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create(['user_id' => $student->id]);

        Enrollment::query()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $assignment = Assignment::factory()->create([
            'course_id' => $course->id,
            'max_score' => 100,
            'due_date' => now()->addDays(2),
        ]);

        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'score' => null,
            'feedback' => null,
            'graded_by' => null,
            'graded_at' => null,
        ]);

        Livewire::actingAs($instructor)
            ->test('pages::courses.home', ['course' => $course])
            ->set('submissionScores.'.$submission->id, '70')
            ->set('submissionFeedbacks.'.$submission->id, 'Should not save')
            ->call('gradeAssignmentSubmission', $submission->id)
            ->assertForbidden();

        $this->assertDatabaseHas('assignment_submissions', [
            'id' => $submission->id,
            'score' => null,
            'feedback' => null,
            'graded_by' => null,
        ]);

        $this->assertDatabaseMissing('assessment_logs', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'assessment_type' => 'assignment',
        ]);
    }
}
