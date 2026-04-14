<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    protected function canManage(User $user): bool
    {
        return $user->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value]);
    }

    public function viewAny(User $user): bool
    {
        return $user->exists;
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        return $this->canManage($user) || $enrollment->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->studentProfile()->exists();
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return $this->canManage($user) || $enrollment->user_id === $user->id;
    }

    public function delete(User $user, Enrollment $enrollment): bool
    {
        return $this->canManage($user) || $enrollment->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Enrollment $enrollment): bool
    {
        return $this->canManage($user);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Enrollment $enrollment): bool
    {
        return false;
    }
}
