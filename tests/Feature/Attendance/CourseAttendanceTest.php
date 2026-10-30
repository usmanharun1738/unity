<?php

namespace Tests\Feature\Attendance;

use App\Enums\RoleName;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FacultyProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourseAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_instructor_can_record_attendance_for_course_students(): void
    {
        $instructor = User::factory()->create();
        $instructor->assignRole(RoleName::Faculty->value);

        $facultyProfile = FacultyProfile::factory()->create(['user_id' => $instructor->id]);
        $course = Course::factory()->create(['faculty_profile_id' => $facultyProfile->id]);

        $student1 = User::factory()->create();
        $student1->assignRole(RoleName::Student->value);
        Enrollment::factory()->create(['user_id' => $student1->id, 'course_id' => $course->id, 'status' => 'enrolled']);

        $student2 = User::factory()->create();
        $student2->assignRole(RoleName::Student->value);
        Enrollment::factory()->create(['user_id' => $student2->id, 'course_id' => $course->id, 'status' => 'enrolled']);

        Livewire::actingAs($instructor)
            ->test('pages::courses.home', ['course' => $course])
            ->set('attendance_date', '2026-10-30')
            ->set('studentAttendanceStatus.'.$student1->id, 'present')
            ->set('studentAttendanceStatus.'.$student2->id, 'absent')
            ->call('saveAttendance')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('attendance', [
            'course_id' => $course->id,
            'user_id' => $student1->id,
            'status' => 'present',
            'marked_by' => $instructor->id,
        ]);

        $this->assertDatabaseHas('attendance', [
            'course_id' => $course->id,
            'user_id' => $student2->id,
            'status' => 'absent',
            'marked_by' => $instructor->id,
        ]);
    }

    public function test_instructor_can_mark_all_present(): void
    {
        $instructor = User::factory()->create();
        $instructor->assignRole(RoleName::Faculty->value);

        $facultyProfile = FacultyProfile::factory()->create(['user_id' => $instructor->id]);
        $course = Course::factory()->create(['faculty_profile_id' => $facultyProfile->id]);

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'enrolled']);

        Livewire::actingAs($instructor)
            ->test('pages::courses.home', ['course' => $course])
            ->call('markAllAttendance', 'present')
            ->call('saveAttendance')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('attendance', [
            'course_id' => $course->id,
            'user_id' => $student->id,
            'status' => 'present',
        ]);
    }

    public function test_student_cannot_record_attendance(): void
    {
        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        $course = Course::factory()->create();

        Livewire::actingAs($student)
            ->test('pages::courses.home', ['course' => $course])
            ->call('saveAttendance')
            ->assertForbidden();
    }
}
