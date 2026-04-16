<?php

namespace App\Models;

use Database\Factories\QuizQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['quiz_id', 'question_type', 'prompt', 'rubric_text', 'allows_multiple', 'options', 'correct_options', 'points', 'display_order'])]
class QuizQuestion extends Model
{
    /** @use HasFactory<QuizQuestionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'allows_multiple' => 'boolean',
            'options' => 'json',
            'correct_options' => 'json',
            'points' => 'decimal:2',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}
