<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Department;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $courses = [
            ['code' => 'CS101', 'enrollment_key' => 'CS101-UNITY', 'title' => 'Introduction to Computer Science', 'department' => 'CS', 'faculty_email' => 'faculty@example.com', 'credits' => 3, 'semester' => 'Fall', 'capacity' => 60],
            ['code' => 'CS201', 'enrollment_key' => 'CS201-UNITY', 'title' => 'Data Structures and Algorithms', 'department' => 'CS', 'faculty_email' => 'bob@university.edu', 'credits' => 4, 'semester' => 'Spring', 'capacity' => 50],
            ['code' => 'CS301', 'enrollment_key' => 'CS301-UNITY', 'title' => 'Database Systems', 'department' => 'CS', 'faculty_email' => 'faculty@example.com', 'credits' => 3, 'semester' => 'Fall', 'capacity' => 40],
            ['code' => 'CS401', 'enrollment_key' => 'CS401-UNITY', 'title' => 'Software Engineering', 'department' => 'CS', 'faculty_email' => 'bob@university.edu', 'credits' => 4, 'semester' => 'Spring', 'capacity' => 35],

            ['code' => 'ENG101', 'enrollment_key' => 'ENG101-UNITY', 'title' => 'Engineering Mechanics', 'department' => 'ENG', 'faculty_email' => 'carol@university.edu', 'credits' => 4, 'semester' => 'Fall', 'capacity' => 80],
            ['code' => 'ENG201', 'enrollment_key' => 'ENG201-UNITY', 'title' => 'Thermodynamics', 'department' => 'ENG', 'faculty_email' => 'david@university.edu', 'credits' => 3, 'semester' => 'Spring', 'capacity' => 60],
            ['code' => 'ENG301', 'enrollment_key' => 'ENG301-UNITY', 'title' => 'Fluid Mechanics', 'department' => 'ENG', 'faculty_email' => 'carol@university.edu', 'credits' => 4, 'semester' => 'Fall', 'capacity' => 50],

            ['code' => 'BUS101', 'enrollment_key' => 'BUS101-UNITY', 'title' => 'Introduction to Business', 'department' => 'BUS', 'faculty_email' => 'emma@university.edu', 'credits' => 3, 'semester' => 'Fall', 'capacity' => 100],
            ['code' => 'BUS201', 'enrollment_key' => 'BUS201-UNITY', 'title' => 'Organizational Management', 'department' => 'BUS', 'faculty_email' => 'jack@university.edu', 'credits' => 3, 'semester' => 'Spring', 'capacity' => 70],
            ['code' => 'BUS301', 'enrollment_key' => 'BUS301-UNITY', 'title' => 'Strategic Planning', 'department' => 'BUS', 'faculty_email' => 'jack@university.edu', 'credits' => 3, 'semester' => 'Fall', 'capacity' => 50],

            ['code' => 'MATH101', 'enrollment_key' => 'MATH101-UNITY', 'title' => 'Calculus I', 'department' => 'MATH', 'faculty_email' => 'frank@university.edu', 'credits' => 4, 'semester' => 'Fall', 'capacity' => 120],
            ['code' => 'MATH201', 'enrollment_key' => 'MATH201-UNITY', 'title' => 'Calculus II', 'department' => 'MATH', 'faculty_email' => 'grace@university.edu', 'credits' => 4, 'semester' => 'Spring', 'capacity' => 100],
            ['code' => 'MATH301', 'enrollment_key' => 'MATH301-UNITY', 'title' => 'Linear Algebra', 'department' => 'MATH', 'faculty_email' => 'frank@university.edu', 'credits' => 3, 'semester' => 'Fall', 'capacity' => 80],

            ['code' => 'PHY101', 'enrollment_key' => 'PHY101-UNITY', 'title' => 'Physics I: Mechanics', 'department' => 'PHY', 'faculty_email' => 'henry@university.edu', 'credits' => 4, 'semester' => 'Fall', 'capacity' => 90],
            ['code' => 'PHY201', 'enrollment_key' => 'PHY201-UNITY', 'title' => 'Physics II: Electricity and Magnetism', 'department' => 'PHY', 'faculty_email' => 'iris@university.edu', 'credits' => 4, 'semester' => 'Spring', 'capacity' => 80],
            ['code' => 'PHY301', 'enrollment_key' => 'PHY301-UNITY', 'title' => 'Modern Physics', 'department' => 'PHY', 'faculty_email' => 'henry@university.edu', 'credits' => 3, 'semester' => 'Fall', 'capacity' => 60],
        ];

        foreach ($courses as $data) {
            $department = Department::where('code', $data['department'])->first();
            $faculty = User::where('email', $data['faculty_email'])->first();

            $subject = Subject::firstOrCreate(
                ['code' => $data['code']],
                [
                    'department_id' => $department->id,
                    'title' => $data['title'],
                    'description' => 'A comprehensive academic course on '.strtolower($data['title']),
                    'credits' => $data['credits'],
                ]
            );

            $course = Course::create([
                'subject_id' => $subject->id,
                'department_id' => $department->id,
                'faculty_profile_id' => $faculty?->facultyProfile?->id,
                'code' => $data['code'],
                'section_code' => 'Section A',
                'academic_year' => '2026-2027',
                'enrollment_key' => $data['enrollment_key'],
                'title' => $data['title'],
                'description' => 'A comprehensive course on '.strtolower($data['title']),
                'credits' => $data['credits'],
                'semester' => $data['semester'],
                'capacity' => $data['capacity'],
                'is_active' => true,
            ]);

            // Add sample modules
            CourseModule::create([
                'course_id' => $course->id,
                'title' => 'Module 1: Foundations & Fundamentals',
                'week_number' => 1,
                'description' => 'Orientation, syllabus breakdown, core terminology, and baseline concepts.',
                'position' => 1,
            ]);

            CourseModule::create([
                'course_id' => $course->id,
                'title' => 'Module 2: Advanced Applied Methodologies',
                'week_number' => 2,
                'description' => 'Deep dive into practical implementation, problem solving, and project analysis.',
                'position' => 2,
            ]);

            // Add sample assignment
            Assignment::create([
                'course_id' => $course->id,
                'title' => 'Assignment 1: Comprehensive Problem Set',
                'description' => 'Submit your solutions as a PDF or DOCX file detailing your methodology, code, and conclusions.',
                'due_date' => now()->addDays(7),
                'max_score' => 100,
                'display_order' => 1,
            ]);

            // Add sample quiz
            $quiz = Quiz::create([
                'course_id' => $course->id,
                'title' => 'Quiz 1: Concept Verification',
                'description' => 'Multiple choice review covering key topics from Modules 1 and 2.',
                'max_score' => 100,
                'time_limit_minutes' => 30,
                'pass_score' => 60,
                'show_results_immediately' => true,
                'display_order' => 1,
            ]);

            QuizQuestion::create([
                'quiz_id' => $quiz->id,
                'question_type' => 'multiple_choice',
                'prompt' => 'Which of the following best describes the core principle of this subject?',
                'rubric_text' => 'Select the most accurate definition.',
                'allows_multiple' => false,
                'options' => [
                    'A' => 'Comprehensive theoretical understanding and application',
                    'B' => 'Arbitrary guesswork',
                    'C' => 'Ignoring formal constraints',
                    'D' => 'None of the above',
                ],
                'correct_options' => ['A'],
                'points' => 50,
                'display_order' => 1,
            ]);

            QuizQuestion::create([
                'quiz_id' => $quiz->id,
                'question_type' => 'multiple_choice',
                'prompt' => 'What is the primary evaluation milestone for this term?',
                'rubric_text' => 'Standard midterm assessment.',
                'allows_multiple' => false,
                'options' => [
                    'A' => 'Continuous Assessment & Final Exam',
                    'B' => 'Attendance only',
                    'C' => 'No evaluation needed',
                    'D' => 'Optional survey',
                ],
                'correct_options' => ['A'],
                'points' => 50,
                'display_order' => 2,
            ]);
        }
    }
}
