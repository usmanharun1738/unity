<?php

namespace App\Listeners;

use App\Events\AssignmentGraded;
use App\Events\AssignmentSubmitted;
use App\Models\User;
use App\Notifications\AssignmentGradedNotification;
use App\Notifications\AssignmentSubmittedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class SendAssignmentNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(AssignmentSubmitted|AssignmentGraded $event): void
    {
        if ($event instanceof AssignmentSubmitted) {
            $this->handleSubmitted($event);
        } elseif ($event instanceof AssignmentGraded) {
            $this->handleGraded($event);
        }
    }

    protected function handleSubmitted(AssignmentSubmitted $event): void
    {
        $submission = $event->submission;
        $submission->loadMissing(['assignment.course.facultyProfile.user', 'student']);

        $instructor = $submission->assignment?->course?->facultyProfile?->user;

        if ($instructor) {
            Notification::send($instructor, new AssignmentSubmittedNotification($submission));
        }
    }

    protected function handleGraded(AssignmentGraded $event): void
    {
        $submission = $event->submission;
        $submission->loadMissing(['assignment', 'student']);

        $student = $submission->student ?? User::find($submission->user_id);

        if ($student) {
            Notification::send($student, new AssignmentGradedNotification($submission));
        }
    }
}
