<?php

namespace Tests\Unit\Services;

use App\Models\Course;
use App\Models\Grade;
use App\Models\User;
use App\Services\Grading\GradeCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeCalculatorServiceTest extends TestCase
{
    use RefreshDatabase;

    private GradeCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new GradeCalculatorService;
    }

    public function test_it_calculates_perfect_scores_correctly(): void
    {
        $result = $this->calculator->calculate([
            'ca' => 100,
            'test' => 100,
            'quiz' => 100,
            'assignment' => 100,
            'project' => 100,
            'exam' => 100,
        ]);

        $this->assertSame(100.0, $result['final_grade']);
        $this->assertSame('A', $result['grade_letter']);
    }

    public function test_it_calculates_weighted_scores_according_to_institutional_formula(): void
    {
        // CA: 80 * 0.25 = 20.0
        // Test: 70 * 0.15 = 10.5
        // Quiz: 90 * 0.10 = 9.0
        // Assignment: 85 * 0.10 = 8.5
        // Project: 95 * 0.10 = 9.5
        // Exam: 60 * 0.30 = 18.0
        // Total = 75.5 -> B
        $result = $this->calculator->calculate([
            'ca' => 80,
            'test' => 70,
            'quiz' => 90,
            'assignment' => 85,
            'project' => 95,
            'exam' => 60,
        ]);

        $this->assertSame(75.5, $result['final_grade']);
        $this->assertSame('B', $result['grade_letter']);
    }

    public function test_it_handles_null_or_missing_scores_as_zero(): void
    {
        $result = $this->calculator->calculate([
            'ca' => 100, // 25
            'exam' => 100, // 30
        ]);

        $this->assertSame(55.0, $result['final_grade']);
        $this->assertSame('D', $result['grade_letter']);
    }

    public function test_it_maps_letter_grades_strictly(): void
    {
        $this->assertSame('A', $this->calculator->determineLetter(80.0));
        $this->assertSame('A', $this->calculator->determineLetter(100.0));
        $this->assertSame('B', $this->calculator->determineLetter(79.99));
        $this->assertSame('B', $this->calculator->determineLetter(70.0));
        $this->assertSame('C', $this->calculator->determineLetter(69.99));
        $this->assertSame('C', $this->calculator->determineLetter(60.0));
        $this->assertSame('D', $this->calculator->determineLetter(59.99));
        $this->assertSame('D', $this->calculator->determineLetter(50.0));
        $this->assertSame('F', $this->calculator->determineLetter(49.99));
        $this->assertSame('F', $this->calculator->determineLetter(0.0));
    }

    public function test_it_recalculates_grade_model_and_invalidates_approval(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();

        $grade = Grade::query()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'ca_score' => 80,
            'test_score' => 80,
            'quiz_score' => 80,
            'assignment_score' => 80,
            'project_score' => 80,
            'exam_score' => 80,
            'is_approved_by_admin' => true,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $updated = $this->calculator->recalculate($grade);

        $this->assertSame(80.0, (float) $updated->final_grade);
        $this->assertSame('A', $updated->grade_letter);
        $this->assertFalse($updated->is_approved_by_admin);
        $this->assertNull($updated->approved_by);
        $this->assertNull($updated->approved_at);
    }
}
