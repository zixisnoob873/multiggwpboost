<?php

namespace App\Http\Requests\Concerns;

use App\Support\Nickname;

trait HandlesUserNickname
{
    protected function normalizeNicknameInput(): void
    {
        $nickname = Nickname::trim($this->input('nickname'));

        $this->merge([
            'nickname' => $nickname,
            'nickname_normalized' => Nickname::normalized($nickname),
        ]);
    }

    protected function nicknameRules(?int $ignoreUserId = null): array
    {
        return Nickname::validationRules($ignoreUserId);
    }

    protected function nicknameMessages(): array
    {
        return Nickname::validationMessages();
    }
}
