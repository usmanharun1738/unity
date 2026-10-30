<?php

namespace App\Events;

use App\Models\QuizResponse;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuizCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public QuizResponse $quizResponse,
    ) {}
}
