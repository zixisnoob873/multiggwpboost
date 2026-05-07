<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

abstract class AdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->isAuthorizedAdmin();
    }

    protected function isAuthorizedAdmin(): bool
    {
        return (bool) $this->user()?->isAdminUser();
    }

    protected function authorizeAdminModule(string $module): bool
    {
        return $this->isAuthorizedAdmin() && (bool) $this->user()?->canAccessAdminModule($module);
    }

    protected function normalizeSearch(?string $key = 'search', int $max = 120): ?string
    {
        $value = trim((string) $this->input($key, ''));

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }

    protected function normalizeSortDirection(?string $key = 'direction', string $default = 'desc'): string
    {
        $value = strtolower(trim((string) $this->input($key, $default)));

        return in_array($value, ['asc', 'desc'], true) ? $value : $default;
    }

    protected function normalizeNullableString(string $key, int $max = 255): ?string
    {
        $value = trim((string) $this->input($key, ''));

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }

    protected function trimNullableString(string $key): ?string
    {
        $value = trim((string) $this->input($key, ''));

        return $value === '' ? null : $value;
    }
}
