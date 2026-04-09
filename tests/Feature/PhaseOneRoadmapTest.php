<?php

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Enums\RoleName;
use App\Models\Course;
use App\Models\Department;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhaseOneRoadmapTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_registration_creates_a_student_profile(): void
    {
        $user = app(CreateNewUser::class)->create([
            'name' => 'Student One',
            'email' => 'student1@university.edu',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertInstanceOf(StudentProfile::class, $user->studentProfile);
        $this->assertNotEmpty($user->studentProfile?->student_number);
        $this->assertSame(RoleName::Student->value, $user->roles->first()?->name);
    }

    public function test_student_can_enroll_using_an_enrollment_key(): void
    {
        Role::findOrCreate(RoleName::Student->value, 'web');

        $department = Department::factory()->create();
        $course = Course::factory()->create([
            'department_id' => $department->id,
            'enrollment_key' => 'CS101-UNITY',
            'is_active' => true,
        ]);

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);

        $this->actingAs($student);

        Livewire::test('pages::enrollments.index')
            ->set('course_id', $course->id)
            ->set('enrollment_key', 'CS101-UNITY')
            ->call('enroll')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_student_can_view_the_course_homepage(): void
    {
        Role::findOrCreate(RoleName::Student->value, 'web');

        $department = Department::factory()->create();
        $course = Course::factory()->create([
            'department_id' => $department->id,
            'enrollment_key' => 'MATH101-UNITY',
            'is_active' => true,
        ]);

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);

        $this->actingAs($student);

        $this->get(route('courses.home', $course))
            ->assertOk()
            ->assertSee($course->title)
            ->assertSee('Join this class');
    }

    public function test_admin_can_archive_and_restore_a_class(): void
    {
        Role::findOrCreate(RoleName::Admin->value, 'web');

        $department = Department::factory()->create();
        $course = Course::factory()->create([
            'department_id' => $department->id,
            'enrollment_key' => 'PHY101-UNITY',
            'is_active' => true,
        ]);

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $this->actingAs($admin);

        Livewire::test('pages::courses.show', ['course' => $course])
            ->call('toggleArchiveStatus')
            ->assertSet('course.is_active', false)
            ->call('toggleArchiveStatus')
            ->assertSet('course.is_active', true);
    }
}
