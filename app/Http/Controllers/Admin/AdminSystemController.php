<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\AdminAuditLogIndexRequest;
use App\Http\Requests\Admin\AdminSystemSettingsRequest;
use App\Models\User;
use App\Queries\Admin\AuditLogIndexQuery;
use App\Services\SystemSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminSystemController extends AdminController
{
    public function __construct(
        protected SystemSettingService $systemSettingService,
        protected AuditLogIndexQuery $auditLogIndexQuery,
    ) {}

    public function settings(): View
    {
        $definitions = (array) config('admin.settings', []);

        return $this->renderPage('admin.system.settings', [
            'settingsDefinitions' => $definitions,
            'settingsValues' => $this->systemSettingService->getMany($definitions),
            'integrations' => [
                [
                    'label' => 'Queue Driver',
                    'value' => (string) config('queue.default', 'sync'),
                ],
                [
                    'label' => 'Mail Driver',
                    'value' => (string) config('mail.default', 'log'),
                ],
                [
                    'label' => 'Broadcast Driver',
                    'value' => (string) config('broadcasting.default', 'null'),
                ],
                [
                    'label' => 'Stripe',
                    'value' => filled(config('services.stripe.key')) ? 'Configured' : 'Missing',
                ],
                [
                    'label' => 'Cryptomus',
                    'value' => filled(config('services.cryptomus.merchant_id')) ? 'Configured' : 'Missing',
                ],
            ],
        ]);
    }

    public function updateSettings(AdminSystemSettingsRequest $request): RedirectResponse
    {
        $changedKeys = [];

        foreach ($request->validated() as $key => $value) {
            if (! array_key_exists($key, (array) config('admin.settings', []))) {
                continue;
            }

            $this->systemSettingService->putString($key, $value);
            $changedKeys[] = $key;
        }

        $this->audit('system', 'system_settings_updated', 'System Settings', [
            'changed_keys' => $changedKeys,
        ], $request);

        return redirect()
            ->route('admin-system.settings')
            ->with('status', 'System settings updated.');
    }

    public function auditLogs(AdminAuditLogIndexRequest $request): View
    {
        $payload = $this->auditLogIndexQuery->execute($request->validated() + [
            'per_page' => (int) ($request->validated('per_page') ?? 25),
        ]);

        $payload['moduleOptions'] = (array) config('admin.modules', []);
        $payload['adminUsers'] = User::query()
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return $this->renderPage('admin.system.audit-logs', $payload);
    }
}
