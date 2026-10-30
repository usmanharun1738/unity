<?php

namespace App\Notifications;

use App\Models\AssignmentSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class AssignmentGradedNotification extends Notification implements ShouldQueue
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
        $this->submission->loadMissing('assignment');
        $assignment = $this->submission->assignment;
        $title = $assignment?->title ?? 'Assignment';
        $maxScore = $assignment?->max_score ?? 100;

        $mail = (new MailMessage)
            ->subject("Assignment Graded: {$title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your submission for \"{$title}\" has been graded.")
            ->line("Score: {$this->submission->score} / {$maxScore}");

        if (! empty($this->submission->feedback)) {
            $mail->line("Feedback: {$this->submission->feedback}");
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
        $this->submission->loadMissing('assignment');
        $assignment = $this->submission->assignment;

        return [
            'submission_id' => $this->submission->id,
            'assignment_id' => $this->submission->assignment_id,
            'assignment_title' => $assignment?->title,
            'score' => (float) $this->submission->score,
            'max_score' => (float) ($assignment?->max_score ?? 100),
            'feedback' => $this->submission->feedback,
            'graded_at' => $this->submission->graded_at?->toIso8601String(),
            'graded_by' => $this->submission->graded_by,
        ];
    }
}
