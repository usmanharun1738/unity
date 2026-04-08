<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\FacultyProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FacultyProfile>
 */
class FacultyProfileFactory extends Factory
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
            'department_id' => Department::factory(),
            'employee_code' => strtoupper(fake()->unique()->bothify('EMP-####')),
            'title' => fake()->randomElement(['Lecturer', 'Assistant Professor', 'Professor']),
            'bio' => fake()->sentence(),
        ];
    }
}
