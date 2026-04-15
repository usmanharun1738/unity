<?php

namespace Tests\Feature\Quizzes;

use App\Enums\RoleName;
use App\Models\AssessmentLog;
use App\Models\Course;
use App\Models\FacultyProfile;
use App\Models\Grade;
use App\Models\Quiz;
use App\Models\QuizResponse;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CourseQuizzesTest extends TestCase
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

    /**
     * @return array{student: User, studentProfile: StudentProfile}
     */
    private function createStudent(): array
    {
        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);

        $studentProfile = StudentProfile::factory()->create([
            'user_id' => $student->id,
        ]);

        return compact('student', 'studentProfile');
    }

    public function test_instructor_can_grade_quiz_response_and_sync_grade(): void
    {
        ['instructor' => $instructor, 'course' => $course] = $this->createInstructorAndCourse();
        ['student' => $student] = $this->createStudent();

        $course->students()->attach($student, ['status' => 'enrolled']);

        $quiz = $course->quizzes()->save(Quiz::factory()->make(['max_score' => 50]));
        $response = $quiz->responses()->save(
            QuizResponse::factory()->make(['user_id' => $student->id, 'score' => null]),
        );

        Livewire::actingAs($instructor)
            ->test('pages::courses.home', ['course' => $course])
            ->set('quizResponseScores', [$response->id => 40])
            ->call('gradeQuizResponse', $response->id);

        $response->refresh();
        $this->assertEquals(40, $response->score);

        $grade = Grade::where(['user_id' => $student->id, 'course_id' => $course->id])->first();
        $this->assertNotNull($grade);
        // Normalized: (40/50) * 100 = 80
        $this->assertEquals(80, $grade->quiz_score);

        // Final grade: (0*0.25) + (0*0.15) + (80*0.10) + (0*0.10) + (0*0.10) + (0*0.30) = 8
        $expectedFinal = (80 * 0.10);
        $this->assertEquals(round($expectedFinal, 2), $grade->final_grade);
        $this->assertEquals('F', $grade->grade_letter);
    }

    public function test_quiz_aggregate_calculated_from_multiple_responses(): void
    {
        ['instructor' => $instructor, 'course' => $course] = $this->createInstructorAndCourse();
        ['student' => $student] = $this->createStudent();

        $course->students()->attach($student, ['status' => 'enrolled']);

        $quiz1 = $course->quizzes()->save(Quiz::factory()->make(['max_score' => 100, 'display_order' => 1]));
        $quiz2 = $course->quizzes()->save(Quiz::factory()->make(['max_score' => 100, 'display_order' => 2]));

        $response1 = $quiz1->responses()->save(
            QuizResponse::factory()->make(['user_id' => $student->id, 'score' => null]),
        );
        $response2 = $quiz2->responses()->save(
            QuizResponse::factory()->make(['user_id' => $student->id, 'score' => null]),
        );

        Livewire::actingAs($instructor)
            ->test('pages::courses.home', ['course' => $course])
            ->set('quizResponseScores', [$response1->id => 80])
            ->call('gradeQuizResponse', $response1->id);

        Livewire::actingAs($instructor)
            ->test('pages::courses.home', ['course' => $course])
            ->set('quizResponseScores', [$response2->id => 90])
            ->call('gradeQuizResponse', $response2->id);

        $grade = Grade::where(['user_id' => $student->id, 'course_id' => $course->id])->first();
        // Aggregate: (80 + 90) / 2 = 85
        $this->assertEquals(85, $grade->quiz_score);
    }

    public function test_assessment_log_created_on_quiz_grade(): void
    {
        ['instructor' => $instructor, 'course' => $course] = $this->createInstructorAndCourse();
        ['student' => $student] = $this->createStudent();

        $course->students()->attach($student, ['status' => 'enrolled']);

        $quiz = $course->quizzes()->save(Quiz::factory()->make(
            ['max_score' => 100, 'title' => 'Midterm Quiz'],
        ));
        $response = $quiz->responses()->save(
            QuizResponse::factory()->make(['user_id' => $student->id, 'score' => null]),
        );

        Livewire::actingAs($instructor)
            ->test('pages::courses.home', ['course' => $course])
            ->set('quizResponseScores', [$response->id => 75])
            ->call('gradeQuizResponse', $response->id);

        $log = AssessmentLog::where([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'assessment_type' => 'quiz',
            'assessment_name' => 'Quiz: Midterm Quiz',
        ])->first();

        $this->assertNotNull($log);
        $this->assertEquals(75, $log->score);
        $this->assertEquals(100, $log->max_score);
        $this->assertEquals($instructor->id, $log->assessed_by);
    }

    public function test_instructor_missing_quizzes_manage_permission_cannot_grade_response(): void
    {
        ['instructor' => $instructor, 'course' => $course] = $this->createInstructorAndCourse();
        ['student' => $student] = $this->createStudent();

        $course->students()->attach($student, ['status' => 'enrolled']);

        $quiz = $course->quizzes()->save(Quiz::factory()->make(['max_score' => 100]));
        $response = $quiz->responses()->save(
            QuizResponse::factory()->make(['user_id' => $student->id, 'score' => null]),
        );

        ['student' => $other_student] = $this->createStudent();
        $course->students()->attach($other_student, ['status' => 'enrolled']);

        // Try to grade as a student (not instructor)
        try {
            Livewire::actingAs($other_student)
                ->test('pages::courses.home', ['course' => $course])
                ->set('quizResponseScores', [$response->id => 80])
                ->call('gradeQuizResponse', $response->id);
        } catch (AuthorizationException) {
            // Expected exception
        }

        $response->refresh();
        $this->assertNull($response->score);
    }

    public function test_non_enrolled_student_quiz_not_graded(): void
    {
        ['instructor' => $instructor, 'course' => $course] = $this->createInstructorAndCourse();
        ['student' => $student] = $this->createStudent();

        // Don't enroll the student

        $quiz = $course->quizzes()->save(Quiz::factory()->make(['max_score' => 100]));
        $response = $quiz->responses()->save(
            QuizResponse::factory()->make(['user_id' => $student->id, 'score' => null]),
        );

        try {
            Livewire::actingAs($instructor)
                ->test('pages::courses.home', ['course' => $course])
                ->set('quizResponseScores', [$response->id => 80])
                ->call('gradeQuizResponse', $response->id);
        } catch (HttpResponseException) {
            // Expected exception from abort(403)
        }

        $response->refresh();
        $this->assertNull($response->score);
    }

    public function test_approval_flags_reset_when_quiz_score_changes(): void
    {
        ['instructor' => $instructor, 'course' => $course] = $this->createInstructorAndCourse();
        ['student' => $student] = $this->createStudent();

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $course->students()->attach($student, ['status' => 'enrolled']);

        $quiz = $course->quizzes()->save(Quiz::factory()->make(['max_score' => 100]));
        $response = $quiz->responses()->save(
            QuizResponse::factory()->make(['user_id' => $student->id, 'score' => null]),
        );

        // Grade quiz once
        Livewire::actingAs($instructor)
            ->test('pages::courses.home', ['course' => $course])
            ->set('quizResponseScores', [$response->id => 80])
            ->call('gradeQuizResponse', $response->id);

        $grade = Grade::where(['user_id' => $student->id, 'course_id' => $course->id])->first();
        $grade->is_approved_by_admin = true;
        $grade->approved_by = $admin->id;
        $grade->approved_at = now();
        $grade->save();

        $this->assertTrue($grade->is_approved_by_admin);
        $this->assertNotNull($grade->approved_by);

        // Grade quiz again
        Livewire::actingAs($instructor)
            ->test('pages::courses.home', ['course' => $course])
            ->set('quizResponseScores', [$response->id => 85])
            ->call('gradeQuizResponse', $response->id);

        $grade->refresh();
        $this->assertFalse($grade->is_approved_by_admin);
        $this->assertNull($grade->approved_by);
        $this->assertNull($grade->approved_at);
    }

    public function test_quiz_score_validation_enforced(): void
    {
        ['instructor' => $instructor, 'course' => $course] = $this->createInstructorAndCourse();
        ['student' => $student] = $this->createStudent();

        $course->students()->attach($student, ['status' => 'enrolled']);

        $quiz = $course->quizzes()->save(Quiz::factory()->make(['max_score' => 100]));
        $response = $quiz->responses()->save(
            QuizResponse::factory()->make(['user_id' => $student->id, 'score' => null]),
        );

        // Try to set score exceeding max_score
        try {
            Livewire::actingAs($instructor)
                ->test('pages::courses.home', ['course' => $course])
                ->set('quizResponseScores', [$response->id => 150])
                ->call('gradeQuizResponse', $response->id);
        } catch (ValidationException) {
            // Expected
        }

        $response->refresh();
        $this->assertNull($response->score);
    }
}
