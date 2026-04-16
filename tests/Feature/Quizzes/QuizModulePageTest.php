<?php

namespace Tests\Feature\Quizzes;

use App\Enums\RoleName;
use App\Models\Course;
use App\Models\FacultyProfile;
use App\Models\Grade;
use App\Models\Quiz;
use App\Models\QuizQuestion;
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
            ->assertSee('Quiz Module')
            ->assertSee('Instructor Quiz List')
            ->assertSee('Response Grading Panel');
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

    public function test_faculty_can_add_objective_question_to_quiz(): void
    {
        ['instructor' => $instructor, 'course' => $course] = $this->createInstructorAndCourse();

        $quiz = $course->quizzes()->save(Quiz::factory()->make([
            'title' => 'Objective Quiz',
            'max_score' => 20,
        ]));

        Livewire::actingAs($instructor)
            ->test('pages::quizzes.index')
            ->set('course_id', $course->id)
            ->set('selected_quiz_id', $quiz->id)
            ->set('question_prompt', 'Which one is a PHP framework?')
            ->set('question_points', '5')
            ->set('question_options', ['Laravel', 'TensorFlow', 'NumPy', 'Pandas'])
            ->set('question_correct_options', [0])
            ->call('addObjectiveQuestion')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('quiz_questions', [
            'quiz_id' => $quiz->id,
            'question_type' => 'objective',
            'prompt' => 'Which one is a PHP framework?',
        ]);
    }

    public function test_faculty_can_add_theory_question_to_quiz(): void
    {
        ['instructor' => $instructor, 'course' => $course] = $this->createInstructorAndCourse();

        $quiz = $course->quizzes()->save(Quiz::factory()->make([
            'title' => 'Theory Quiz',
            'max_score' => 20,
        ]));

        Livewire::actingAs($instructor)
            ->test('pages::quizzes.index')
            ->set('course_id', $course->id)
            ->set('selected_quiz_id', $quiz->id)
            ->set('theory_question_prompt', 'Explain the MVC architecture.')
            ->set('theory_question_points', '10')
            ->set('theory_question_rubric', 'Mention model, view, and controller roles.')
            ->call('addTheoryQuestion')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('quiz_questions', [
            'quiz_id' => $quiz->id,
            'question_type' => 'theory',
            'prompt' => 'Explain the MVC architecture.',
        ]);
    }

    public function test_faculty_can_create_quiz_for_owned_course_without_quizzes_manage_permission(): void
    {
        ['instructor' => $instructor, 'course' => $course] = $this->createInstructorAndCourse();

        if ($instructor->hasPermissionTo('quizzes.manage')) {
            $instructor->revokePermissionTo('quizzes.manage');
            $instructor = User::query()->findOrFail($instructor->id);
        }

        Livewire::actingAs($instructor)
            ->test('pages::quizzes.index')
            ->set('course_id', $course->id)
            ->set('quiz_title', 'Owned Course Quiz')
            ->set('quiz_max_score', '60')
            ->call('createQuiz')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('quizzes', [
            'course_id' => $course->id,
            'title' => 'Owned Course Quiz',
            'max_score' => 60,
        ]);
    }

    public function test_student_can_view_quiz_module_page_but_cannot_create_quiz(): void
    {
        ['student' => $student] = $this->createStudent();
        ['course' => $course] = $this->createInstructorAndCourse();

        $course->students()->attach($student, ['status' => 'enrolled']);

        $this->actingAs($student)
            ->get(route('quizzes.index'))
            ->assertOk()
            ->assertSee('Student Quiz View');

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

    public function test_student_can_submit_objective_quiz_and_get_auto_graded(): void
    {
        ['instructor' => $instructor, 'course' => $course] = $this->createInstructorAndCourse();
        ['student' => $student] = $this->createStudent();

        $course->students()->attach($student, ['status' => 'enrolled']);

        $quiz = $course->quizzes()->save(Quiz::factory()->make([
            'title' => 'Auto Grade Quiz',
            'max_score' => 10,
            'pass_score' => 6,
        ]));

        $questionOne = QuizQuestion::factory()->create([
            'quiz_id' => $quiz->id,
            'prompt' => 'Select Laravel',
            'points' => 2,
            'options' => ['Laravel', 'React'],
            'correct_options' => [0],
            'display_order' => 1,
        ]);

        $questionTwo = QuizQuestion::factory()->create([
            'quiz_id' => $quiz->id,
            'prompt' => 'Select PHP creator',
            'points' => 3,
            'options' => ['Guido', 'Rasmus'],
            'correct_options' => [1],
            'display_order' => 2,
        ]);

        Livewire::actingAs($student)
            ->test('pages::quizzes.index')
            ->set('course_id', $course->id)
            ->set("attemptAnswers.{$quiz->id}.{$questionOne->id}", 0)
            ->set("attemptAnswers.{$quiz->id}.{$questionTwo->id}", 1)
            ->call('submitObjectiveAttempt', $quiz->id)
            ->assertHasNoErrors();

        $response = QuizResponse::query()->where([
            'quiz_id' => $quiz->id,
            'user_id' => $student->id,
        ])->first();

        $this->assertNotNull($response);
        $this->assertEquals(10, (float) $response->score);
        $this->assertTrue((bool) $response->is_passed);

        $grade = Grade::query()->where([
            'user_id' => $student->id,
            'course_id' => $course->id,
        ])->first();

        $this->assertNotNull($grade);
        $this->assertEquals(100, (float) $grade->quiz_score);
    }

    public function test_student_mixed_quiz_submission_is_pending_manual_until_theory_graded(): void
    {
        ['instructor' => $instructor, 'course' => $course] = $this->createInstructorAndCourse();
        ['student' => $student] = $this->createStudent();

        $course->students()->attach($student, ['status' => 'enrolled']);

        $quiz = $course->quizzes()->save(Quiz::factory()->make([
            'title' => 'Mixed Quiz',
            'max_score' => 10,
            'pass_score' => 6,
        ]));

        $objectiveQuestion = QuizQuestion::factory()->create([
            'quiz_id' => $quiz->id,
            'question_type' => 'objective',
            'prompt' => 'Select Laravel',
            'points' => 2,
            'options' => ['Laravel', 'React'],
            'correct_options' => [0],
            'display_order' => 1,
        ]);

        $theoryQuestion = QuizQuestion::factory()->create([
            'quiz_id' => $quiz->id,
            'question_type' => 'theory',
            'prompt' => 'Explain service container.',
            'rubric_text' => 'Mention dependency resolution.',
            'points' => 3,
            'options' => [],
            'correct_options' => [],
            'display_order' => 2,
        ]);

        Livewire::actingAs($student)
            ->test('pages::quizzes.index')
            ->set('course_id', $course->id)
            ->set("attemptAnswers.{$quiz->id}.{$objectiveQuestion->id}", 0)
            ->set("attemptAnswers.{$quiz->id}.{$theoryQuestion->id}", 'It resolves class dependencies automatically.')
            ->call('submitQuizAttempt', $quiz->id)
            ->assertHasNoErrors();

        $response = QuizResponse::query()->where([
            'quiz_id' => $quiz->id,
            'user_id' => $student->id,
        ])->first();

        $this->assertNotNull($response);
        $this->assertEquals('pending_manual', $response->response_data['grading_status'] ?? null);
        $this->assertNull($response->is_passed);

        $grade = Grade::query()->where([
            'user_id' => $student->id,
            'course_id' => $course->id,
        ])->first();

        $this->assertNull($grade);
    }

    public function test_instructor_can_manually_grade_theory_response_and_finalize_quiz_score(): void
    {
        ['instructor' => $instructor, 'course' => $course] = $this->createInstructorAndCourse();
        ['student' => $student] = $this->createStudent();

        $course->students()->attach($student, ['status' => 'enrolled']);

        $quiz = $course->quizzes()->save(Quiz::factory()->make([
            'title' => 'Manual Theory Grading Quiz',
            'max_score' => 10,
            'pass_score' => 6,
        ]));

        $objectiveQuestion = QuizQuestion::factory()->create([
            'quiz_id' => $quiz->id,
            'question_type' => 'objective',
            'prompt' => 'Select Laravel',
            'points' => 2,
            'options' => ['Laravel', 'React'],
            'correct_options' => [0],
            'display_order' => 1,
        ]);

        $theoryQuestion = QuizQuestion::factory()->create([
            'quiz_id' => $quiz->id,
            'question_type' => 'theory',
            'prompt' => 'Explain service container.',
            'rubric_text' => 'Mention dependency resolution.',
            'points' => 3,
            'options' => [],
            'correct_options' => [],
            'display_order' => 2,
        ]);

        Livewire::actingAs($student)
            ->test('pages::quizzes.index')
            ->set('course_id', $course->id)
            ->set("attemptAnswers.{$quiz->id}.{$objectiveQuestion->id}", 0)
            ->set("attemptAnswers.{$quiz->id}.{$theoryQuestion->id}", 'It resolves dependencies for classes and interfaces.')
            ->call('submitQuizAttempt', $quiz->id)
            ->assertHasNoErrors();

        $response = QuizResponse::query()->where([
            'quiz_id' => $quiz->id,
            'user_id' => $student->id,
        ])->firstOrFail();

        Livewire::actingAs($instructor)
            ->test('pages::quizzes.index')
            ->set('theoryQuestionScores', [$response->id => [$theoryQuestion->id => 3]])
            ->set('theoryQuestionFeedbacks', [$response->id => [$theoryQuestion->id => 'Good explanation']])
            ->call('gradeTheoryResponse', $response->id, $theoryQuestion->id)
            ->assertHasNoErrors();

        $response->refresh();
        $this->assertEquals('graded', $response->response_data['grading_status'] ?? null);
        $this->assertEquals(10, (float) $response->score);
        $this->assertTrue((bool) $response->is_passed);

        $grade = Grade::query()->where([
            'user_id' => $student->id,
            'course_id' => $course->id,
        ])->first();

        $this->assertNotNull($grade);
        $this->assertEquals(100, (float) $grade->quiz_score);
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

    public function test_quiz_page_honors_course_query_parameter_for_instructor(): void
    {
        ['instructor' => $instructor, 'course' => $firstCourse] = $this->createInstructorAndCourse();

        $secondCourse = Course::factory()->create([
            'faculty_profile_id' => $firstCourse->faculty_profile_id,
            'is_active' => true,
        ]);

        Livewire::actingAs($instructor)
            ->withQueryParams(['course' => $secondCourse->id])
            ->test('pages::quizzes.index')
            ->assertSet('course_id', $secondCourse->id);
    }

    public function test_faculty_cannot_create_quiz_for_course_not_owned(): void
    {
        ['instructor' => $instructor] = $this->createInstructorAndCourse();
        ['course' => $otherCourse] = $this->createInstructorAndCourse();

        Livewire::actingAs($instructor)
            ->test('pages::quizzes.index')
            ->set('course_id', $otherCourse->id)
            ->set('quiz_title', 'Unauthorized Quiz')
            ->set('quiz_max_score', '40')
            ->call('createQuiz')
            ->assertStatus(403);

        $this->assertDatabaseMissing('quizzes', [
            'course_id' => $otherCourse->id,
            'title' => 'Unauthorized Quiz',
        ]);
    }
}
