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
            ['code' => 'CS101', 'title' => 'Introduction to Computer Science', 'department' => 'CS', 'faculty_email' => 'alice@university.edu', 'credits' => 3, 'semester' => 'Fall', 'capacity' => 60],
            ['code' => 'CS201', 'title' => 'Data Structures and Algorithms', 'department' => 'CS', 'faculty_email' => 'bob@university.edu', 'credits' => 4, 'semester' => 'Spring', 'capacity' => 50],
            ['code' => 'CS301', 'title' => 'Database Systems', 'department' => 'CS', 'faculty_email' => 'alice@university.edu', 'credits' => 3, 'semester' => 'Fall', 'capacity' => 40],
            ['code' => 'CS401', 'title' => 'Software Engineering', 'department' => 'CS', 'faculty_email' => 'bob@university.edu', 'credits' => 4, 'semester' => 'Spring', 'capacity' => 35],

            ['code' => 'ENG101', 'title' => 'Engineering Mechanics', 'department' => 'ENG', 'faculty_email' => 'carol@university.edu', 'credits' => 4, 'semester' => 'Fall', 'capacity' => 80],
            ['code' => 'ENG201', 'title' => 'Thermodynamics', 'department' => 'ENG', 'faculty_email' => 'david@university.edu', 'credits' => 3, 'semester' => 'Spring', 'capacity' => 60],
            ['code' => 'ENG301', 'title' => 'Fluid Mechanics', 'department' => 'ENG', 'faculty_email' => 'carol@university.edu', 'credits' => 4, 'semester' => 'Fall', 'capacity' => 50],

            ['code' => 'BUS101', 'title' => 'Introduction to Business', 'department' => 'BUS', 'faculty_email' => 'emma@university.edu', 'credits' => 3, 'semester' => 'Fall', 'capacity' => 100],
            ['code' => 'BUS201', 'title' => 'Organizational Management', 'department' => 'BUS', 'faculty_email' => 'jack@university.edu', 'credits' => 3, 'semester' => 'Spring', 'capacity' => 70],
            ['code' => 'BUS301', 'title' => 'Strategic Planning', 'department' => 'BUS', 'faculty_email' => 'jack@university.edu', 'credits' => 3, 'semester' => 'Fall', 'capacity' => 50],

            ['code' => 'MATH101', 'title' => 'Calculus I', 'department' => 'MATH', 'faculty_email' => 'frank@university.edu', 'credits' => 4, 'semester' => 'Fall', 'capacity' => 120],
            ['code' => 'MATH201', 'title' => 'Calculus II', 'department' => 'MATH', 'faculty_email' => 'grace@university.edu', 'credits' => 4, 'semester' => 'Spring', 'capacity' => 100],
            ['code' => 'MATH301', 'title' => 'Linear Algebra', 'department' => 'MATH', 'faculty_email' => 'frank@university.edu', 'credits' => 3, 'semester' => 'Fall', 'capacity' => 80],

            ['code' => 'PHY101', 'title' => 'Physics I: Mechanics', 'department' => 'PHY', 'faculty_email' => 'henry@university.edu', 'credits' => 4, 'semester' => 'Fall', 'capacity' => 90],
            ['code' => 'PHY201', 'title' => 'Physics II: Electricity and Magnetism', 'department' => 'PHY', 'faculty_email' => 'iris@university.edu', 'credits' => 4, 'semester' => 'Spring', 'capacity' => 80],
            ['code' => 'PHY301', 'title' => 'Modern Physics', 'department' => 'PHY', 'faculty_email' => 'henry@university.edu', 'credits' => 3, 'semester' => 'Fall', 'capacity' => 60],
        ];

        foreach ($courses as $data) {
            $department = Department::where('code', $data['department'])->first();
            $faculty = User::where('email', $data['faculty_email'])->first();

            Course::create([
                'department_id' => $department->id,
                'faculty_profile_id' => $faculty?->facultyProfile?->id,
                'code' => $data['code'],
                'title' => $data['title'],
                'description' => 'A comprehensive course on ' . strtolower($data['title']),
                'credits' => $data['credits'],
                'semester' => $data['semester'],
                'capacity' => $data['capacity'],
                'is_active' => true,
            ]);
        }
    }
}
