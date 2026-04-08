<?php

namespace App\Models;

use Database\Factories\FacultyProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'department_id', 'employee_code', 'title', 'bio'])]
class FacultyProfile extends Model
{
    /** @use HasFactory<FacultyProfileFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
