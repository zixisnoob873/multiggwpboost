<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use JsonException;

/**
 * @implements CastsAttributes<array<string, mixed>, array<string, mixed>|null>
 */
class EncryptedArray implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $serialized = (string) $value;

        try {
            $serialized = Crypt::decryptString($serialized);
        } catch (DecryptException) {
            // Legacy rows were stored as plaintext JSON. Keep them readable until rewritten.
        }

        $decoded = json_decode($serialized, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            $serialized = json_encode(is_array($value) ? $value : [], JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $serialized = '{}';
        }

        return Crypt::encryptString($serialized);
    }
}
