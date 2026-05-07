<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ConfirmMaintenanceModePhraseRequest;
use App\Http\Requests\Admin\IssueMaintenanceModeChallengeRequest;
use App\Http\Requests\Admin\UpdateMaintenanceModeRequest;
use App\Http\Requests\Admin\VerifyMaintenanceModeCaptchaRequest;
use App\Http\Requests\Admin\VerifyMaintenanceModePasswordRequest;
use App\Services\MaintenanceModeChallengeService;
use App\Services\SystemSettingService;
use App\Support\Logging\AppEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminMaintenanceModeController extends AdminController
{
    public function __construct(
        protected SystemSettingService $systemSettingService,
        protected MaintenanceModeChallengeService $maintenanceModeChallengeService,
        protected AppEventLogger $eventLogger,
    ) {}

    public function index(): \Illuminate\View\View
    {
        return $this->renderPage('admin.system.maintenance-mode', [
            'maintenanceModeEnabled' => $this->systemSettingService->isMaintenanceModeEnabled(),
        ]);
    }

    public function challenge(IssueMaintenanceModeChallengeRequest $request): JsonResponse
    {
        $enabled = (bool) $request->validated('enabled');
        $flow = $this->maintenanceModeChallengeService->start($request->user(), $enabled);

        $this->eventLogger->admin('admin.maintenance_mode_confirmation_started', $request, $request->user(), [
            'maintenance_mode_target_enabled' => $enabled,
        ]);

        return response()->json([
            'success' => true,
            'flow' => $flow,
            'message' => 'Security confirmation started.',
        ]);
    }

    public function confirmPhrase(ConfirmMaintenanceModePhraseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $enabled = (bool) $data['enabled'];

        if ((string) $data['confirmation_text'] !== 'CONFIRM') {
            $this->eventLogger->security('admin.maintenance_mode_confirmation_phrase_failed', $request, [
                'maintenance_mode_target_enabled' => $enabled,
            ]);

            return response()->json([
                'success' => false,
                'step' => 'confirmation_text',
                'message' => 'Type CONFIRM exactly to continue.',
                'error_code' => 'maintenance_mode_confirmation_phrase_failed',
                'errors' => [
                    'confirmation_text' => ['Type CONFIRM exactly to continue.'],
                ],
            ], 422);
        }

        $verification = $this->maintenanceModeChallengeService->advanceConfirmation(
            $request->user(),
            (string) $data['flow_token'],
            $enabled,
        );

        if (! ($verification['valid'] ?? false)) {
            return $this->invalidFlowResponse($request, $enabled, 'step_1', (string) ($verification['reason'] ?? 'invalid'));
        }

        $this->eventLogger->admin('admin.maintenance_mode_confirmation_phrase_verified', $request, $request->user(), [
            'maintenance_mode_target_enabled' => $enabled,
        ]);

        return response()->json([
            'success' => true,
            'step' => 2,
            'challenge' => [
                'captcha' => $verification['captcha'] ?? null,
                'expires_in' => $verification['expires_in'] ?? null,
            ],
            'message' => 'Confirmation phrase accepted.',
        ]);
    }

    public function verifyCaptcha(VerifyMaintenanceModeCaptchaRequest $request): JsonResponse
    {
        $data = $request->validated();
        $enabled = (bool) $data['enabled'];
        $verification = $this->maintenanceModeChallengeService->verifyCaptcha(
            $request->user(),
            (string) $data['flow_token'],
            $enabled,
            (string) $data['captcha'],
        );

        if (! ($verification['valid'] ?? false)) {
            if (($verification['reason'] ?? null) === 'captcha_mismatch') {
                $message = 'The CAPTCHA was incorrect. A new one has been generated.';

                $this->eventLogger->security('admin.maintenance_mode_captcha_failed', $request, [
                    'maintenance_mode_target_enabled' => $enabled,
                ]);

                return response()->json([
                    'success' => false,
                    'step' => 'captcha',
                    'message' => $message,
                    'error_code' => 'maintenance_mode_captcha_failed',
                    'errors' => [
                        'captcha' => [$message],
                    ],
                    'challenge' => [
                        'captcha' => $verification['captcha'] ?? null,
                        'expires_in' => $verification['expires_in'] ?? null,
                    ],
                ], 422);
            }

            return $this->invalidFlowResponse($request, $enabled, 'captcha', (string) ($verification['reason'] ?? 'invalid'));
        }

        $this->eventLogger->admin('admin.maintenance_mode_captcha_verified', $request, $request->user(), [
            'maintenance_mode_target_enabled' => $enabled,
        ]);

        return response()->json([
            'success' => true,
            'step' => 3,
            'message' => 'CAPTCHA verified.',
        ]);
    }

    public function verifyPassword(VerifyMaintenanceModePasswordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $enabled = (bool) $data['enabled'];
        $user = $request->user();
        $flowToken = (string) $data['flow_token'];

        $verification = $this->maintenanceModeChallengeService->authorizeStep($user, $flowToken, $enabled, 3);

        if (! ($verification['valid'] ?? false) && ($verification['reason'] ?? null) !== 'step_mismatch') {
            return $this->invalidFlowResponse($request, $enabled, 'password', (string) ($verification['reason'] ?? 'invalid'));
        }

        if (($verification['reason'] ?? null) === 'step_mismatch') {
            return response()->json([
                'success' => false,
                'step' => 'password',
                'message' => 'Complete the earlier confirmation steps first.',
                'error_code' => 'maintenance_mode_step_mismatch',
                'errors' => [
                    'current_password' => ['Complete the earlier confirmation steps first.'],
                ],
            ], 422);
        }

        if (! Hash::check((string) $data['current_password'], (string) $user->password)) {
            $this->eventLogger->security('admin.maintenance_mode_password_failed', $request, [
                'maintenance_mode_target_enabled' => $enabled,
            ]);

            return response()->json([
                'success' => false,
                'step' => 'password',
                'message' => 'Your current password is incorrect.',
                'error_code' => 'maintenance_mode_password_failed',
                'errors' => [
                    'current_password' => ['Your current password is incorrect.'],
                ],
            ], 422);
        }

        $advance = $this->maintenanceModeChallengeService->advancePasswordVerified($user, $flowToken, $enabled);

        if (! ($advance['valid'] ?? false)) {
            return $this->invalidFlowResponse($request, $enabled, 'password', (string) ($advance['reason'] ?? 'invalid'));
        }

        $this->eventLogger->admin('admin.maintenance_mode_password_verified', $request, $user, [
            'maintenance_mode_target_enabled' => $enabled,
        ]);

        return response()->json([
            'success' => true,
            'step' => 4,
            'message' => 'Password verified. Final confirmation required.',
        ]);
    }

    public function update(UpdateMaintenanceModeRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $enabled = (bool) $data['enabled'];
        $flowToken = (string) $data['flow_token'];
        $verification = $this->maintenanceModeChallengeService->authorizeFinalization(
            $request->user(),
            $flowToken,
            $enabled,
        );

        if (! ($verification['valid'] ?? false)) {
            return $this->invalidFlowResponse($request, $enabled, 'final_confirmation', (string) ($verification['reason'] ?? 'invalid'));
        }

        try {
            $previouslyEnabled = $this->systemSettingService->isMaintenanceModeEnabled();
            $this->systemSettingService->setMaintenanceMode($enabled);
        } catch (\Throwable $exception) {
            Log::error('Failed to update maintenance mode.', [
                'enabled' => $enabled,
                'admin_id' => $request->user()?->getKey(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to update maintenance mode right now.',
                    'error_code' => 'maintenance_mode_update_failed',
                ], 500);
            }

            return back()->withErrors([
                'maintenance_mode' => 'Unable to update maintenance mode right now.',
            ]);
        }

        $message = $enabled
            ? 'Maintenance mode is now ON.'
            : 'Maintenance mode is now OFF.';

        $this->eventLogger->admin('admin.maintenance_mode_updated', $request, $request->user(), [
            'maintenance_mode_enabled' => $enabled,
            'maintenance_mode_previous_enabled' => $previouslyEnabled,
        ]);
        $this->audit('system', 'maintenance_mode_toggled', 'Maintenance Mode', [
            'from' => $previouslyEnabled,
            'to' => $enabled,
        ], $request);

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'enabled' => $enabled,
                'label' => $enabled ? 'ON' : 'OFF',
                'message' => $message,
            ]);
        }

        return back()->with('status', $message);
    }

    protected function invalidFlowResponse(
        IssueMaintenanceModeChallengeRequest|ConfirmMaintenanceModePhraseRequest|VerifyMaintenanceModeCaptchaRequest|VerifyMaintenanceModePasswordRequest|UpdateMaintenanceModeRequest $request,
        bool $enabled,
        string $step,
        string $reason,
    ): JsonResponse|RedirectResponse {
        $flow = $this->maintenanceModeChallengeService->start($request->user(), $enabled);
        $message = 'This confirmation session expired or became invalid. Please start again.';

        $this->eventLogger->security('admin.maintenance_mode_flow_invalid', $request, [
            'maintenance_mode_target_enabled' => $enabled,
            'reason' => $reason,
            'step' => $step,
        ]);

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'step' => $step,
                'message' => $message,
                'error_code' => 'maintenance_mode_confirmation_invalid',
                'restart_required' => true,
                'flow' => $flow,
                'errors' => [
                    'flow_token' => [$message],
                ],
            ], 422);
        }

        return back()->withErrors([
            'maintenance_mode' => $message,
        ]);
    }
}
