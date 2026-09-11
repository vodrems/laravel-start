<?php

namespace App\Mail;

use App\DTO\DeletedFileData;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class FileDeletedMail extends Mailable
{
    public function __construct(
        public readonly DeletedFileData $file,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "File \"{$this->file->originalName}\" deleted ({$this->file->reason->label()})",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.file-deleted',
        );
    }
}
