<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\CompleteOAuthProfileRequest;
use App\Models\OAuthAccount;
use App\Models\User;
use App\Services\Mail\AccountLifecycleEmailNotifier;
use App\Support\Logging\AppEventLogger;
use App\Support\Nickname;
use App\Support\UserProfileData;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Throwable;

class OAuthWebController extends Controller
{
    protected const PENDING_PROFILE_SESSION_KEY = 'oauth.pending_profile';

    protected const EMAIL_ALREADY_EXISTS_MESSAGE = 'An account with this email already exists. Sign in with your password first, then connect this provider from account settings.';

    protected const SUPPORTED_PROVIDERS = [
        'google' => 'Google',
        'discord' => 'Discord',
    ];

    public function __construct(
        protected SocialiteFactory $socialite,
        protected AppEventLogger $eventLogger,
        protected AccountLifecycleEmailNotifier $accountLifecycleEmailNotifier,
    ) {}

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $provider = $this->normalizeProvider($provider);

        if (! $this->providerIsConfigured($provider)) {
            return redirect()
                ->route('login')
                ->withErrors(['oauth' => $this->providerLabel($provider).' login is not configured yet.']);
        }

        $request->session()->forget(self::PENDING_PROFILE_SESSION_KEY);

        return $this->providerDriver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $provider = $this->normalizeProvider($provider);

        if ($request->filled('error')) {
            $this->eventLogger->security('auth.oauth_denied', $request, [
                'provider' => $provider,
                'error' => $request->input('error'),
            ], 'info');

            return $this->redirectWithOAuthError($this->providerLabel($provider).' login was cancelled.');
        }

        try {
            $providerUser = $this->providerDriver($provider)->user();
        } catch (Throwable $exception) {
            $this->eventLogger->security('auth.oauth_callback_failed', $request, [
                'provider' => $provider,
                'exception' => $exception,
            ]);

            return $this->redirectWithOAuthError($this->providerLabel($provider).' login failed. Please try again.');
        }

        $profile = $this->profileFromProviderUser($provider, $providerUser);

        if ($profile['provider_user_id'] === '') {
            return $this->redirectWithOAuthError($this->providerLabel($provider).' did not return an account ID.');
        }

        if (! $this->profileHasVerifiedEmail($profile)) {
            $this->logUnverifiedProviderEmail($request, $profile);

            return $this->redirectWithOAuthError($this->providerLabel($provider).' did not return a verified email address.');
        }

        $linkedAccount = OAuthAccount::query()
            ->with('user')
            ->where('provider', $provider)
            ->where('provider_user_id', $profile['provider_user_id'])
            ->first();

        if ($linkedAccount instanceof OAuthAccount) {
            return $this->loginLinkedAccount($request, $linkedAccount, $profile);
        }

        $email = (string) $profile['email'];

        if ($email !== '') {
            $existingUser = User::query()->where('email', $email)->first();

            if ($existingUser instanceof User) {
                return $this->blockExistingUserEmailLink($request, $existingUser, $profile);
            }
        }

        if ($this->profileHasRequiredFields($profile)) {
            return $this->createAndLoginOAuthUser($request, $profile);
        }

        $request->session()->put(self::PENDING_PROFILE_SESSION_KEY, $profile);

        $redirect = redirect()->route('oauth.complete-profile');

