<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentSubmission>
 */
class AssignmentSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'user_id' => User::factory(),
            'file_path' => 'submissions/'.fake()->sha256().'.pdf',
            'submission_date' => $this->faker->dateTimeBetween('-10 days'),
            'score' => $this->faker->optional(0.8)->numberBetween(0, 100),
            'feedback' => $this->faker->optional(0.6)->paragraph(),
            'graded_by' => User::factory(),
            'graded_at' => $this->faker->optional(0.8)->dateTime(),
            'is_late' => $this->faker->boolean(20),
        ];
    }
}
