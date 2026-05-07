<?php

namespace App\Contracts\Notifications;

interface DiscordMessage
{
    public function webhookConfigKey(): string;

    public function username(): string;

    public function embeds(): array;
}
