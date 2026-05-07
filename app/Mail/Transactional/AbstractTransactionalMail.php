<?php

namespace App\Mail\Transactional;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

abstract class AbstractTransactionalMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public array $payload)
    {
        $this->onQueue((string) config('mail.transactional_queue', 'notifications'));
        $this->afterCommit();
    }

    abstract protected function subjectLine(): string;

    abstract protected function viewName(): string;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: $this->viewName(),
            with: [
                'payload' => $this->payload,
            ],
        );
    }
}
