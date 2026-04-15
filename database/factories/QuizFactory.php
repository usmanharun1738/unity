<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'max_score' => 100,
            'time_limit_minutes' => $this->faker->randomElement([30, 45, 60, 90]),
            'pass_score' => 60,
            'show_results_immediately' => $this->faker->boolean(80),
            'display_order' => $this->faker->numberBetween(0, 10),
        ];
    }
}
