<?php

namespace Tests\Feature;

use App\DTO\DeletedFileData;
use App\Enums\DeletionReason;
use App\Events\StoredFileDeleted;
use App\Listeners\SendFileDeletedNotification;
use App\Mail\FileDeletedMail;
use Carbon\CarbonImmutable;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FileDeletedNotificationTest extends TestCase
{
    public function test_listener_is_subscribed_to_deletion_event(): void
    {
        Event::fake();

        Event::assertListening(StoredFileDeleted::class, SendFileDeletedNotification::class);
    }

    public function test_deletion_publishes_message_to_rabbitmq_notifications_queue(): void
    {
        Queue::fake()->serializeAndRestore();

        event(new StoredFileDeleted($this->deletedFile()));

        $this->assertSame('rabbitmq', (new SendFileDeletedNotification)->viaConnection());
        Queue::assertPushedOn('notifications', CallQueuedListener::class, fn (CallQueuedListener $job) => $job->class === SendFileDeletedNotification::class
            && $job->data[0]->file->originalName === 'report.pdf');
    }

    public function test_listener_emails_address_from_env(): void
    {
        Mail::fake();
        config(['filestorage.notifications.email' => 'ops@example.com']);

        (new SendFileDeletedNotification)->handle(new StoredFileDeleted($this->deletedFile()));

        Mail::assertSent(FileDeletedMail::class, fn (FileDeletedMail $mail) => $mail->hasTo('ops@example.com')
            && $mail->file->originalName === 'report.pdf');
    }

    public function test_email_describes_file_and_reason(): void
    {
        $mail = new FileDeletedMail($this->deletedFile(DeletionReason::Expired));

        $mail->assertHasSubject('File "report.pdf" deleted (retention period expired)');
        $mail->assertSeeInHtml('report.pdf');
        $mail->assertSeeInHtml('retention period expired');
        $mail->assertSeeInHtml('1.5 MB');
    }

    private function deletedFile(DeletionReason $reason = DeletionReason::Manual): DeletedFileData
    {
        return new DeletedFileData(
            id: 1,
            originalName: 'report.pdf',
            mimeType: 'application/pdf',
            size: 1536 * 1024,
            uploadedAt: CarbonImmutable::now()->subHours(24),
            deletedAt: CarbonImmutable::now(),
            reason: $reason,
        );
    }
}
