<?php

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\QuizResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizResponse>
 */
class QuizResponseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $score = $this->faker->numberBetween(0, 100);

        return [
            'quiz_id' => Quiz::factory(),
            'user_id' => User::factory(),
            'response_data' => [
                'q1' => ['answer' => 'A', 'is_correct' => true],
                'q2' => ['answer' => 'B', 'is_correct' => false],
                'q3' => ['answer' => 'C', 'is_correct' => true],
            ],
            'score' => $score,
            'submitted_at' => $this->faker->dateTime(),
            'time_taken_seconds' => $this->faker->numberBetween(300, 3600),
            'is_passed' => $score >= 60,
        ];
    }
}
