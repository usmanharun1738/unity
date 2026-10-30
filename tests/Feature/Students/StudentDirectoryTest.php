<?php

namespace Tests\Feature\Students;

use App\Enums\RoleName;
use App\Livewire\Pages\Students\Directory;
use App\Livewire\Pages\Students\MyClasses;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\FacultyProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function setupAdmin(): User
    {
        Role::findOrCreate(RoleName::Admin->value, 'web');
        Role::findOrCreate(RoleName::Student->value, 'web');
        Permission::findOrCreate('students.view-any', 'web');

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $admin->givePermissionTo('students.view-any');

        return $admin;
    }

    public function test_directory_renders_and_lists_students(): void
    {
        $admin = $this->setupAdmin();

        $student = User::factory()->create(['name' => 'John Doe']);
        $student->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create([
            'user_id' => $student->id,
            'student_number' => 'STU-1001',
            'major' => 'Computer Science',
            'year_level' => 3,
        ]);

        $this->actingAs($admin);

        Livewire::test(Directory::class)
            ->assertOk()
            ->assertSee('Student Directory')
            ->assertSee('John Doe')
            ->assertSee('STU-1001')
            ->assertSee('Computer Science');
    }

    public function test_directory_search_filter_works(): void
    {
        $admin = $this->setupAdmin();

        $student1 = User::factory()->create(['name' => 'Alice Wonderland']);
        $student1->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create(['user_id' => $student1->id, 'student_number' => 'STU-9001']);

        $student2 = User::factory()->create(['name' => 'Bob Builder']);
        $student2->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create(['user_id' => $student2->id, 'student_number' => 'STU-9002']);

        $this->actingAs($admin);

        Livewire::test(Directory::class)
            ->set('search', 'Alice')
            ->assertSee('Alice Wonderland')
            ->assertDontSee('Bob Builder')
            ->set('search', '9002')
            ->assertSee('Bob Builder')
            ->assertDontSee('Alice Wonderland');
    }

    public function test_directory_department_filter_and_reset_works(): void
    {
        $admin = $this->setupAdmin();

        $deptA = Department::factory()->create(['name' => 'Engineering']);
        $deptB = Department::factory()->create(['name' => 'Humanities']);

        $studentA = User::factory()->create(['name' => 'Eng Student']);
        $studentA->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create([
            'user_id' => $studentA->id,
            'department_id' => $deptA->id,
            'major' => 'Engineering',
        ]);

        $studentB = User::factory()->create(['name' => 'Arts Student']);
        $studentB->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create([
            'user_id' => $studentB->id,
            'department_id' => $deptB->id,
            'major' => 'Humanities',
        ]);

        $this->actingAs($admin);

        Livewire::test(Directory::class)
            ->set('department_id', $deptA->id)
            ->assertSee('Eng Student')
            ->assertDontSee('Arts Student')
            ->call('resetFilters')
            ->assertSet('department_id', null)
            ->assertSet('search', '')
            ->assertSet('sort_by', 'name')
            ->assertSee('Eng Student')
            ->assertSee('Arts Student');
    }

    public function test_directory_sorting_controls(): void
    {
        $admin = $this->setupAdmin();

        $this->actingAs($admin);

        Livewire::test(Directory::class)
            ->set('sort_by', 'student_number')
            ->assertSet('sort_by', 'student_number')
            ->set('sort_direction', 'desc')
            ->assertSet('sort_direction', 'desc')
            ->set('per_page', 25)
            ->assertSet('per_page', 25);
    }

    public function test_my_classes_filter_and_reset_works(): void
    {
        Role::findOrCreate(RoleName::Faculty->value, 'web');
        Role::findOrCreate(RoleName::Student->value, 'web');
        Permission::findOrCreate('students.view-any', 'web');

        $faculty = User::factory()->create();
        $faculty->assignRole(RoleName::Faculty->value);
        $faculty->givePermissionTo('students.view-any');
        $profile = FacultyProfile::factory()->create(['user_id' => $faculty->id]);

        $course1 = Course::factory()->create([
            'faculty_profile_id' => $profile->id,
            'title' => 'Advanced Math',
            'is_active' => true,
        ]);
        $course2 = Course::factory()->create([
            'faculty_profile_id' => $profile->id,
            'title' => 'Physics 101',
            'is_active' => true,
        ]);

        $student1 = User::factory()->create(['name' => 'Math Student']);
        $student1->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create(['user_id' => $student1->id, 'student_number' => 'STU-M1']);
        Enrollment::factory()->create([
            'user_id' => $student1->id,
            'course_id' => $course1->id,
            'status' => 'active',
        ]);

        $student2 = User::factory()->create(['name' => 'Physics Student']);
        $student2->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create(['user_id' => $student2->id, 'student_number' => 'STU-P2']);
        Enrollment::factory()->create([
            'user_id' => $student2->id,
            'course_id' => $course2->id,
            'status' => 'active',
        ]);

        $this->actingAs($faculty);

        Livewire::test(MyClasses::class)
            ->assertOk()
            ->assertSee('My Class Students')
            ->set('course_id', $course1->id)
            ->assertSee('Math Student')
            ->assertDontSee('Physics Student')
            ->call('resetFilters')
            ->assertSet('course_id', null)
            ->assertSee('Math Student')
            ->assertSee('Physics Student');
    }
}
