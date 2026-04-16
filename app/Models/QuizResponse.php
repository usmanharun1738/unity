<?php

namespace App\Models;

use Database\Factories\QuizResponseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['quiz_id', 'user_id', 'response_data', 'score', 'started_at', 'expires_at', 'submitted_at', 'time_taken_seconds', 'is_passed'])]
class QuizResponse extends Model
{
    /** @use HasFactory<QuizResponseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'response_data' => 'json',
            'score' => 'decimal:2',
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'is_passed' => 'boolean',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
