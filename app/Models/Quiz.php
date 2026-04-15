<?php

namespace App\Models;

use Database\Factories\QuizFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['course_id', 'title', 'description', 'max_score', 'time_limit_minutes', 'pass_score', 'show_results_immediately', 'display_order'])]
class Quiz extends Model
{
    /** @use HasFactory<QuizFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'max_score' => 'decimal:2',
            'pass_score' => 'decimal:2',
            'show_results_immediately' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(QuizResponse::class);
    }
}
