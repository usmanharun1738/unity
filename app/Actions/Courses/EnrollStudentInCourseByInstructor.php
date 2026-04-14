<?php

namespace App\Actions\Courses;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class EnrollStudentInCourseByInstructor
{
    public function handle(Course $course, User $student): Enrollment
    {
        if (! $student->studentProfile()->exists()) {
            throw ValidationException::withMessages([
                'user_id' => __('Only students can be enrolled in courses.'),
            ]);
        }

        if ($course->enrollments()->where('user_id', $student->id)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => __('This student is already enrolled in this course.'),
            ]);
        }

        return Enrollment::query()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);
    }
}
