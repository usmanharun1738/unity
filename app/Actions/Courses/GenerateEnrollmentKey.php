<?php

namespace App\Actions\Courses;

use App\Models\Course;
use Illuminate\Support\Str;

class GenerateEnrollmentKey
{
    public function handle(Course $course): string
    {
        $key = Str::random(16);
        $course->update(['enrollment_key' => $key]);

        return $key;
    }
}
