<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;

class StudentPolicy
{
    /**
     * Determine whether the user can view the student directory.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            RoleName::Admin->value,
            RoleName::DepartmentStaff->value,
            RoleName::Faculty->value,
        ]);
    }

    /**
     * Determine whether the user can view a student profile.
     */
    public function view(User $user, User $student): bool
    {
        if ($user->id === $student->id) {
            return true;
        }

        if ($user->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value])) {
            return true;
        }

        if (! $user->hasRole(RoleName::Faculty->value) || ! $user->facultyProfile) {
            return false;
        }

        return $student->enrollments()
            ->whereHas('course', function ($query) use ($user): void {
                $query->where('faculty_profile_id', $user->facultyProfile->id);
            })
            ->exists();
    }

    /**
     * Determine whether the user can view student academic records.
     */
    public function viewAcademicRecords(User $user, User $student): bool
    {
        return $this->view($user, $student);
    }

    /**
     * Determine whether the user can manage a student's scores for a specific course.
     */
    public function manageCourseAssessments(User $user, User $student, int $courseId): bool
    {
        if (! $this->viewAcademicRecords($user, $student)) {
            return false;
        }

        if ($user->hasAnyRole([RoleName::Admin->value, RoleName::DepartmentStaff->value])) {
            return true;
        }

        if (! $user->hasRole(RoleName::Faculty->value) || ! $user->facultyProfile) {
            return false;
        }

        return $student->enrollments()
            ->where('course_id', $courseId)
            ->where('status', 'active')
            ->whereHas('course', function ($query) use ($user): void {
                $query->where('faculty_profile_id', $user->facultyProfile->id);
            })
            ->exists();
    }
}
