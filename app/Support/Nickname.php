<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

class Nickname
{
    public const MAX_LENGTH = 25;

    public const REGEX = '/^[A-Za-z0-9]+$/';

    public static function trim(mixed $value): string
    {
        return trim((string) $value);
    }

    public static function normalized(mixed $value): ?string
    {
        $nickname = self::trim($value);

        return $nickname !== '' ? Str::lower($nickname) : null;
    }

    public static function isValid(mixed $value): bool
    {
        $nickname = self::trim($value);

        if ($nickname === '' || Str::length($nickname) > self::MAX_LENGTH) {
            return false;
        }

        return preg_match(self::REGEX, $nickname) === 1;
    }

    public static function validationRules(?int $ignoreUserId = null): array
    {
        return [
            'nickname' => [
                'required',
                'string',
                'max:'.self::MAX_LENGTH,
                'regex:'.self::REGEX,
                function (string $attribute, mixed $value, \Closure $fail) use ($ignoreUserId): void {
                    $normalized = self::normalized($value);

                    if ($normalized === null) {
                        return;
                    }

                    $query = User::query()->where('nickname_normalized', $normalized);

                    if ($ignoreUserId !== null) {
                        $query->whereKeyNot($ignoreUserId);
                    }

                    if ($query->exists()) {
                        $fail('This nickname has already been taken.');
                    }
                },
            ],
        ];
    }

    public static function validationMessages(): array
    {
        return [
            'nickname.required' => 'A nickname is required.',
            'nickname.max' => 'The nickname may not be greater than '.self::MAX_LENGTH.' characters.',
            'nickname.regex' => 'The nickname may only contain letters and numbers.',
        ];
    }
}
