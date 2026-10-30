<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Grading\GradeCalculatorService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EnrollmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $deptMap = [
            'Computer Science' => Department::where('code', 'CS')->value('id'),
            'Engineering' => Department::where('code', 'ENG')->value('id'),
            'Business Administration' => Department::where('code', 'BUS')->value('id'),
            'Mathematics' => Department::where('code', 'MATH')->value('id'),
            'Physics' => Department::where('code', 'PHY')->value('id'),
        ];

        $students = [];

        // Primary Student account for easy evaluation
        $primaryStudent = User::create([
            'name' => 'Alex Student',
            'email' => 'student@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $primaryStudent->assignRole(RoleName::Student->value);
        StudentProfile::create([
            'user_id' => $primaryStudent->id,
            'department_id' => $deptMap['Computer Science'],
            'student_number' => 'STU-000001',
            'major' => 'Computer Science',
            'year_level' => 3,
            'bio' => 'Junior Computer Science student passionate about distributed systems and user experience design.',
            'avatar_path' => null,
        ]);
        $students[] = $primaryStudent;

        // Create 20 cohort students
        for ($i = 2; $i <= 20; $i++) {
            $major = fake()->randomElement(['Computer Science', 'Engineering', 'Business Administration', 'Mathematics', 'Physics']);
            $user = User::create([
                'name' => fake()->name(),
                'email' => 'student'.$i.'@university.edu',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
            $user->assignRole(RoleName::Student->value);
            StudentProfile::create([
                'user_id' => $user->id,
                'department_id' => $deptMap[$major] ?? null,
                'student_number' => 'STU-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'major' => $major,
                'year_level' => fake()->numberBetween(1, 4),
                'bio' => fake()->sentence(),
                'avatar_path' => null,
            ]);
            $students[] = $user;
        }

        // Get all courses
        $courses = Course::with('assignments', 'facultyProfile.user')->get();
        $gradeService = app(GradeCalculatorService::class);

        // Core courses for primary student
        $primaryCourses = Course::whereIn('code', ['CS101', 'CS201', 'CS301', 'MATH101', 'BUS101'])->get();

        foreach ($students as $student) {
            $selectedCourses = ($student->id === $primaryStudent->id)
                ? $primaryCourses
                : $courses->random(random_int(3, 5));

            foreach ($selectedCourses as $course) {
                $enrollment = Enrollment::create([
                    'user_id' => $student->id,
                    'course_id' => $course->id,
                    'enrolled_at' => now()->subDays(random_int(10, 45)),
                    'status' => 'enrolled',
                ]);

                // Create Grade record with realistic score components
                $ca = random_int(65, 95);
                $test = random_int(60, 95);
                $quiz = random_int(70, 100);
                $assignment = random_int(70, 95);
                $project = random_int(75, 98);
                $exam = random_int(65, 95);

                $grade = Grade::create([
                    'user_id' => $student->id,
                    'course_id' => $course->id,
                    'ca_score' => $ca,
                    'test_score' => $test,
                    'quiz_score' => $quiz,
                    'assignment_score' => $assignment,
                    'project_score' => $project,
                    'exam_score' => $exam,
                ]);

                $gradeService->recalculate($grade, false);

                // Admin approve some grades for realism
                if (fake()->boolean(40)) {
                    $grade->is_approved_by_admin = true;
                    $grade->approved_at = now()->subDays(random_int(1, 5));
                    $grade->save();
                }

                // Add sample attendance for the last 5 days
                for ($d = 5; $d >= 1; $d--) {
                    Attendance::create([
                        'user_id' => $student->id,
                        'course_id' => $course->id,
                        'attendance_date' => now()->subDays($d)->format('Y-m-d'),
                        'status' => fake()->randomElement(['present', 'present', 'present', 'late', 'excused']),
                        'marked_by' => $course->facultyProfile?->user_id,
                        'notes' => fake()->boolean(20) ? 'Attended on time and participated actively.' : null,
                    ]);
                }

                // Add assignment submission if assignment exists
                $courseAssignment = $course->assignments->first();
                if ($courseAssignment) {
                    AssignmentSubmission::create([
                        'assignment_id' => $courseAssignment->id,
                        'user_id' => $student->id,
                        'file_path' => 'submissions/sample_assignment_submission.pdf',
                        'submission_date' => now()->subDays(random_int(1, 4)),
                        'score' => random_int(75, 98),
                        'feedback' => 'Well structured, thoughtful methodology, and thorough code implementation.',
                        'graded_by' => $course->facultyProfile?->user_id,
                        'graded_at' => now()->subDays(1),
                        'is_late' => false,
                    ]);
                }
            }
        }
    }
}
