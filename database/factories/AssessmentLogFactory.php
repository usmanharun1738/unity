<?php

namespace Database\Factories;

use App\Models\AssessmentLog;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentLog>
 */
class AssessmentLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'assessment_type' => $this->faker->randomElement(['ca', 'test', 'assignment', 'quiz', 'project', 'exam']),
            'assessment_name' => $this->faker->sentence(2),
            'score' => $this->faker->numberBetween(0, 100),
            'max_score' => 100,
            'assessed_by' => User::factory(),
            'assessed_at' => $this->faker->dateTime(),
            'notes' => $this->faker->optional()->sentence(),
            'created_at' => $this->faker->dateTime(),
        ];
    }
}
