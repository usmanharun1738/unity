<?php

namespace Tests\Feature\Courses;

use App\Enums\RoleName;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BrowseCourseCatalogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{student: User, manager: User}
     */
    private function seedRolesAndUsers(): array
    {
        Role::findOrCreate(RoleName::Student->value, 'web');
        Role::findOrCreate(RoleName::Admin->value, 'web');

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);

        $manager = User::factory()->create();
        $manager->assignRole(RoleName::Admin->value);

        return compact('student', 'manager');
    }

    public function test_authenticated_user_can_access_browse_page(): void
    {
        ['student' => $student] = $this->seedRolesAndUsers();

        $this->actingAs($student)
            ->get(route('courses.browse'))
            ->assertOk();
    }

    public function test_student_only_sees_active_courses_plus_archived_enrolled_courses(): void
    {
        ['student' => $student] = $this->seedRolesAndUsers();

        $active = Course::factory()->create(['title' => 'Active Public Course', 'is_active' => true]);
        $archivedHidden = Course::factory()->create(['title' => 'Archived Hidden Course', 'is_active' => false]);
        $archivedEnrolled = Course::factory()->create(['title' => 'Archived Enrolled Course', 'is_active' => false]);

        Enrollment::factory()->create([
            'user_id' => $student->id,
            'course_id' => $archivedEnrolled->id,
            'status' => 'enrolled',
        ]);

        $this->actingAs($student)
            ->get(route('courses.browse'))
            ->assertSeeText($active->title)
            ->assertSeeText($archivedEnrolled->title)
            ->assertDontSeeText($archivedHidden->title);
    }

    public function test_browse_filters_apply_for_search_department_semester_and_my_only(): void
    {
        ['student' => $student] = $this->seedRolesAndUsers();

        $science = Department::factory()->create(['name' => 'Science Department']);
        $arts = Department::factory()->create(['name' => 'Arts Department']);

        $targetCourse = Course::factory()->create([
            'title' => 'Data Mining Fundamentals',
            'code' => 'SCI301',
            'department_id' => $science->id,
            'semester' => 'Spring',
            'is_active' => true,
        ]);

        $otherCourse = Course::factory()->create([
            'title' => 'Modern Art History',
            'code' => 'ART201',
            'department_id' => $arts->id,
            'semester' => 'Fall',
            'is_active' => true,
        ]);

        Enrollment::factory()->create([
            'user_id' => $student->id,
            'course_id' => $targetCourse->id,
            'status' => 'enrolled',
        ]);

        Livewire::actingAs($student)
            ->test('pages::courses.browse')
            ->set('search', 'Mining')
            ->set('department_id', $science->id)
            ->set('semester', 'Spring')
            ->set('my_only', true)
            ->assertSeeText($targetCourse->title)
            ->assertDontSeeText($otherCourse->title);
    }

    public function test_manager_can_toggle_archived_courses_in_results(): void
    {
        ['manager' => $manager] = $this->seedRolesAndUsers();

        $active = Course::factory()->create(['title' => 'Manager Active Course', 'is_active' => true]);
        $archived = Course::factory()->create(['title' => 'Manager Archived Course', 'is_active' => false]);

        Livewire::actingAs($manager)
            ->test('pages::courses.browse')
            ->assertSeeText($active->title)
            ->assertDontSeeText($archived->title)
            ->set('show_archived', true)
            ->assertSeeText($archived->title);
    }

    public function test_sorting_and_per_page_controls_change_visible_results(): void
    {
        ['student' => $student] = $this->seedRolesAndUsers();

        foreach (['AAA', 'BBB', 'CCC', 'DDD', 'EEE', 'FFF', 'GGG', 'HHH', 'III'] as $index => $prefix) {
            Course::factory()->create([
                'title' => $prefix.' Course',
                'code' => 'CAT'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'is_active' => true,
            ]);
        }

        Livewire::actingAs($student)
            ->test('pages::courses.browse')
            ->set('sort_by', 'title')
            ->set('sort_direction', 'asc')
            ->set('per_page', 6)
            ->assertSeeText('AAA Course')
            ->assertSeeText('FFF Course')
            ->assertDontSeeText('GGG Course');

        Livewire::actingAs($student)
            ->test('pages::courses.browse')
            ->set('sort_by', 'title')
            ->set('sort_direction', 'desc')
            ->set('per_page', 6)
            ->assertSeeText('III Course')
            ->assertDontSeeText('CCC Course');
    }
}
