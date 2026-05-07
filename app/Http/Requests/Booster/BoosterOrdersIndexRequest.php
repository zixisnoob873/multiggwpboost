<?php

namespace App\Http\Requests\Booster;

use App\Support\BoostingCatalog;
use App\Support\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BoosterOrdersIndexRequest extends FormRequest
{
    protected $redirectRoute = 'booster-orders';

    public function authorize(): bool
    {
        return $this->user()?->role === 'booster';
    }

    protected function prepareForValidation(): void
    {
        $serviceInput = trim((string) $this->input('service', ''));
        $service = $serviceInput === ''
            ? null
            : (BoostingCatalog::normalizeServiceType($serviceInput) ?? $serviceInput);
        $view = Str::lower(trim((string) $this->input('view', 'all')));
        $search = trim((string) $this->input('search', ''));
        $status = trim((string) $this->input('status', ''));
        $region = Str::upper(trim((string) $this->input('region', '')));

        $this->merge([
            'search' => $search !== '' ? $search : null,
            'status' => $status !== '' ? $status : null,
            'region' => $region !== '' ? $region : null,
            'service' => $service,
            'view' => $view !== '' ? $view : 'all',
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(OrderStatus::values())],
            'region' => ['nullable', Rule::in(BoostingCatalog::regions())],
            'service' => ['nullable', Rule::in(BoostingCatalog::serviceOptions())],
            'view' => ['required', Rule::in(['all', 'assigned'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
