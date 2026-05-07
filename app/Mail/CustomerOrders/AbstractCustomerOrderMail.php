<?php

namespace App\Mail\CustomerOrders;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

abstract class AbstractCustomerOrderMail extends Mailable
{
    public function __construct(public array $payload) {}

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

    protected function orderReference(): string
    {
        return (string) data_get($this->payload, 'order.number', data_get($this->payload, 'order.id', ''));
    }
}
