<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $caScore = $this->faker->numberBetween(0, 100);
        $testScore = $this->faker->numberBetween(0, 100);
        $assignmentScore = $this->faker->numberBetween(0, 100);
        $quizScore = $this->faker->numberBetween(0, 100);
        $projectScore = $this->faker->numberBetween(0, 100);
        $examScore = $this->faker->numberBetween(0, 100);

        $finalGrade = (($caScore * 0.30) + ($testScore * 0.20) + ($assignmentScore * 0.10) + ($projectScore * 0.10) + ($examScore * 0.30));

        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'ca_score' => $caScore,
            'test_score' => $testScore,
            'assignment_score' => $assignmentScore,
            'quiz_score' => $quizScore,
            'project_score' => $projectScore,
            'exam_score' => $examScore,
            'final_grade' => round($finalGrade, 2),
            'grade_letter' => match (true) {
                $finalGrade >= 80 => 'A',
                $finalGrade >= 70 => 'B',
                $finalGrade >= 60 => 'C',
                $finalGrade >= 50 => 'D',
                default => 'F'
            },
            'is_approved_by_admin' => false,
        ];
    }
}
