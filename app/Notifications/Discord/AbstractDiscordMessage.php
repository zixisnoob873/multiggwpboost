<?php

namespace App\Notifications\Discord;

use App\Contracts\Notifications\DiscordMessage;

abstract class AbstractDiscordMessage implements DiscordMessage
{
    final public function embeds(): array
    {
        return [$this->embed()];
    }

    abstract protected function embed(): array;
}