        return $redirect;
    }

    public function linkRedirect(Request $request, string $provider): RedirectResponse
    {
        $provider = $this->normalizeProvider($provider);

        if (! $this->providerIsConfigured($provider)) {
            return redirect()
                ->to($this->redirectPathFor($request->user()))
                ->withErrors(['oauth' => $this->providerLabel($provider).' login is not configured yet.']);
        }

        $request->session()->forget(self::PENDING_PROFILE_SESSION_KEY);

        return $this->providerDriver($provider, route('oauth.link.callback', ['provider' => $provider]))->redirect();
    }

    public function linkCallback(Request $request, string $provider): RedirectResponse
    {
        $provider = $this->normalizeProvider($provider);
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if ($request->filled('error')) {
            $this->eventLogger->security('auth.oauth_link_denied', $request, [
                'provider' => $provider,
                'error' => $request->input('error'),
            ], 'info');

            return $this->redirectWithAccountOAuthError($request, $this->providerLabel($provider).' linking was cancelled.');
        }

        try {
            $providerUser = $this->providerDriver($provider, route('oauth.link.callback', ['provider' => $provider]))->user();
        } catch (Throwable $exception) {
            $this->eventLogger->security('auth.oauth_link_callback_failed', $request, [
                'provider' => $provider,
                'exception' => $exception,
            ]);

            return $this->redirectWithAccountOAuthError($request, $this->providerLabel($provider).' linking failed. Please try again.');
        }

        $profile = $this->profileFromProviderUser($provider, $providerUser);

        if ($profile['provider_user_id'] === '') {
            return $this->redirectWithAccountOAuthError($request, $this->providerLabel($provider).' did not return an account ID.');
        }

        if (! $this->profileHasVerifiedEmail($profile)) {
            $this->logUnverifiedProviderEmail($request, $profile);

            return $this->redirectWithAccountOAuthError($request, $this->providerLabel($provider).' did not return a verified email address.');
        }

        if (! hash_equals((string) $user->email, (string) $profile['email'])) {
            $this->eventLogger->security('auth.oauth_link_email_mismatch', $request, [
                'provider' => $profile['provider'],
                'provider_user_id_hash' => $this->providerUserIdHash($profile),
                'email_hash' => $this->emailFingerprint($profile['email'] ?? null),
                'actor_email_hash' => $this->emailFingerprint($user->email),
            ]);

            return $this->redirectWithAccountOAuthError($request, 'This '.$this->providerLabel($provider).' email does not match your account email.');
        }

        $linkedAccount = OAuthAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $profile['provider_user_id'])
            ->first();

        if ($linkedAccount instanceof OAuthAccount && (int) $linkedAccount->user_id !== (int) $user->getKey()) {
            return $this->redirectWithAccountOAuthError($request, 'This '.$this->providerLabel($provider).' account is already linked to another user.');
        }

        $existingProviderLink = OAuthAccount::query()
            ->where('user_id', $user->getKey())
            ->where('provider', $provider)
            ->first();

        if ($existingProviderLink instanceof OAuthAccount
            && $existingProviderLink->provider_user_id !== $profile['provider_user_id']) {
            return $this->redirectWithAccountOAuthError($request, 'This account is already linked to a different '.$this->providerLabel($provider).' login.');
        }

        $linkedAccount = DB::transaction(function () use ($user, $profile): OAuthAccount {
            $this->applyProviderEmailVerification($user, $profile);

            return $this->linkOAuthAccount($user, $profile);
        });

        $this->eventLogger->auth('auth.oauth_linked_by_authenticated_user', $request, $user, [
            'provider' => $profile['provider'],
            'linked_account_id' => $linkedAccount->getKey(),
        ]);

        return redirect()
            ->to($this->redirectPathFor($user))
            ->with('status', $this->providerLabel($provider).' has been connected to your account.');
    }

    public function showCompleteProfile(Request $request): View|RedirectResponse
    {
        $profile = (array) $request->session()->get(self::PENDING_PROFILE_SESSION_KEY, []);

        if ($profile === []) {
            return redirect()
                ->route('login')
                ->withErrors(['oauth' => 'Start with Google or Discord to finish an OAuth signup.']);
        }

        return view('auth.complete-profile', [
            'profile' => $profile,
            'emailLocked' => trim((string) ($profile['email'] ?? '')) !== '',
            'seo' => $this->authSeo(
                'Complete Profile',
                'Finish your GGWP-Boost account after signing in with Google or Discord.'
            ),
        ]);
    }

    public function completeProfile(CompleteOAuthProfileRequest $request): RedirectResponse
    {
        $profile = (array) $request->session()->get(self::PENDING_PROFILE_SESSION_KEY, []);

        if ($profile === []) {
            return redirect()
                ->route('login')
                ->withErrors(['oauth' => 'Start with Google or Discord to finish an OAuth signup.']);
        }

        $data = $request->validated();
        $profile = array_merge($profile, [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'nickname' => $data['nickname'],
        ]);

        if (! $this->profileHasVerifiedEmail($profile)) {
            $request->session()->forget(self::PENDING_PROFILE_SESSION_KEY);
            $this->logUnverifiedProviderEmail($request, $profile);

            return $this->redirectWithOAuthError($this->providerLabel($profile['provider']).' did not return a verified email address.');
        }

        $alreadyLinked = OAuthAccount::query()
            ->where('provider', $profile['provider'])
            ->where('provider_user_id', $profile['provider_user_id'])
            ->exists();

        if ($alreadyLinked) {
            $request->session()->forget(self::PENDING_PROFILE_SESSION_KEY);

            return $this->redirectWithOAuthError('This '.$this->providerLabel($profile['provider']).' account is already linked to another user.');
        }

        try {
            $user = DB::transaction(function () use ($profile): User {
                $user = new User;
                $user->forceFill(UserProfileData::payload($profile, User::ROLE_CUSTOMER, includePassword: false))->save();
                $this->applyProviderEmailVerification($user, $profile);
                $this->linkOAuthAccount($user, $profile);

                return $user;
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                return back()
                    ->withErrors(['nickname' => 'This nickname has already been taken.'])
                    ->withInput($request->except('email'));
            }

            throw $exception;
        }

        $this->accountLifecycleEmailNotifier->queueAccountCreated($user, 'oauth-'.$profile['provider']);
        $request->session()->forget(self::PENDING_PROFILE_SESSION_KEY);

        return $this->loginAndRedirect($request, $user, 'auth.oauth_registered', [
            'provider' => $profile['provider'],
        ])->with('status', 'Your account is ready.');
    }

    protected function loginLinkedAccount(Request $request, OAuthAccount $linkedAccount, array $profile): RedirectResponse
    {
        $user = $linkedAccount->user;

        if (! $user instanceof User) {
            return $this->redirectWithOAuthError('This '.$this->providerLabel($profile['provider']).' account is linked to a missing user.');
        }

        $email = (string) ($profile['email'] ?? '');
        $emailOwner = $email !== ''
            ? User::query()->where('email', $email)->whereKeyNot($user->getKey())->first()
            : null;

        if ($emailOwner instanceof User) {
            return $this->redirectWithOAuthError('This '.$this->providerLabel($profile['provider']).' account is already linked to another user.');
        }

        if ($user->isSuspended()) {
            return $this->redirectWithOAuthError('Your account is suspended. Please contact support.');
        }

        $this->applyProviderEmailVerification($user, $profile);
        $this->updateOAuthAccount($linkedAccount, $profile);

        return $this->loginAndRedirect($request, $user, 'auth.oauth_login_succeeded', [
            'provider' => $profile['provider'],
            'linked_account_id' => $linkedAccount->getKey(),
        ]);
    }

    protected function blockExistingUserEmailLink(Request $request, User $user, array $profile): RedirectResponse
    {
        $this->eventLogger->security('auth.oauth_email_only_link_blocked', $request, [
            'provider' => $profile['provider'],
            'provider_user_id_hash' => $this->providerUserIdHash($profile),
            'email_hash' => $this->emailFingerprint($profile['email'] ?? null),
            'matched_user_id' => $user->getKey(),
        ]);

        return $this->redirectWithOAuthError(self::EMAIL_ALREADY_EXISTS_MESSAGE);
    }

    protected function createAndLoginOAuthUser(Request $request, array $profile): RedirectResponse
    {
        $user = DB::transaction(function () use ($profile): User {
            $user = new User;
            $user->forceFill(UserProfileData::payload($profile, User::ROLE_CUSTOMER, includePassword: false))->save();
            $this->applyProviderEmailVerification($user, $profile);
            $this->linkOAuthAccount($user, $profile);

            return $user;
        });

        $this->accountLifecycleEmailNotifier->queueAccountCreated($user, 'oauth-'.$profile['provider']);

        return $this->loginAndRedirect($request, $user, 'auth.oauth_registered', [
            'provider' => $profile['provider'],
        ])->with('status', 'Your account is ready.');
    }

    protected function providerDriver(string $provider, ?string $redirectUrl = null): AbstractProvider
    {
        /** @var AbstractProvider $driver */
        $driver = $this->socialite->driver($provider);

        $driver->setScopes($this->scopesFor($provider));

        if ($redirectUrl !== null && method_exists($driver, 'redirectUrl')) {
            $driver->redirectUrl($redirectUrl);
        }

        return $driver;
    }

    protected function scopesFor(string $provider): array
    {
        return match ($provider) {
            'google' => ['email', 'profile'],
            'discord' => ['identify', 'email'],
            default => [],
        };
    }

    protected function profileFromProviderUser(string $provider, SocialiteUser $user): array
    {
        $raw = $user->getRaw();
        $email = strtolower(trim((string) $user->getEmail()));
        $name = trim((string) $user->getName());
        $providerNickname = trim((string) $user->getNickname());

        $profile = [
            'provider' => $provider,
            'provider_label' => $this->providerLabel($provider),
            'provider_user_id' => trim((string) $user->getId()),
            'email' => $email,
            'email_verified' => $this->providerEmailIsVerified($provider, $raw),
            'name' => $name,
            'first_name' => '',
            'last_name' => '',
            'nickname' => Nickname::isValid($providerNickname) ? $providerNickname : '',
            'suggested_nickname' => '',
            'avatar_url' => trim((string) $user->getAvatar()) ?: null,
            'raw_name' => $name,
        ];

        if ($provider === 'google') {
            $profile['first_name'] = trim((string) Arr::get($raw, 'given_name'));
            $profile['last_name'] = trim((string) Arr::get($raw, 'family_name'));
        }

        $profile['suggested_nickname'] = $this->suggestNickname($provider, $profile, $raw);

        return $profile;
    }

    protected function providerEmailIsVerified(string $provider, array $raw): bool
    {
        return match ($provider) {
            'google' => (bool) Arr::get($raw, 'email_verified', false),
            'discord' => (bool) Arr::get($raw, 'verified', false),
            default => false,
        };
    }

    protected function profileHasVerifiedEmail(array $profile): bool
    {
        return trim((string) ($profile['email'] ?? '')) !== ''
            && (bool) ($profile['email_verified'] ?? false);
    }

    protected function logUnverifiedProviderEmail(Request $request, array $profile): void
    {
        $this->eventLogger->security('auth.oauth_unverified_email_blocked', $request, [
            'provider' => $profile['provider'] ?? null,
            'provider_user_id_hash' => $this->providerUserIdHash($profile),
            'email_hash' => $this->emailFingerprint($profile['email'] ?? null),
        ]);
    }

    protected function suggestNickname(string $provider, array $profile, array $raw): string
    {
        $emailPrefix = Str::before((string) ($profile['email'] ?? ''), '@');

        $sources = $provider === 'google'
            ? [
                (string) ($profile['name'] ?? ''),
                $emailPrefix,
                (string) ($profile['nickname'] ?? ''),
            ]
            : [
                (string) Arr::get($raw, 'global_name', ''),
                (string) Arr::get($raw, 'username', ''),
                $emailPrefix,
            ];

        foreach ($sources as $source) {
            $candidate = $this->firstAvailableNickname($source);

            if ($candidate !== null) {
                return $candidate;
            }
        }

        return '';
    }

    protected function firstAvailableNickname(string $source): ?string
    {
        $base = substr(preg_replace('/[^A-Za-z0-9]+/', '', $source) ?? '', 0, Nickname::MAX_LENGTH);

        if ($base === '') {
            return null;
        }

        if (! User::query()->where('nickname_normalized', Nickname::normalized($base))->exists()) {
            return $base;
        }

        for ($suffix = 2; $suffix <= 999; $suffix++) {
            $suffixText = (string) $suffix;
            $candidate = substr($base, 0, max(1, Nickname::MAX_LENGTH - strlen($suffixText))).$suffixText;

            if (! User::query()->where('nickname_normalized', Nickname::normalized($candidate))->exists()) {
                return $candidate;
            }
        }

        return null;
    }

    protected function profileHasRequiredFields(array $profile): bool
    {
        return trim((string) ($profile['first_name'] ?? '')) !== ''
            && trim((string) ($profile['last_name'] ?? '')) !== ''
            && trim((string) ($profile['email'] ?? '')) !== ''
            && Nickname::isValid($profile['nickname'] ?? '')
            && ! User::query()->where('nickname_normalized', Nickname::normalized($profile['nickname']))->exists();
    }

    protected function linkOAuthAccount(User $user, array $profile): OAuthAccount
    {
        return OAuthAccount::query()->updateOrCreate(
            [
                'provider' => $profile['provider'],
                'provider_user_id' => $profile['provider_user_id'],
            ],
            [
                'user_id' => $user->getKey(),
                'email' => $profile['email'] ?: null,
                'name' => $profile['name'] ?: null,
                'nickname' => $profile['nickname'] ?: null,
                'avatar_url' => $profile['avatar_url'] ?: null,
                'email_verified_at' => $profile['email_verified'] ? now() : null,
                'last_login_at' => now(),
            ]
        );
    }

    protected function updateOAuthAccount(OAuthAccount $account, array $profile): void
    {
        $account->forceFill([
            'email' => $profile['email'] ?: null,
            'name' => $profile['name'] ?: null,
            'nickname' => $profile['nickname'] ?: null,
            'avatar_url' => $profile['avatar_url'] ?: null,
            'email_verified_at' => $profile['email_verified'] ? now() : $account->email_verified_at,
            'last_login_at' => now(),
        ])->save();
    }

    protected function applyProviderEmailVerification(User $user, array $profile): void
    {
        if (! (bool) ($profile['email_verified'] ?? false) || $user->email_verified_at !== null) {
            return;
        }

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();
    }

    protected function loginAndRedirect(Request $request, User $user, string $event, array $context = []): RedirectResponse
    {
        $this->applySecureCookieSettings();

        Auth::login($user);
        $request->session()->regenerate();
        $this->eventLogger->auth($event, $request, $user, $context);

        return redirect()->intended($this->redirectPathFor($user));
    }

    protected function redirectWithOAuthError(string $message): RedirectResponse
    {
        return redirect()
            ->route('login')
            ->withErrors(['oauth' => $message]);
    }

    protected function redirectWithAccountOAuthError(Request $request, string $message): RedirectResponse
    {
        return redirect()
            ->to($this->redirectPathFor($request->user()))
            ->withErrors(['oauth' => $message]);
    }

    protected function normalizeProvider(string $provider): string
    {
        $provider = Str::lower(trim($provider));

        abort_unless(array_key_exists($provider, self::SUPPORTED_PROVIDERS), 404);

        return $provider;
    }

    protected function providerLabel(string $provider): string
    {
        return self::SUPPORTED_PROVIDERS[$provider] ?? Str::title($provider);
    }

    protected function providerIsConfigured(string $provider): bool
    {
        $config = (array) config("services.{$provider}", []);

        return filled($config['client_id'] ?? null)
            && filled($config['client_secret'] ?? null)
            && filled($config['redirect'] ?? null);
    }

    protected function redirectPathFor(?User $user): string
    {
        if (! $user) {
            return route('customer-dashboard');
        }

        if ($user->isAdminUser()) {
            return route('admin-dashboard');
        }

        return User::normalizeRole($user->role) === User::ROLE_BOOSTER
            ? route('booster-dashboard')
            : route('customer-dashboard');
    }

    protected function applySecureCookieSettings(): void
    {
        config([
            'session.secure' => (bool) config('session.secure'),
            'session.http_only' => true,
            'session.same_site' => 'lax',
        ]);
    }

    protected function providerUserIdHash(array $profile): ?string
    {
        $providerUserId = trim((string) ($profile['provider_user_id'] ?? ''));

        return $providerUserId !== '' ? substr(hash('sha256', $providerUserId), 0, 16) : null;
    }

    protected function emailFingerprint(?string $email): ?string
    {
        $normalized = strtolower(trim((string) $email));

        return $normalized !== '' ? substr(hash('sha256', $normalized), 0, 16) : null;
    }

    protected function authSeo(string $title, string $description): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'canonical' => url()->current(),
            'robots' => 'noindex,follow',
            'type' => 'website',
        ];
    }

    protected function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return Str::contains($exception->getMessage(), [
            'UNIQUE constraint failed',
            'Integrity constraint violation',
            'Duplicate entry',
        ]);
    }
}
