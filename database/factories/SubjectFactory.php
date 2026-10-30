<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
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
            'code' => strtoupper(fake()->unique()->bothify('??###')),
            'title' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'credits' => fake()->randomElement([2, 3, 4]),
        ];
    }
}

