<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EnrollmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 30 students
        $students = [];
        for ($i = 1; $i <= 30; $i++) {
            $user = User::create([
                'name' => 'Student '.$i,
                'email' => 'student'.$i.'@university.edu',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
            $user->assignRole(RoleName::Student->value);
            StudentProfile::query()->create([
                'user_id' => $user->id,
                'student_number' => 'STU-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'major' => fake()->randomElement(['Computer Science', 'Engineering', 'Business Administration', 'Mathematics', 'Physics']),
                'year_level' => fake()->numberBetween(1, 4),
                'bio' => fake()->sentence(),
                'avatar_path' => null,
            ]);
            $students[] = $user;
        }

        // Get all courses
        $courses = Course::all();

        // Enroll students in random courses
        foreach ($students as $student) {
            $courseCount = random_int(3, 6);
            $selectedCourses = $courses->random($courseCount)->pluck('id');

            foreach ($selectedCourses as $courseId) {
                Enrollment::create([
                    'user_id' => $student->id,
                    'course_id' => $courseId,
                    'enrolled_at' => now(),
                    'status' => 'enrolled',
                ]);
            }
        }
    }
}
