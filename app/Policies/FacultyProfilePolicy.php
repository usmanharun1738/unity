<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\FacultyProfile;
use App\Models\User;

class FacultyProfilePolicy
{
    protected function canManage(User $user): bool
    {
        return $user->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value]);
    }

    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, FacultyProfile $facultyProfile): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, FacultyProfile $facultyProfile): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, FacultyProfile $facultyProfile): bool
    {
        return $this->canManage($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, FacultyProfile $facultyProfile): bool
    {
        return $this->canManage($user);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, FacultyProfile $facultyProfile): bool
    {
        return false;
    }
}
