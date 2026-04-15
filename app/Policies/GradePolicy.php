<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Grade;
use App\Models\User;

class GradePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            RoleName::Admin->value,
            RoleName::DepartmentStaff->value,
            RoleName::Faculty->value,
            RoleName::Student->value,
        ]);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Grade $grade): bool
    {
        if ($user->id === $grade->user_id) {
            return true;
        }

        if ($user->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value])) {
            return true;
        }

        if (! $user->hasRole(RoleName::Faculty->value) || ! $user->facultyProfile) {
            return false;
        }

        return $grade->course()->where('faculty_profile_id', $user->facultyProfile->id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            RoleName::Admin->value,
            RoleName::DepartmentStaff->value,
            RoleName::Faculty->value,
        ]);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Grade $grade): bool
    {
        if ($user->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value])) {
            return true;
        }

        if (! $user->hasRole(RoleName::Faculty->value) || ! $user->facultyProfile) {
            return false;
        }

        return $grade->course()->where('faculty_profile_id', $user->facultyProfile->id)->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Grade $grade): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }

    /**
     * Determine whether the user can approve or override grades.
     */
    public function approve(User $user, Grade $grade): bool
    {
        return $user->hasAnyRole([
            RoleName::Admin->value,
            RoleName::DepartmentStaff->value,
        ]);
    }
}
