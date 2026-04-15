<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Assignment;
use App\Models\User;

class AssignmentPolicy
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
    public function view(User $user, Assignment $assignment): bool
    {
        if ($user->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value])) {
            return true;
        }

        if ($user->hasRole(RoleName::Faculty->value) && $user->facultyProfile) {
            return $assignment->course()->where('faculty_profile_id', $user->facultyProfile->id)->exists();
        }

        if ($user->hasRole(RoleName::Student->value)) {
            return $user->enrollments()
                ->where('course_id', $assignment->course_id)
                ->where('status', 'active')
                ->exists();
        }

        return false;
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
    public function update(User $user, Assignment $assignment): bool
    {
        if ($user->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value])) {
            return true;
        }

        return $user->hasRole(RoleName::Faculty->value)
            && $user->facultyProfile
            && $assignment->course()->where('faculty_profile_id', $user->facultyProfile->id)->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Assignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }
}
