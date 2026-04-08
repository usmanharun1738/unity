<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FacultySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = Department::pluck('id', 'code')->toArray();

        $facultyData = [
            ['name' => 'Dr. Alice Johnson', 'email' => 'alice@university.edu', 'department' => 'CS', 'title' => 'Professor'],
            ['name' => 'Dr. Bob Smith', 'email' => 'bob@university.edu', 'department' => 'CS', 'title' => 'Associate Professor'],
            ['name' => 'Dr. Carol Williams', 'email' => 'carol@university.edu', 'department' => 'ENG', 'title' => 'Professor'],
            ['name' => 'Dr. David Brown', 'email' => 'david@university.edu', 'department' => 'ENG', 'title' => 'Lecturer'],
            ['name' => 'Dr. Emma Davis', 'email' => 'emma@university.edu', 'department' => 'BUS', 'title' => 'Assistant Professor'],
            ['name' => 'Dr. Frank Miller', 'email' => 'frank@university.edu', 'department' => 'MATH', 'title' => 'Professor'],
            ['name' => 'Dr. Grace Lee', 'email' => 'grace@university.edu', 'department' => 'MATH', 'title' => 'Lecturer'],
            ['name' => 'Dr. Henry Wilson', 'email' => 'henry@university.edu', 'department' => 'PHY', 'title' => 'Associate Professor'],
            ['name' => 'Dr. Iris Taylor', 'email' => 'iris@university.edu', 'department' => 'PHY', 'title' => 'Assistant Professor'],
            ['name' => 'Dr. Jack Martinez', 'email' => 'jack@university.edu', 'department' => 'BUS', 'title' => 'Professor'],
        ];

        foreach ($facultyData as $index => $data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);

            $user->assignRole(RoleName::Faculty->value);

            $user->facultyProfile()->create([
                'department_id' => $departments[$data['department']],
                'employee_code' => 'FAC'.str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                'title' => $data['title'],
                'bio' => fake()->sentence(),
            ]);
        }
    }
}
