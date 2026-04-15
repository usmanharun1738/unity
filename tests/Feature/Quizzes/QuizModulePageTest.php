<?php

namespace Tests\Feature\Quizzes;

use App\Enums\RoleName;
use App\Models\Course;
use App\Models\FacultyProfile;
use App\Models\Grade;
use App\Models\Quiz;
use App\Models\QuizResponse;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuizModulePageTest extends TestCase
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

    public function test_faculty_can_view_quiz_module_page(): void
    {
        ['instructor' => $instructor] = $this->createInstructorAndCourse();

        $this->actingAs($instructor)
            ->get(route('quizzes.index'))
            ->assertOk()
            ->assertSee('Quiz Module');
    }

    public function test_faculty_can_create_quiz_from_quiz_module_page(): void
    {
        ['instructor' => $instructor, 'course' => $course] = $this->createInstructorAndCourse();

        Livewire::actingAs($instructor)
            ->test('pages::quizzes.index')
            ->set('course_id', $course->id)
            ->set('quiz_title', 'Week 1 Quiz')
            ->set('quiz_description', 'Introductory concepts')
            ->set('quiz_max_score', '50')
            ->set('quiz_pass_score', '25')
            ->set('quiz_time_limit_minutes', '20')
            ->set('quiz_show_results_immediately', true)
            ->call('createQuiz')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('quizzes', [
            'course_id' => $course->id,
            'title' => 'Week 1 Quiz',
            'max_score' => 50,
        ]);
    }

    public function test_student_can_view_quiz_module_page_but_cannot_create_quiz(): void
    {
        ['student' => $student] = $this->createStudent();
        ['course' => $course] = $this->createInstructorAndCourse();

        $course->students()->attach($student, ['status' => 'enrolled']);

        $this->actingAs($student)
            ->get(route('quizzes.index'))
            ->assertOk();

        Livewire::actingAs($student)
            ->test('pages::quizzes.index')
            ->set('course_id', $course->id)
            ->set('quiz_title', 'Student Attempt')
            ->set('quiz_max_score', '40')
            ->call('createQuiz')
            ->assertStatus(403);

        $this->assertDatabaseMissing('quizzes', [
            'course_id' => $course->id,
            'title' => 'Student Attempt',
        ]);
    }

    public function test_grading_from_quiz_module_syncs_quiz_score(): void
    {
        ['instructor' => $instructor, 'course' => $course] = $this->createInstructorAndCourse();
        ['student' => $student] = $this->createStudent();

        $course->students()->attach($student, ['status' => 'enrolled']);

        $quiz = $course->quizzes()->save(Quiz::factory()->make([
            'title' => 'Quiz A',
            'max_score' => 50,
        ]));

        $response = $quiz->responses()->save(QuizResponse::factory()->make([
            'user_id' => $student->id,
            'score' => null,
        ]));

        Livewire::actingAs($instructor)
            ->test('pages::quizzes.index')
            ->set('quizResponseScores', [$response->id => 40])
            ->call('gradeQuizResponse', $response->id)
            ->assertHasNoErrors();

        $response->refresh();
        $this->assertEquals(40, $response->score);

        $grade = Grade::query()->where([
            'user_id' => $student->id,
            'course_id' => $course->id,
        ])->first();

        $this->assertNotNull($grade);
        $this->assertEquals(80, $grade->quiz_score);
    }

    public function test_sidebar_shows_quizzes_navigation_link(): void
    {
        ['instructor' => $instructor] = $this->createInstructorAndCourse();

        $this->actingAs($instructor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Quizzes')
            ->assertSee(route('quizzes.index'));
    }
}
