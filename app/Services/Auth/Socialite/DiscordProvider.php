<?php

namespace App\Services\Auth\Socialite;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

class DiscordProvider extends AbstractProvider implements ProviderInterface
{
    protected $scopeSeparator = ' ';

    protected $scopes = [
        'identify',
        'email',
    ];

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase('https://discord.com/api/oauth2/authorize', $state);
    }

    protected function getTokenUrl(): string
    {
        return 'https://discord.com/api/oauth2/token';
    }

    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get('https://discord.com/api/users/@me', [
            RequestOptions::HEADERS => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    protected function mapUserToObject(array $user): User
    {
        $displayName = Arr::get($user, 'global_name') ?: Arr::get($user, 'username');

        return (new User)->setRaw($user)->map([
            'id' => (string) Arr::get($user, 'id'),
            'nickname' => Arr::get($user, 'username'),
            'name' => $displayName,
            'email' => Arr::get($user, 'email'),
            'avatar' => $this->avatarUrl($user),
        ]);
    }

    protected function avatarUrl(array $user): ?string
    {
        $id = (string) Arr::get($user, 'id');
        $avatar = (string) Arr::get($user, 'avatar');

        if ($id === '' || $avatar === '') {
            return null;
        }

        $extension = str_starts_with($avatar, 'a_') ? 'gif' : 'png';

        return "https://cdn.discordapp.com/avatars/{$id}/{$avatar}.{$extension}?size=256";
    }
}
