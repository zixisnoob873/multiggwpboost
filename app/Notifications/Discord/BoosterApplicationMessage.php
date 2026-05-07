<?php

namespace App\Notifications\Discord;

class BoosterApplicationMessage extends AbstractDiscordMessage
{
    public function __construct(protected array $data) {}

    public function webhookConfigKey(): string
    {
        return 'services.discord.webhook_booster_applications';
    }

    public function username(): string
    {
        return 'GGWP Booster Applications';
    }

    protected function embed(): array
    {
        return [
            'title' => 'New Booster Application',
            'color' => 0x5865F2,
            'fields' => [
                ['name' => 'Name', 'value' => $this->data['name'], 'inline' => true],
                ['name' => 'Email', 'value' => $this->data['email'], 'inline' => true],
                ['name' => 'Current Rank', 'value' => $this->data['current_rank'], 'inline' => true],
                ['name' => 'Peak Rank', 'value' => $this->data['peak_rank'], 'inline' => true],
                ['name' => 'Avg. Time A1 to I1', 'value' => $this->data['average_time'], 'inline' => false],
                ['name' => 'Discord', 'value' => $this->data['discord'], 'inline' => true],
                ['name' => 'Main Account Tracker', 'value' => $this->data['main_account_tracker'], 'inline' => false],
                ['name' => 'Marketplace Profile', 'value' => $this->data['marketplace_profile'] ?: 'Not provided', 'inline' => false],
                ['name' => 'Regions', 'value' => implode(', ', $this->data['regions']), 'inline' => false],
            ],
            'footer' => ['text' => 'Developed by 2Thoughts'],
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
