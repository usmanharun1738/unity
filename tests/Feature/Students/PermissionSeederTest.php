<?php

namespace Tests\Feature\Students;

use App\Enums\RoleName;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_seeder_creates_permissions_and_maps_them_to_roles(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $expectedPermissions = [
            'students.view-any',
            'students.view-profile',
            'students.manage-assessments',
            'grades.view-any',
            'grades.manage',
            'grades.approve',
            'attendance.manage',
            'assignments.manage',
            'courses.view-any',
            'courses.view',
            'courses.manage-content',
            'enrollments.view-any',
            'enrollments.create',
            'enrollments.manage',
            'materials.download',
        ];

        foreach ($expectedPermissions as $permissionName) {
            $this->assertTrue(
                Permission::query()->where('name', $permissionName)->where('guard_name', 'web')->exists(),
                "Permission [{$permissionName}] was not seeded.",
            );
        }

        $admin = Role::findByName(RoleName::Admin->value, 'web');
        $departmentStaff = Role::findByName(RoleName::DepartmentStaff->value, 'web');
        $faculty = Role::findByName(RoleName::Faculty->value, 'web');
        $student = Role::findByName(RoleName::Student->value, 'web');

        foreach ($expectedPermissions as $permissionName) {
            $this->assertTrue($admin->hasPermissionTo($permissionName));
        }

        $this->assertTrue($departmentStaff->hasPermissionTo('grades.approve'));
        $this->assertTrue($departmentStaff->hasPermissionTo('courses.manage-content'));
        $this->assertTrue($departmentStaff->hasPermissionTo('enrollments.manage'));
        $this->assertFalse($faculty->hasPermissionTo('grades.approve'));
        $this->assertTrue($faculty->hasPermissionTo('grades.manage'));
        $this->assertTrue($faculty->hasPermissionTo('courses.manage-content'));
        $this->assertTrue($faculty->hasPermissionTo('enrollments.manage'));
        $this->assertFalse($faculty->hasPermissionTo('enrollments.create'));
        $this->assertTrue($student->hasPermissionTo('students.view-profile'));
        $this->assertFalse($student->hasPermissionTo('students.view-any'));
        $this->assertTrue($student->hasPermissionTo('courses.view-any'));
        $this->assertTrue($student->hasPermissionTo('courses.view'));
        $this->assertTrue($student->hasPermissionTo('enrollments.view-any'));
        $this->assertTrue($student->hasPermissionTo('enrollments.create'));
        $this->assertFalse($student->hasPermissionTo('enrollments.manage'));
        $this->assertTrue($student->hasPermissionTo('materials.download'));
    }
}
