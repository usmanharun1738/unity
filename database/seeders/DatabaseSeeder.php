<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(DepartmentSeeder::class);
        $this->call(FacultySeeder::class);
        $this->call(CourseSeeder::class);
        $this->call(EnrollmentSeeder::class);

        // Create admin user
        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@university.edu',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $adminUser->assignRole(RoleName::Admin->value);

        // Create department staff user
        $deptStaffUser = User::create([
            'name' => 'Department Staff',
            'email' => 'deptstaff@university.edu',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $deptStaffUser->assignRole(RoleName::DepartmentStaff->value);
    }
}
