<?php

namespace Tests\Feature\Enrollments;

use App\Enums\RoleName;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnrollmentPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_authenticated_user_can_access_enrollments_page(): void
    {
        Role::findOrCreate(RoleName::Student->value, 'web');

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create(['user_id' => $student->id]);

        $this->actingAs($student)
            ->get(route('enrollments.index'))
            ->assertOk();
    }

    public function test_user_can_enroll_with_valid_class_code(): void
    {
        Role::findOrCreate(RoleName::Student->value, 'web');

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create(['user_id' => $student->id]);

        $course = Course::factory()->create(['code' => 'JOIN101', 'enrollment_key' => 'JOIN101-UNITY', 'is_active' => true]);

        Livewire::actingAs($student)
            ->test('pages::enrollments.index')
            ->set('course_id', $course->id)
            ->set('enrollment_key', 'JOIN101-UNITY')
            ->call('enroll')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_user_cannot_enroll_with_invalid_class_code(): void
    {
        Role::findOrCreate(RoleName::Student->value, 'web');

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create(['user_id' => $student->id]);

        $course = Course::factory()->create(['code' => 'JOIN101', 'enrollment_key' => 'JOIN101-UNITY', 'is_active' => true]);

        Livewire::actingAs($student)
            ->test('pages::enrollments.index')
            ->set('course_id', $course->id)
            ->set('enrollment_key', 'WRONG101')
            ->call('enroll')
            ->assertHasErrors(['code']);

        $this->assertDatabaseMissing('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_user_cannot_duplicate_enrollment_for_same_class(): void
    {
        Role::findOrCreate(RoleName::Student->value, 'web');

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create(['user_id' => $student->id]);

        $course = Course::factory()->create(['code' => 'JOIN101', 'enrollment_key' => 'JOIN101-UNITY', 'is_active' => true]);

        Enrollment::query()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        Livewire::actingAs($student)
            ->test('pages::enrollments.index')
            ->set('course_id', $course->id)
            ->set('enrollment_key', 'JOIN101-UNITY')
            ->call('enroll')
            ->assertHasErrors(['course_id']);
    }

    public function test_faculty_cannot_self_enroll_with_key(): void
    {
        Role::findOrCreate(RoleName::Faculty->value, 'web');

        $faculty = User::factory()->create();
        $faculty->assignRole(RoleName::Faculty->value);

        $course = Course::factory()->create(['code' => 'JOIN101', 'enrollment_key' => 'JOIN101-UNITY', 'is_active' => true]);

        Livewire::actingAs($faculty)
            ->test('pages::enrollments.index')
            ->set('course_id', $course->id)
            ->set('enrollment_key', 'JOIN101-UNITY')
            ->call('enroll')
            ->assertForbidden();

        $this->assertDatabaseMissing('enrollments', [
            'user_id' => $faculty->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_student_missing_enrollments_create_permission_cannot_enroll(): void
    {
        $studentRole = Role::findByName(RoleName::Student->value, 'web');
        $studentRole->revokePermissionTo('enrollments.create');

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create(['user_id' => $student->id]);

        $course = Course::factory()->create(['code' => 'JOIN101', 'enrollment_key' => 'JOIN101-UNITY', 'is_active' => true]);

        Livewire::actingAs($student)
            ->test('pages::enrollments.index')
            ->set('course_id', $course->id)
            ->set('enrollment_key', 'JOIN101-UNITY')
            ->call('enroll')
            ->assertForbidden();

        $this->assertDatabaseMissing('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);
    }
}
