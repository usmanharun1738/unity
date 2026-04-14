<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\CourseModule;
use App\Models\User;

class CourseModulePolicy
{
    protected function isInstructorOfModule(User $user, CourseModule $courseModule): bool
    {
        return $courseModule->course?->facultyProfile?->user_id === $user->id;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value, RoleName::Student->value]);
    }

    public function view(User $user, CourseModule $courseModule): bool
    {
        if ($user->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value])) {
            return true;
        }

        if ($this->isInstructorOfModule($user, $courseModule)) {
            return true;
        }

        return $courseModule->course->enrollments()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value]);
    }

    public function update(User $user, CourseModule $courseModule): bool
    {
        return $user->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value])
            || $this->isInstructorOfModule($user, $courseModule);
    }

    public function delete(User $user, CourseModule $courseModule): bool
    {
        return $user->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value])
            || $this->isInstructorOfModule($user, $courseModule);
    }
}
