<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Department;
use App\Models\FacultyProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'faculty_profile_id' => FacultyProfile::factory(),
            'code' => strtoupper(fake()->unique()->bothify('???###')),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'credits' => fake()->numberBetween(1, 6),
            'semester' => fake()->randomElement(['Fall', 'Spring', 'Summer']),
            'capacity' => fake()->numberBetween(20, 120),
            'is_active' => true,
        ];
    }
}
