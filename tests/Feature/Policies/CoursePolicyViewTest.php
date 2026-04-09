<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleName;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CoursePolicyViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_archived_course(): void
    {
        Role::findOrCreate(RoleName::Admin->value, 'web');

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $course = Course::factory()->create(['is_active' => false]);

        $this->assertTrue($admin->can('view', $course));
    }

    public function test_student_can_view_active_course(): void
    {
        Role::findOrCreate(RoleName::Student->value, 'web');

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);

        $course = Course::factory()->create(['is_active' => true]);

        $this->assertTrue($student->can('view', $course));
    }

    public function test_student_cannot_view_archived_course_when_not_enrolled(): void
    {
        Role::findOrCreate(RoleName::Student->value, 'web');

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);

        $course = Course::factory()->create(['is_active' => false]);

        $this->assertFalse($student->can('view', $course));
    }

    public function test_student_can_view_archived_course_when_enrolled(): void
    {
        Role::findOrCreate(RoleName::Student->value, 'web');

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);

        $course = Course::factory()->create(['is_active' => false]);

        Enrollment::query()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $this->assertTrue($student->can('view', $course));
    }
}
