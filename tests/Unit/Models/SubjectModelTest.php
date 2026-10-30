<?php

namespace Tests\Unit\Models;

use App\Models\Course;
use App\Models\Department;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_belongs_to_department(): void
    {
        $department = Department::factory()->create();
        $subject = Subject::factory()->create(['department_id' => $department->id]);

        $this->assertInstanceOf(Department::class, $subject->department);
        $this->assertSame($department->id, $subject->department->id);
    }

    public function test_subject_has_many_courses(): void
    {
        $subject = Subject::factory()->create();
        $course1 = Course::factory()->create(['subject_id' => $subject->id]);
        $course2 = Course::factory()->create(['subject_id' => $subject->id]);

        $this->assertCount(2, $subject->courses);
        $this->assertTrue($subject->courses->contains($course1));
        $this->assertTrue($subject->courses->contains($course2));
    }

    public function test_course_belongs_to_subject(): void
    {
        $subject = Subject::factory()->create();
        $course = Course::factory()->create(['subject_id' => $subject->id]);

        $this->assertInstanceOf(Subject::class, $course->subject);
        $this->assertSame($subject->id, $course->subject->id);
    }

    public function test_department_has_many_subjects(): void
    {
        $department = Department::factory()->create();
        $subject = Subject::factory()->create(['department_id' => $department->id]);

        $this->assertTrue($department->subjects->contains($subject));
    }

    public function test_student_profile_belongs_to_department(): void
    {
        $department = Department::factory()->create();
        $user = User::factory()->create();
        $profile = StudentProfile::factory()->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
        ]);

        $this->assertInstanceOf(Department::class, $profile->department);
        $this->assertSame($department->id, $profile->department->id);
    }
}
