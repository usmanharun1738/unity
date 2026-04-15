<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Quiz;
use App\Models\User;

class QuizPolicy
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
    public function view(User $user, Quiz $quiz): bool
    {
        if ($user->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value])) {
            return true;
        }

        if ($user->hasRole(RoleName::Faculty->value) && $user->facultyProfile) {
            return $quiz->course()->where('faculty_profile_id', $user->facultyProfile->id)->exists();
        }

        if ($user->hasRole(RoleName::Student->value)) {
            return $user->enrollments()
                ->where('course_id', $quiz->course_id)
                ->whereIn('status', ['active', 'enrolled'])
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
    public function update(User $user, Quiz $quiz): bool
    {
        if ($user->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value])) {
            return true;
        }

        if ($user->hasRole(RoleName::Faculty->value) && $user->facultyProfile) {
            return $quiz->course()->where('faculty_profile_id', $user->facultyProfile->id)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Quiz $quiz): bool
    {
        return $this->update($user, $quiz);
    }
}
