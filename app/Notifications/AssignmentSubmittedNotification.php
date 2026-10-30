<?php

namespace App\Notifications;

use App\Models\AssignmentSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class AssignmentSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public AssignmentSubmission $submission,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $this->submission->loadMissing(['assignment', 'student']);
        $assignment = $this->submission->assignment;
        $title = $assignment?->title ?? 'Assignment';
        $studentName = $this->submission->student?->name ?? 'A student';
        $submissionDate = $this->submission->submission_date?->toDayDateTimeString() ?? 'N/A';

        $mail = (new MailMessage)
            ->subject("New Assignment Submission: {$title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$studentName} has submitted an assignment for \"{$title}\".")
            ->line("Submitted at: {$submissionDate}");

        if ($this->submission->is_late) {
            $mail->line('Note: This submission was marked as late.');
        }

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->submission->loadMissing(['assignment', 'student']);
        $assignment = $this->submission->assignment;

        return [
            'submission_id' => $this->submission->id,
            'assignment_id' => $this->submission->assignment_id,
            'assignment_title' => $assignment?->title,
            'student_id' => $this->submission->user_id,
            'student_name' => $this->submission->student?->name,
            'submission_date' => $this->submission->submission_date?->toIso8601String(),
            'is_late' => (bool) $this->submission->is_late,
        ];
    }
}
