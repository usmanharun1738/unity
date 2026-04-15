<?php

namespace App\Models;

use Database\Factories\GradeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'course_id', 'ca_score', 'test_score', 'assignment_score', 'quiz_score', 'project_score', 'exam_score', 'final_grade', 'grade_letter', 'is_approved_by_admin', 'approved_by', 'approved_at'])]
class Grade extends Model
{
    /** @use HasFactory<GradeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'ca_score' => 'decimal:2',
            'test_score' => 'decimal:2',
            'assignment_score' => 'decimal:2',
            'quiz_score' => 'decimal:2',
            'project_score' => 'decimal:2',
            'exam_score' => 'decimal:2',
            'final_grade' => 'decimal:2',
            'is_approved_by_admin' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
