<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminAuditLogger;
use App\Support\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\View\View;

abstract class AdminController extends Controller
{
    public const ACCOUNT_STATUS_OPTIONS = [
        'active' => 'Active',
        'suspended' => 'Suspended',
    ];

    public const PAYMENT_STATUS_OPTIONS = [
        'pending' => 'Pending',
        'paid' => 'Paid',
    ];

    public static function statusOptions(): array
    {
        return OrderStatus::options();
    }

    protected function renderPage(string $view, array $payload = []): View
    {
        abort_unless($this->userIsAdmin(), 403);

        return view($view, $payload);
    }

    protected function userIsAdmin(): bool
    {
        return Auth::check() && (bool) Auth::user()?->isAdminUser();
    }

    protected function normalizeStructuredFields(array|Collection|null $values): array
    {
        $items = collect($values ?? []);

        return $items
            ->map(function ($value) {
                if (! is_string($value)) {
                    return $value;
                }

                $trim = trim($value);
                if (($trim === '' && $trim !== '0') || (! $trim)) {
                    return $trim;
                }

                if (in_array($trim[0], ['{', '['], true)) {
                    $decoded = json_decode($trim, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $decoded;
                    }
                }

                return $value;
            })
            ->toArray();
    }

    protected function audit(
        string $module,
        string $action,
        Model|string|null $subject = null,
        array $metadata = [],
        ?Request $request = null,
    ): void {
        app(AdminAuditLogger::class)->log(
            $module,
            $action,
            Auth::user(),
            $subject,
            $metadata,
            $request ?? request()
        );
    }
}
