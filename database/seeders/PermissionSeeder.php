<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'students.view-any',
            'students.view-profile',
            'students.manage-assessments',
            'grades.view-any',
            'grades.manage',
            'grades.approve',
            'attendance.manage',
            'assignments.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $admin = Role::findByName(RoleName::Admin->value, 'web');
        $departmentStaff = Role::findByName(RoleName::DepartmentStaff->value, 'web');
        $faculty = Role::findByName(RoleName::Faculty->value, 'web');
        $student = Role::findByName(RoleName::Student->value, 'web');

        $admin->syncPermissions($permissions);

        $departmentStaff->syncPermissions([
            'students.view-any',
            'students.view-profile',
            'students.manage-assessments',
            'grades.view-any',
            'grades.manage',
            'grades.approve',
            'attendance.manage',
            'assignments.manage',
        ]);

        $faculty->syncPermissions([
            'students.view-any',
            'students.view-profile',
            'students.manage-assessments',
            'grades.view-any',
            'grades.manage',
            'attendance.manage',
            'assignments.manage',
        ]);

        $student->syncPermissions([
            'students.view-profile',
            'grades.view-any',
        ]);
    }
}
