<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
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
            'attendance_date' => $this->faker->dateTimeBetween('-6 months'),
            'status' => $this->faker->randomElement(['present', 'absent', 'late', 'excused']),
            'marked_by' => User::factory(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
