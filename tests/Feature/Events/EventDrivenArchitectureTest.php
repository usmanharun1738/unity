<?php

namespace Tests\Feature\Events;

use App\Events\AssignmentGraded;
use App\Events\AssignmentSubmitted;
use App\Events\QuizCompleted;
use App\Events\StudentEnrolled;
use App\Listeners\LogAssessmentAudit;
use App\Listeners\SendAssignmentNotification;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FacultyProfile;
use App\Models\Quiz;
use App\Models\QuizResponse;
use App\Models\User;
use App\Notifications\AssignmentGradedNotification;
use App\Notifications\AssignmentSubmittedNotification;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EventDrivenArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_submitted_event_can_be_dispatched(): void
    {
        Event::fake([AssignmentSubmitted::class]);

        $submission = AssignmentSubmission::factory()->create();

        AssignmentSubmitted::dispatch($submission);

        Event::assertDispatched(AssignmentSubmitted::class, function (AssignmentSubmitted $event) use ($submission): bool {
            return $event->submission->id === $submission->id;
        });
    }

    public function test_assignment_graded_event_can_be_dispatched(): void
    {
        Event::fake([AssignmentGraded::class]);

        $submission = AssignmentSubmission::factory()->create([
            'score' => 95.50,
            'feedback' => 'Great work!',
        ]);

        AssignmentGraded::dispatch($submission);

        Event::assertDispatched(AssignmentGraded::class, function (AssignmentGraded $event) use ($submission): bool {
            return $event->submission->id === $submission->id;
        });
    }

    public function test_quiz_completed_event_can_be_dispatched(): void
    {
        Event::fake([QuizCompleted::class]);

        $quizResponse = QuizResponse::factory()->create([
            'score' => 88.00,
            'is_passed' => true,
        ]);

        QuizCompleted::dispatch($quizResponse);

        Event::assertDispatched(QuizCompleted::class, function (QuizCompleted $event) use ($quizResponse): bool {
            return $event->quizResponse->id === $quizResponse->id;
        });
    }

    public function test_student_enrolled_event_can_be_dispatched(): void
    {
        Event::fake([StudentEnrolled::class]);

        $enrollment = Enrollment::factory()->create();

        StudentEnrolled::dispatch($enrollment);

        Event::assertDispatched(StudentEnrolled::class, function (StudentEnrolled $event) use ($enrollment): bool {
            return $event->enrollment->id === $enrollment->id;
        });
    }

    public function test_send_assignment_notification_listener_notifies_instructor_on_submission(): void
    {
        Notification::fake();

        $instructor = User::factory()->create(['name' => 'Professor Smith']);
        $facultyProfile = FacultyProfile::factory()->create(['user_id' => $instructor->id]);
        $course = Course::factory()->create(['faculty_profile_id' => $facultyProfile->id]);
        $assignment = Assignment::factory()->create(['course_id' => $course->id, 'title' => 'Biology 101 Essay']);
        $student = User::factory()->create(['name' => 'Alice Student']);

        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'is_late' => false,
        ]);

        $listener = new SendAssignmentNotification;
        $listener->handle(new AssignmentSubmitted($submission));

        Notification::assertSentTo(
            $instructor,
            AssignmentSubmittedNotification::class,
            function (AssignmentSubmittedNotification $notification) use ($submission): bool {
                return $notification->submission->id === $submission->id;
            }
        );

        Notification::assertNotSentTo($student, AssignmentSubmittedNotification::class);
    }

    public function test_send_assignment_notification_listener_notifies_student_on_graded(): void
    {
        Notification::fake();

        $instructor = User::factory()->create();
        $student = User::factory()->create(['name' => 'Bob Student']);
        $assignment = Assignment::factory()->create(['title' => 'Chemistry Lab 1', 'max_score' => 100]);

        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'score' => 88.50,
            'feedback' => 'Solid analysis and observations.',
            'graded_by' => $instructor->id,
            'graded_at' => now(),
        ]);

        $listener = new SendAssignmentNotification;
        $listener->handle(new AssignmentGraded($submission));

        Notification::assertSentTo(
            $student,
            AssignmentGradedNotification::class,
            function (AssignmentGradedNotification $notification) use ($submission): bool {
                return $notification->submission->id === $submission->id;
            }
        );

        Notification::assertNotSentTo($instructor, AssignmentGradedNotification::class);
    }

    public function test_assignment_graded_notification_mail_and_database_structure(): void
    {
        $student = User::factory()->create(['name' => 'Carol Student']);
        $assignment = Assignment::factory()->create(['title' => 'Midterm Essay', 'max_score' => 50]);

        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'score' => 45.00,
            'feedback' => 'Well researched arguments.',
            'graded_at' => now(),
        ]);

        $notification = new AssignmentGradedNotification($submission);

        $channels = $notification->via($student);
        $this->assertContains('mail', $channels);
        $this->assertContains('database', $channels);

        $mail = $notification->toMail($student);
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertEquals('Assignment Graded: Midterm Essay', $mail->subject);
        $this->assertStringContainsString('Carol Student', $mail->greeting);
        $this->assertTrue(collect($mail->introLines)->contains(fn ($line): bool => str_contains($line, '45.00') && str_contains($line, '50')));
        $this->assertTrue(collect($mail->introLines)->contains(fn ($line): bool => str_contains($line, 'Well researched arguments.')));

        $data = $notification->toArray($student);
        $this->assertEquals($submission->id, $data['submission_id']);
        $this->assertEquals($assignment->id, $data['assignment_id']);
        $this->assertEquals('Midterm Essay', $data['assignment_title']);
        $this->assertEquals(45.0, $data['score']);
        $this->assertEquals(50.0, $data['max_score']);
        $this->assertEquals('Well researched arguments.', $data['feedback']);
    }

    public function test_assignment_submitted_notification_mail_and_database_structure(): void
    {
        $instructor = User::factory()->create(['name' => 'Dr. Jones']);
        $student = User::factory()->create(['name' => 'David Student']);
        $assignment = Assignment::factory()->create(['title' => 'Final Project']);

        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'is_late' => true,
            'submission_date' => now(),
        ]);

        $notification = new AssignmentSubmittedNotification($submission);

        $channels = $notification->via($instructor);
        $this->assertContains('mail', $channels);
        $this->assertContains('database', $channels);

        $mail = $notification->toMail($instructor);
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertEquals('New Assignment Submission: Final Project', $mail->subject);
        $this->assertStringContainsString('David Student', implode(' ', $mail->introLines));
        $this->assertTrue(collect($mail->introLines)->contains(fn ($line): bool => str_contains($line, 'late')));

        $data = $notification->toArray($instructor);
        $this->assertEquals($submission->id, $data['submission_id']);
        $this->assertEquals('Final Project', $data['assignment_title']);
        $this->assertEquals($student->id, $data['student_id']);
        $this->assertEquals('David Student', $data['student_name']);
        $this->assertTrue($data['is_late']);
    }

    public function test_notifications_can_be_stored_in_database(): void
    {
        $student = User::factory()->create();
        $submission = AssignmentSubmission::factory()->create(['user_id' => $student->id]);

        Notification::send($student, new AssignmentGradedNotification($submission));

        $this->assertEquals(1, $student->notifications()->count());
        $notification = $student->notifications()->first();
        $this->assertEquals(AssignmentGradedNotification::class, $notification->type);
        $this->assertEquals($submission->id, $notification->data['submission_id']);
    }

    public function test_log_assessment_audit_records_log_for_assignment_graded(): void
    {
        $instructor = User::factory()->create();
        $student = User::factory()->create();
        $course = Course::factory()->create();
        $assignment = Assignment::factory()->create([
            'course_id' => $course->id,
            'title' => 'Physics Homework 1',
            'max_score' => 100.00,
        ]);

        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'score' => 92.50,
            'feedback' => 'Excellent solutions.',
            'graded_by' => $instructor->id,
            'graded_at' => now(),
        ]);

        $listener = new LogAssessmentAudit;
        $listener->handle(new AssignmentGraded($submission));

        $this->assertDatabaseHas('assessment_logs', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'assessment_type' => 'assignment',
            'assessment_name' => 'Physics Homework 1',
            'score' => 92.50,
            'max_score' => 100.00,
            'assessed_by' => $instructor->id,
            'notes' => 'Excellent solutions.',
        ]);
    }

    public function test_log_assessment_audit_records_log_for_quiz_completed(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create();
        $quiz = Quiz::factory()->create([
            'course_id' => $course->id,
            'title' => 'History Quiz 1',
            'max_score' => 50.00,
        ]);

        $quizResponse = QuizResponse::factory()->create([
            'quiz_id' => $quiz->id,
            'user_id' => $student->id,
            'score' => 42.00,
            'is_passed' => true,
            'submitted_at' => now(),
        ]);

        $listener = new LogAssessmentAudit;
        $listener->handle(new QuizCompleted($quizResponse));

        $this->assertDatabaseHas('assessment_logs', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'assessment_type' => 'quiz',
            'assessment_name' => 'History Quiz 1',
            'score' => 42.00,
            'max_score' => 50.00,
            'assessed_by' => null,
            'notes' => 'Quiz passed',
        ]);
    }

    public function test_end_to_end_assignment_graded_event_triggers_both_listeners(): void
    {
        Notification::fake();

        $instructor = User::factory()->create();
        $student = User::factory()->create();
        $course = Course::factory()->create();
        $assignment = Assignment::factory()->create([
            'course_id' => $course->id,
            'title' => 'Literature Review',
            'max_score' => 100.00,
        ]);

        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'score' => 88.00,
            'feedback' => 'Thorough literature analysis.',
            'graded_by' => $instructor->id,
            'graded_at' => now(),
        ]);

        // Dispatch actual event without faking events
        AssignmentGraded::dispatch($submission);

        // 1. Audit log recorded
        $this->assertDatabaseHas('assessment_logs', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'assessment_type' => 'assignment',
            'score' => 88.00,
        ]);

        // 2. Notification sent
        Notification::assertSentTo($student, AssignmentGradedNotification::class);
    }

    public function test_end_to_end_quiz_completed_event_triggers_audit_log(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->create();
        $quiz = Quiz::factory()->create([
            'course_id' => $course->id,
            'title' => 'Calculus Quiz',
            'max_score' => 20.00,
        ]);

        $quizResponse = QuizResponse::factory()->create([
            'quiz_id' => $quiz->id,
            'user_id' => $student->id,
            'score' => 18.00,
            'is_passed' => true,
            'submitted_at' => now(),
        ]);

        QuizCompleted::dispatch($quizResponse);

        $this->assertDatabaseHas('assessment_logs', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'assessment_type' => 'quiz',
            'assessment_name' => 'Calculus Quiz',
            'score' => 18.00,
        ]);
    }

    public function test_events_serialize_and_restore_models_properly(): void
    {
        $submission = AssignmentSubmission::factory()->create(['score' => 70.00]);
        $quizResponse = QuizResponse::factory()->create(['score' => 85.00]);
        $enrollment = Enrollment::factory()->create();

        // 1. AssignmentSubmitted serialization
        $submittedEvent = new AssignmentSubmitted($submission);
        $unserializedSubmitted = unserialize(serialize($submittedEvent));
        $this->assertInstanceOf(AssignmentSubmitted::class, $unserializedSubmitted);
        $this->assertEquals($submission->id, $unserializedSubmitted->submission->id);

        // 2. AssignmentGraded serialization and fresh model reload
        $gradedEvent = new AssignmentGraded($submission);
        $submission->update(['score' => 99.00]);
        $unserializedGraded = unserialize(serialize($gradedEvent));
        $this->assertInstanceOf(AssignmentGraded::class, $unserializedGraded);
        $this->assertEquals($submission->id, $unserializedGraded->submission->id);
        $this->assertEquals(99.00, $unserializedGraded->submission->score);

        // 3. QuizCompleted serialization
        $quizEvent = new QuizCompleted($quizResponse);
        $unserializedQuiz = unserialize(serialize($quizEvent));
        $this->assertInstanceOf(QuizCompleted::class, $unserializedQuiz);
        $this->assertEquals($quizResponse->id, $unserializedQuiz->quizResponse->id);

        // 4. StudentEnrolled serialization
        $enrollmentEvent = new StudentEnrolled($enrollment);
        $unserializedEnrollment = unserialize(serialize($enrollmentEvent));
        $this->assertInstanceOf(StudentEnrolled::class, $unserializedEnrollment);
        $this->assertEquals($enrollment->id, $unserializedEnrollment->enrollment->id);
    }

    public function test_queued_notifications_and_listeners_serialize_models_properly(): void
    {
        $submission = AssignmentSubmission::factory()->create([
            'score' => 75.00,
            'feedback' => 'Initial feedback',
        ]);

        // Test notification serialization
        $notification = new AssignmentGradedNotification($submission);
        $serializedNotification = serialize($notification);

        $submission->update(['score' => 90.00, 'feedback' => 'Updated feedback']);

        /** @var AssignmentGradedNotification $unserializedNotification */
        $unserializedNotification = unserialize($serializedNotification);
        $this->assertInstanceOf(AssignmentGradedNotification::class, $unserializedNotification);
        $this->assertEquals($submission->id, $unserializedNotification->submission->id);
        $this->assertEquals(90.00, $unserializedNotification->submission->score);
        $this->assertEquals('Updated feedback', $unserializedNotification->submission->feedback);

        // Test submitted notification serialization
        $submittedNotification = new AssignmentSubmittedNotification($submission);
        $unserializedSubmittedNotification = unserialize(serialize($submittedNotification));
        $this->assertInstanceOf(AssignmentSubmittedNotification::class, $unserializedSubmittedNotification);
        $this->assertEquals($submission->id, $unserializedSubmittedNotification->submission->id);

        // Test queued listeners pushed to queue when queue is faked
        Queue::fake();

        AssignmentGraded::dispatch($submission);

        Queue::assertPushed(CallQueuedListener::class, function (CallQueuedListener $job): bool {
            return $job->class === LogAssessmentAudit::class;
        });

        Queue::assertPushed(CallQueuedListener::class, function (CallQueuedListener $job): bool {
            return $job->class === SendAssignmentNotification::class;
        });
    }

    public function test_send_assignment_notification_handles_missing_instructor_gracefully(): void
    {
        Notification::fake();

        // Course without faculty profile
        $course = Course::factory()->create(['faculty_profile_id' => null]);
        $assignment = Assignment::factory()->create(['course_id' => $course->id]);
        $submission = AssignmentSubmission::factory()->create(['assignment_id' => $assignment->id]);

        $listener = new SendAssignmentNotification;
        $listener->handle(new AssignmentSubmitted($submission));

        Notification::assertNothingSent();
    }

    public function test_assignment_graded_notification_without_feedback(): void
    {
        $student = User::factory()->create();
        $submission = AssignmentSubmission::factory()->create([
            'user_id' => $student->id,
            'feedback' => null,
            'score' => 80.00,
        ]);

        $notification = new AssignmentGradedNotification($submission);
        $mail = $notification->toMail($student);

        $this->assertFalse(collect($mail->introLines)->contains(fn ($line): bool => str_contains($line, 'Feedback:')));
    }

    public function test_assignment_submitted_notification_not_late(): void
    {
        $instructor = User::factory()->create();
        $submission = AssignmentSubmission::factory()->create([
            'is_late' => false,
        ]);

        $notification = new AssignmentSubmittedNotification($submission);
        $mail = $notification->toMail($instructor);

        $this->assertFalse(collect($mail->introLines)->contains(fn ($line): bool => str_contains($line, 'late')));
    }
}
