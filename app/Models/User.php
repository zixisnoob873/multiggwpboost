<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Mail\Transactional\PasswordResetMail;
use App\Services\Mail\TransactionalMailDispatcher;
use App\Support\Nickname;
use App\Support\Security\StoredFilePath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_CUSTOMER = 'customer';

    public const ROLE_BOOSTER = 'booster';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'first_name',
        'last_name',
        'nickname',
        'nickname_normalized',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            $user->first_name = trim((string) $user->first_name);
            $user->last_name = trim((string) $user->last_name);
            $user->email = strtolower(trim((string) $user->email));
            $user->nickname = Nickname::trim($user->nickname);
            $user->nickname_normalized = Nickname::normalized($user->nickname);
            $user->role = self::normalizeRole($user->role);
        });
    }

    public static function normalizeRole(mixed $role): string
    {
        $normalized = Str::lower(str_replace('-', '_', trim((string) $role)));

        if ($normalized === '') {
            return '';
        }

        if (in_array($normalized, [self::ROLE_CUSTOMER, self::ROLE_BOOSTER], true)) {
            return $normalized;
        }

        if (
            $normalized === self::ROLE_SUPER_ADMIN
            || in_array($normalized, ['admin', 'manager'], true)
        ) {
            return self::ROLE_SUPER_ADMIN;
        }

        return $normalized;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function oauthAccounts(): HasMany
    {
        return $this->hasMany(OAuthAccount::class);
    }

    public function adminAuditLogs(): HasMany
    {
        return $this->hasMany(AdminAuditLog::class, 'actor_id');
    }

    public function boosterOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'booster_id');
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class, 'booster_id');
    }

    public function walletAdjustments(): HasMany
    {
        return $this->hasMany(BoosterWalletAdjustment::class, 'booster_id');
    }

    public function isSuspended(): bool
    {
        return $this->account_status === 'suspended';
    }

    public function isAdminUser(): bool
    {
        return self::normalizeRole($this->role) === self::ROLE_SUPER_ADMIN;
    }

    public function adminRole(): ?string
    {
        return $this->isAdminUser() ? self::ROLE_SUPER_ADMIN : null;
    }

    public function adminRoleLabel(): ?string
    {
        return $this->isAdminUser() ? 'Super Admin' : null;
    }

    public function canAccessAdminModule(string $module): bool
    {
        if (! $this->isAdminUser()) {
            return false;
        }

        return array_key_exists($module, (array) config('admin.modules', []));
    }

    public function fullIdentity(string $fallback = 'User'): string
    {
        $fullName = trim((string) ($this->name ?? ''));

        if ($fullName !== '') {
            return $fullName;
        }

        $parts = array_filter([
            trim((string) ($this->first_name ?? '')),
            trim((string) ($this->last_name ?? '')),
        ]);

        if ($parts !== []) {
            return implode(' ', $parts);
        }

        $email = trim((string) ($this->email ?? ''));

        return $email !== '' ? $email : $fallback;
    }

    public function publicIdentity(string $fallback = 'User'): string
    {
        $nickname = trim((string) ($this->nickname ?? ''));

        return $nickname !== '' ? $nickname : $this->fullIdentity($fallback);
    }

    public function getFullNameAttribute(): string
    {
        return $this->fullIdentity();
    }

    public function getPublicNameAttribute(): string
    {
        return $this->publicIdentity();
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        if (! $this->profile_photo_path) {
            return null;
        }

        $path = StoredFilePath::clean($this->profile_photo_path, [
            'uploads/profile-photos/',
            'profile-photos/',
        ]);

        if ($path === null) {
            return null;
        }

        if (Str::startsWith($path, 'uploads/profile-photos/')) {
            if (Storage::disk('private')->exists($path)) {
                return URL::temporarySignedRoute('profile-photos.show', now()->addMinutes(30), [
                    'user' => $this,
                    'v' => sha1($path),
                ]);
            }

            $legacyPath = public_path($path);

            return is_file($legacyPath)
                ? asset(ltrim($path, '/'))
                : null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return URL::temporarySignedRoute('profile-photos.show', now()->addMinutes(30), [
            'user' => $this,
            'v' => sha1($path),
        ]);
    }

    public function sendPasswordResetNotification($token): void
    {
        app(TransactionalMailDispatcher::class)->queue(
            $this->getEmailForPasswordReset(),
            new PasswordResetMail([
                'user' => [
                    'id' => $this->getKey(),
                    'name' => $this->fullIdentity('User'),
                    'email' => $this->getEmailForPasswordReset(),
                ],
                'reset' => [
                    'token' => $token,
                    'url' => route('password.reset', [
                        'token' => $token,
                        'email' => $this->getEmailForPasswordReset(),
                    ]),
                    'expires_in_minutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords', 'users').'.expire', 60),
                ],
                'links' => [
                    'login_url' => route('login'),
                    'support_url' => route('contact'),
                    'support_email' => trim((string) (config('footer.support.email') ?? config('mail.from.address') ?? '')) ?: null,
                ],
                'branding' => $this->mailBrandingPayload(),
            ]),
            $this->fullIdentity('User'),
        );
    }

    protected function mailBrandingPayload(): array
    {
        $branding = [
            'app_name' => config('app.name', 'GGWP Boost'),
        ];

        $logoUrl = trim((string) config('mail.logo_url', ''));

        if ($logoUrl !== '') {
            $branding['logo_url'] = $logoUrl;
        }

        return $branding;
    }
}
