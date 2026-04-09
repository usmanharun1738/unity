<?php

namespace Database\Factories;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentProfile>
 */
class StudentProfileFactory extends Factory
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
            'student_number' => 'STU-'.strtoupper(fake()->unique()->bothify('#######')),
            'major' => fake()->randomElement(['Computer Science', 'Engineering', 'Business Administration', 'Mathematics', 'Physics']),
            'year_level' => fake()->numberBetween(1, 4),
            'bio' => fake()->sentence(),
            'avatar_path' => null,
        ];
    }
}
