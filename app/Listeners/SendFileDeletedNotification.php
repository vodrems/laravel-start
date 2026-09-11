<?php

namespace App\Listeners;

use App\Events\StoredFileDeleted;
use App\Mail\FileDeletedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Queued listener: dispatching the event publishes a message to RabbitMQ,
 * the queue worker consumes it and sends the email.
 */
final class SendFileDeletedNotification implements ShouldQueue
{
    public int $tries = 3;

    public int $backoff = 10;

    public function viaConnection(): string
    {
        return config('filestorage.notifications.connection');
    }

    public function viaQueue(): string
    {
        return config('filestorage.notifications.queue');
    }

    public function handle(StoredFileDeleted $event): void
    {
        Mail::to(config('filestorage.notifications.email'))
            ->send(new FileDeletedMail($event->file));
    }
}
