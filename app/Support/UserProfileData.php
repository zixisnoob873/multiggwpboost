<?php

namespace App\Support;

class UserProfileData
{
    public static function payload(array $data, string $role, bool $includePassword = true): array
    {
        $payload = [
            'name' => trim("{$data['first_name']} {$data['last_name']}"),
            'first_name' => trim((string) $data['first_name']),
            'last_name' => trim((string) $data['last_name']),
            'nickname' => Nickname::trim($data['nickname'] ?? ''),
            'nickname_normalized' => Nickname::normalized($data['nickname'] ?? ''),
            'email' => strtolower(trim((string) $data['email'])),
            'role' => $role,
        ];

        if (array_key_exists('account_status', $data)) {
            $payload['account_status'] = $data['account_status'];
        }

        if ($includePassword && ! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        return $payload;
    }
}
