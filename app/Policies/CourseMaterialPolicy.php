<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\CourseMaterial;
use App\Models\User;

class CourseMaterialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value, RoleName::Student->value]);
    }

    public function view(User $user, CourseMaterial $courseMaterial): bool
    {
        if ($user->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value])) {
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
        return $user->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value]);
    }

    public function delete(User $user, CourseMaterial $courseMaterial): bool
    {
        return $user->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value]);
    }

    public function download(User $user, CourseMaterial $courseMaterial): bool
    {
        return $this->view($user, $courseMaterial);
    }
}
