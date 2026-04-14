<?php

namespace Tests\Feature\ContentDelivery;

use App\Enums\RoleName;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\FacultyProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CourseContentDeliveryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{manager: User, student: User, nonEnrolledStudent: User, course: Course}
     */
    private function createUsersAndCourse(): array
    {
        Role::findOrCreate(RoleName::Admin->value, 'web');
        Role::findOrCreate(RoleName::Student->value, 'web');

        $manager = User::factory()->create();
        $manager->assignRole(RoleName::Admin->value);

        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create(['user_id' => $student->id]);

        $nonEnrolledStudent = User::factory()->create();
        $nonEnrolledStudent->assignRole(RoleName::Student->value);
        StudentProfile::factory()->create(['user_id' => $nonEnrolledStudent->id]);

        $course = Course::factory()->create(['is_active' => true]);

        Enrollment::query()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        return compact('manager', 'student', 'nonEnrolledStudent', 'course');
    }

    public function test_manager_can_create_module_and_update_syllabus(): void
    {
        ['manager' => $manager, 'course' => $course] = $this->createUsersAndCourse();

        Livewire::actingAs($manager)
            ->test('pages::courses.home', ['course' => $course])
            ->set('syllabus_content', 'Phase 2 syllabus content')
            ->call('saveSyllabus')
            ->set('module_title', 'Week 1 - Introduction')
            ->set('module_week_number', 1)
            ->set('module_description', 'Orientation and setup')
            ->set('module_position', 1)
            ->call('createModule')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'syllabus_content' => 'Phase 2 syllabus content',
        ]);

        $this->assertDatabaseHas('course_modules', [
            'course_id' => $course->id,
            'title' => 'Week 1 - Introduction',
            'week_number' => 1,
        ]);
    }

    public function test_manager_can_upload_module_material(): void
    {
        Storage::fake('local');
        ['manager' => $manager, 'course' => $course] = $this->createUsersAndCourse();

        $module = CourseModule::factory()->create([
            'course_id' => $course->id,
            'title' => 'Week 2',
        ]);

        Livewire::actingAs($manager)
            ->test('pages::courses.home', ['course' => $course])
            ->set('material_module_id', $module->id)
            ->set('material_title', 'Lecture Slides')
            ->set('material_description', 'Week 2 lecture deck')
            ->set('material_file', UploadedFile::fake()->create('lecture-week-2.pdf', 256, 'application/pdf'))
            ->call('uploadModuleMaterial')
            ->assertHasNoErrors();

        $material = CourseMaterial::query()->where('course_id', $course->id)->first();

        $this->assertNotNull($material);
        $this->assertFalse((bool) $material->is_syllabus);
        $this->assertTrue(Storage::disk('local')->exists($material->file_path));
    }

    public function test_enrolled_student_can_download_material(): void
    {
        Storage::fake('local');
        ['student' => $student, 'course' => $course] = $this->createUsersAndCourse();

        $module = CourseModule::factory()->create([
            'course_id' => $course->id,
            'title' => 'Week 3',
        ]);

        Storage::disk('local')->put('course-materials/'.$course->id.'/modules/'.$module->id.'/week3.pdf', 'week 3 content');

        $material = CourseMaterial::factory()->create([
            'course_id' => $course->id,
            'course_module_id' => $module->id,
            'uploaded_by' => $student->id,
            'title' => 'Week 3 PDF',
            'file_path' => 'course-materials/'.$course->id.'/modules/'.$module->id.'/week3.pdf',
            'original_name' => 'week3.pdf',
            'mime_type' => 'application/pdf',
            'is_syllabus' => false,
        ]);

        $this->actingAs($student)
            ->get(route('courses.materials.download', [$course, $material]))
            ->assertOk();
    }

    public function test_non_enrolled_student_cannot_download_material(): void
    {
        Storage::fake('local');
        ['nonEnrolledStudent' => $nonEnrolledStudent, 'course' => $course, 'student' => $student] = $this->createUsersAndCourse();

        $module = CourseModule::factory()->create([
            'course_id' => $course->id,
            'title' => 'Week 4',
        ]);

        Storage::disk('local')->put('course-materials/'.$course->id.'/modules/'.$module->id.'/week4.pdf', 'week 4 content');

        $material = CourseMaterial::factory()->create([
            'course_id' => $course->id,
            'course_module_id' => $module->id,
            'uploaded_by' => $student->id,
            'title' => 'Week 4 PDF',
            'file_path' => 'course-materials/'.$course->id.'/modules/'.$module->id.'/week4.pdf',
            'original_name' => 'week4.pdf',
            'mime_type' => 'application/pdf',
            'is_syllabus' => false,
        ]);

        $this->actingAs($nonEnrolledStudent)
            ->get(route('courses.materials.download', [$course, $material]))
            ->assertForbidden();

        $this->assertTrue(Storage::disk('local')->exists($material->file_path));
    }

    public function test_instructor_can_manage_content_for_assigned_course(): void
    {
        Storage::fake('local');

        Role::findOrCreate(RoleName::Faculty->value, 'web');

        $instructor = User::factory()->create();
        $instructor->assignRole(RoleName::Faculty->value);

        $facultyProfile = FacultyProfile::factory()->create([
            'user_id' => $instructor->id,
        ]);

        $course = Course::factory()->create([
            'faculty_profile_id' => $facultyProfile->id,
            'is_active' => true,
        ]);

        Livewire::actingAs($instructor)
            ->test('pages::courses.home', ['course' => $course])
            ->set('module_title', 'Week 1 - Instructor Module')
            ->set('module_week_number', 1)
            ->set('module_position', 1)
            ->call('createModule')
            ->assertHasNoErrors();

        $module = CourseModule::query()->where('course_id', $course->id)->firstOrFail();

        Livewire::actingAs($instructor)
            ->test('pages::courses.home', ['course' => $course])
            ->set('material_module_id', $module->id)
            ->set('material_title', 'Instructor Upload')
            ->set('material_description', 'Uploaded by assigned instructor')
            ->set('material_file', UploadedFile::fake()->create('instructor-notes.pdf', 128, 'application/pdf'))
            ->call('uploadModuleMaterial')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('course_materials', [
            'course_id' => $course->id,
            'course_module_id' => $module->id,
            'title' => 'Instructor Upload',
            'uploaded_by' => $instructor->id,
        ]);
    }
}
