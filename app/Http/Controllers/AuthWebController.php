<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Support\UserProfileData;
use App\Support\Logging\AppEventLogger;
use App\Services\Auth\LoginCaptchaService;
use App\Services\Mail\AccountLifecycleEmailNotifier;
use App\Services\Security\ProfilePhotoStorageService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\PasswordReset;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AuthWebController extends Controller
{
    public function __construct(
        protected ProfilePhotoStorageService $profilePhotoStorageService,
        protected LoginCaptchaService $loginCaptchaService,
        protected AppEventLogger $eventLogger,
        protected AccountLifecycleEmailNotifier $accountLifecycleEmailNotifier,
    ) {}

    public function showLogin(Request $request): \Illuminate\View\View
    {
        return view('login', [
            'loginCaptcha' => $this->loginCaptchaService->viewData($request, old('email')),
            'seo' => $this->authSeo(
                'Login',
                'Sign in to track your VALORANT boost, manage order details, and contact support from your dashboard.'
            ),
        ]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $this->applySecureCookieSettings();
        $this->ensureIsNotRateLimited($request);

        $credentials = $request->validated();
        $captchaError = $this->loginCaptchaService->ensureValidCaptcha(
            $request,
            $credentials['email'],
            $request->input('captcha')
        );

        if ($captchaError !== null) {
            RateLimiter::hit($this->throttleKey($request), $this->lockoutDecaySeconds());
            $this->loginCaptchaService->recordFailure($request, $credentials['email']);
            $this->eventLogger->security('auth.login_captcha_failed', $request, [
                'email_hash' => $this->emailFingerprint($credentials['email']),
            ]);

            return back()
                ->withErrors(['captcha' => $captchaError])
                ->withInput($request->only('email'));
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request), $this->lockoutDecaySeconds());
            $this->loginCaptchaService->recordFailure($request, $credentials['email']);
            $this->eventLogger->security('auth.login_failed', $request, [
                'email_hash' => $this->emailFingerprint($credentials['email']),
            ], 'info');

            return back()
                ->withErrors(['email' => 'Invalid credentials.'])
                ->withInput($request->only('email'));
        }

        $authenticatedUser = Auth::user();

        if (! $authenticatedUser instanceof User) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey($request), $this->lockoutDecaySeconds());

            return back()
                ->withErrors(['email' => 'Invalid credentials.'])
                ->withInput($request->only('email'));
        }

        if ($authenticatedUser->isSuspended()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            RateLimiter::hit($this->throttleKey($request), $this->lockoutDecaySeconds());
            $this->loginCaptchaService->recordFailure($request, $credentials['email']);
            $this->eventLogger->security('auth.login_blocked_suspended', $request, [
                'actor_id' => $authenticatedUser->getKey(),
                'actor_role' => $authenticatedUser->role,
                'email_hash' => $this->emailFingerprint($credentials['email']),
            ]);

            return back()
                ->withErrors(['email' => 'Invalid credentials.'])
                ->withInput($request->only('email'));
        }

        if ($this->requiresVerifiedEmail($authenticatedUser) && $authenticatedUser->email_verified_at === null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            RateLimiter::hit($this->throttleKey($request), $this->lockoutDecaySeconds());
            $this->loginCaptchaService->recordFailure($request, $credentials['email']);
            $this->eventLogger->security('auth.login_blocked_unverified', $request, [
                'actor_id' => $authenticatedUser->getKey(),
                'actor_role' => $authenticatedUser->role,
                'email_hash' => $this->emailFingerprint($credentials['email']),
            ]);

            return back()
                ->withErrors(['email' => 'Please verify your email address before logging in.'])
                ->withInput($request->only('email'));
        }

        RateLimiter::clear($this->throttleKey($request));
        $this->loginCaptchaService->clear($request);
        $request->session()->regenerate();
        $this->eventLogger->auth('auth.login_succeeded', $request, $authenticatedUser, [
            'remember' => $request->boolean('remember'),
        ]);

        return redirect()->intended($this->redirectPathFor($authenticatedUser));
    }

    public function showSignup(): \Illuminate\View\View
    {
        return view('signup', [
            'seo' => $this->authSeo(
                'Create Account',
                'Create a GGWP-Boost account to order VALORANT rank boosting, save contact details, and track your boost securely.'
            ),
        ]);
    }

    public function showForgotPassword(): \Illuminate\View\View
    {
        return view('auth.forgot-password', [
            'seo' => $this->authSeo(
                'Reset Password',
                'Request a secure password reset link for your GGWP-Boost account.'
            ),
        ]);
    }

    public function sendPasswordResetLink(ForgotPasswordRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Password::sendResetLink([
            'email' => $data['email'],
        ]);

        $this->eventLogger->security('auth.password_reset_requested', $request, [
            'email_hash' => $this->emailFingerprint($data['email']),
        ], 'info');

        return back()->with('status', 'If an account with that email exists, we have emailed a password reset link.');
    }

    public function showResetPassword(Request $request, string $token): RedirectResponse|\Illuminate\View\View
    {
        $email = Str::lower(trim((string) $request->query('email')));

        if ($email === '' || ! $this->resetTokenIsValid($email, $token)) {
            return redirect()
                ->route('password.request')
                ->withErrors(['email' => 'This password reset link is invalid or has expired.']);
        }

        return view('auth.reset-password', [
            'email' => $email,
            'token' => $token,
            'seo' => $this->authSeo(
                'Choose a New Password',
                'Choose a new password for your GGWP-Boost account.'
            ),
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $status = Password::reset(
            [
                'email' => $data['email'],
                'password' => $data['password'],
                'token' => $data['token'],
            ],
            function (User $user, string $password) use ($request): void {
                $this->applySecureCookieSettings();

                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                Auth::login($user);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return redirect()
                ->route('password.request')
                ->withErrors(['email' => 'This password reset link is invalid or has expired.']);
        }

        $request->session()->regenerate();
        $this->eventLogger->auth('auth.password_reset_succeeded', $request, $request->user(), [
            'email_hash' => $this->emailFingerprint($data['email']),
        ]);

        return redirect()->intended($this->redirectPathFor($request->user()))
            ->with('status', 'Your password has been reset.');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $this->applySecureCookieSettings();
        $data = $request->validated();

        $user = new User;
        $user->forceFill(UserProfileData::payload($data, 'customer'))->save();
        $this->accountLifecycleEmailNotifier->queueAccountCreated($user, 'self-service');

        Auth::login($user);

        $request->session()->regenerate();
        $this->eventLogger->auth('auth.registered', $request, $user);

        return redirect()->intended($this->redirectPathFor($user))
            ->with('status', 'Your account is ready.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $this->eventLogger->auth('auth.logout', $request, $user);

        return redirect()->route('home');
    }

    public function showConfirmPassword(Request $request): \Illuminate\View\View
    {
        return view('auth.confirm-password', [
            'seo' => $this->authSeo(
                'Confirm Password',
                'Confirm your password before changing sensitive account connections.'
            ),
        ]);
    }

    public function confirmPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! $user instanceof User || ! Hash::check((string) $request->input('password'), (string) $user->password)) {
            return back()->withErrors(['password' => 'Invalid credentials.']);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended($this->redirectPathFor($user));
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = $request->user();
        $user->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();
        $this->eventLogger->auth('auth.password_updated', $request, $user);

        return redirect()->to($this->redirectPathFor($user))
            ->with('status', 'Your password has been changed.');
    }

    public function updateProfilePhoto(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);
        $this->authorize('update', $user);

        $data = $request->validated();

        try {
            $path = $this->profilePhotoStorageService->store($data['profile_photo'], $user);
        } catch (RuntimeException $exception) {
            return redirect()->to($this->redirectPathFor($user))
                ->withErrors(['profile_photo' => $exception->getMessage()]);
        }

        if ($user->profile_photo_path && Str::startsWith($user->profile_photo_path, ['uploads/profile-photos/', 'profile-photos/'])) {
            $this->profilePhotoStorageService->deleteIfManaged($user->profile_photo_path);
        }

        $user->forceFill([
            'profile_photo_path' => $path,
        ])->save();

        return redirect()->to($this->redirectPathFor($user))
            ->with('status', 'Profile picture updated.');
    }

    protected function ensureIsNotRateLimited(Request $request): void
    {
        $key = $this->throttleKey($request);

        if (! RateLimiter::tooManyAttempts($key, 5)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($key);
        $this->eventLogger->security('auth.login_rate_limited', $request, [
            'retry_after' => $seconds,
            'email_hash' => $this->emailFingerprint((string) $request->input('email')),
        ]);

        throw ValidationException::withMessages([
            'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
        ]);
    }

    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());
    }

    protected function lockoutDecaySeconds(): int
    {
        return 300;
    }

    protected function applySecureCookieSettings(): void
    {
        config([
            'session.secure' => (bool) config('session.secure'),
            'session.http_only' => true,
            'session.same_site' => 'lax',
        ]);
    }

    protected function emailFingerprint(?string $email): ?string
    {
        $normalized = strtolower(trim((string) $email));

        return $normalized !== '' ? substr(hash('sha256', $normalized), 0, 16) : null;
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

    protected function requiresVerifiedEmail(User $user): bool
    {
        return (bool) config('auth.require_verified_login', false)
            && ! $user->isAdminUser();
    }

    protected function resetTokenIsValid(string $email, string $token): bool
    {
        $user = User::query()->where('email', $email)->first();

        return $user instanceof User
            && Password::broker()->tokenExists($user, $token);
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
}
