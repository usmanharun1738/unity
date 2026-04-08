<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            [
                'name' => 'Computer Science',
                'code' => 'CS',
                'description' => 'Department of Computer Science and Software Engineering',
                'is_active' => true,
            ],
            [
                'name' => 'Engineering',
                'code' => 'ENG',
                'description' => 'College of Engineering and Applied Sciences',
                'is_active' => true,
            ],
            [
                'name' => 'Business Administration',
                'code' => 'BUS',
                'description' => 'School of Business and Management',
                'is_active' => true,
            ],
            [
                'name' => 'Mathematics',
                'code' => 'MATH',
                'description' => 'Department of Mathematics and Statistics',
                'is_active' => true,
            ],
            [
                'name' => 'Physics',
                'code' => 'PHY',
                'description' => 'Department of Physics and Astronomy',
                'is_active' => true,
            ],
        ];

        foreach ($departments as $department) {
            Department::create($department);
        }
    }
}
