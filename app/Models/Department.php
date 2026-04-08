<?php

namespace App\Models;

use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'description', 'is_active'])]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    public function facultyProfiles(): HasMany
    {
        return $this->hasMany(FacultyProfile::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
