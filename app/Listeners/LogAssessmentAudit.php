<?php

namespace App\Listeners;

use App\Events\AssignmentGraded;
use App\Events\QuizCompleted;
use App\Models\AssessmentLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogAssessmentAudit implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(AssignmentGraded|QuizCompleted $event): void
    {
        if ($event instanceof AssignmentGraded) {
            $this->handleAssignmentGraded($event);
        } elseif ($event instanceof QuizCompleted) {
            $this->handleQuizCompleted($event);
        }
    }

    protected function handleAssignmentGraded(AssignmentGraded $event): void
    {
        $submission = $event->submission;
        $submission->loadMissing('assignment.course');
        $assignment = $submission->assignment;

        AssessmentLog::create([
            'user_id' => $submission->user_id,
            'course_id' => $assignment?->course_id,
            'assessment_type' => 'assignment',
            'assessment_name' => $assignment?->title ?? 'Assignment',
            'score' => $submission->score ?? 0,
            'max_score' => $assignment?->max_score ?? 100,
            'assessed_by' => $submission->graded_by,
            'assessed_at' => $submission->graded_at ?? now(),
            'notes' => $submission->feedback,
            'created_at' => now(),
        ]);
    }

    protected function handleQuizCompleted(QuizCompleted $event): void
    {
        $quizResponse = $event->quizResponse;
        $quizResponse->loadMissing('quiz.course');
        $quiz = $quizResponse->quiz;

        AssessmentLog::create([
            'user_id' => $quizResponse->user_id,
            'course_id' => $quiz?->course_id,
            'assessment_type' => 'quiz',
            'assessment_name' => $quiz?->title ?? 'Quiz',
            'score' => $quizResponse->score ?? 0,
            'max_score' => $quiz?->max_score ?? 100,
            'assessed_by' => null,
            'assessed_at' => $quizResponse->submitted_at ?? now(),
            'notes' => $quizResponse->is_passed ? 'Quiz passed' : 'Quiz completed',
            'created_at' => now(),
        ]);
    }
}
