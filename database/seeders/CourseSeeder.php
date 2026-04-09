<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Department;
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
            ['code' => 'CS101', 'enrollment_key' => 'CS101-UNITY', 'title' => 'Introduction to Computer Science', 'department' => 'CS', 'faculty_email' => 'alice@university.edu', 'credits' => 3, 'semester' => 'Fall', 'capacity' => 60],
            ['code' => 'CS201', 'enrollment_key' => 'CS201-UNITY', 'title' => 'Data Structures and Algorithms', 'department' => 'CS', 'faculty_email' => 'bob@university.edu', 'credits' => 4, 'semester' => 'Spring', 'capacity' => 50],
            ['code' => 'CS301', 'enrollment_key' => 'CS301-UNITY', 'title' => 'Database Systems', 'department' => 'CS', 'faculty_email' => 'alice@university.edu', 'credits' => 3, 'semester' => 'Fall', 'capacity' => 40],
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

            Course::create([
                'department_id' => $department->id,
                'faculty_profile_id' => $faculty?->facultyProfile?->id,
                'code' => $data['code'],
                'enrollment_key' => $data['enrollment_key'],
                'title' => $data['title'],
                'description' => 'A comprehensive course on '.strtolower($data['title']),
                'credits' => $data['credits'],
                'semester' => $data['semester'],
                'capacity' => $data['capacity'],
                'is_active' => true,
            ]);
        }
    }
}
