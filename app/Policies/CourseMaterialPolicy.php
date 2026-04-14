<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\CourseMaterial;
use App\Models\User;

class CourseMaterialPolicy
{
    protected function isInstructorOfMaterial(User $user, CourseMaterial $courseMaterial): bool
    {
        return $courseMaterial->course?->facultyProfile?->user_id === $user->id;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value, RoleName::Student->value]);
    }

    public function view(User $user, CourseMaterial $courseMaterial): bool
    {
        if ($user->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value])) {
            return true;
        }

        if ($this->isInstructorOfMaterial($user, $courseMaterial)) {
            return true;
        }

        return $courseMaterial->course->enrollments()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value]);
    }

    public function update(User $user, CourseMaterial $courseMaterial): bool
    {
        return $user->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value])
            || $this->isInstructorOfMaterial($user, $courseMaterial);
    }

    public function delete(User $user, CourseMaterial $courseMaterial): bool
    {
        return $user->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value])
            || $this->isInstructorOfMaterial($user, $courseMaterial);
    }

    public function download(User $user, CourseMaterial $courseMaterial): bool
    {
        return $this->view($user, $courseMaterial);
    }
}
