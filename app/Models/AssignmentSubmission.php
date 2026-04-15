<?php

namespace App\Models;

use Database\Factories\AssignmentSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assignment_id', 'user_id', 'file_path', 'submission_date', 'score', 'feedback', 'graded_by', 'graded_at', 'is_late'])]
class AssignmentSubmission extends Model
{
    /** @use HasFactory<AssignmentSubmissionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'submission_date' => 'datetime',
            'score' => 'decimal:2',
            'graded_at' => 'datetime',
            'is_late' => 'boolean',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
