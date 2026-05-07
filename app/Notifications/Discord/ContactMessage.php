<?php

namespace App\Notifications\Discord;

class ContactMessage extends AbstractDiscordMessage
{
    public function __construct(protected array $data) {}

    public function webhookConfigKey(): string
    {
        return 'services.discord.webhook_contact';
    }

    public function username(): string
    {
        return 'GGWP Contact Form';
    }

    protected function embed(): array
    {
        return [
            'title' => 'New Contact Form Message',
            'color' => 0xDC3545,
            'fields' => [
                ['name' => 'Name', 'value' => $this->data['name'], 'inline' => true],
                ['name' => 'Email', 'value' => $this->data['email'], 'inline' => true],
                ['name' => 'Order ID', 'value' => $this->data['order_reference'] ?: 'Not provided', 'inline' => true],
                ['name' => 'Message', 'value' => $this->data['message'], 'inline' => false],
            ],
            'footer' => ['text' => 'GGWP Boost'],
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
