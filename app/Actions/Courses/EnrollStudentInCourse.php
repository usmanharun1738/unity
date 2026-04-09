<?php

namespace App\Actions\Courses;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class EnrollStudentInCourse
{
    public function handle(User $user, Course $course, string $enrollmentKey): Enrollment
    {
        if (! $course->is_active) {
            throw ValidationException::withMessages([
                'course_id' => __('This class is archived and is not available for enrollment.'),
            ]);
        }

        if ($course->enrollments()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'course_id' => __('You are already enrolled in this class.'),
            ]);
        }

        if (strcasecmp(trim($course->enrollment_key ?? ''), trim($enrollmentKey)) !== 0) {
            throw ValidationException::withMessages([
                'code' => __('The enrollment key does not match this class.'),
            ]);
        }

        return Enrollment::query()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);
    }
}
