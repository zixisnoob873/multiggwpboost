<?php

namespace App\Http\Requests\Admin;

use App\Models\GameAddon;
use App\Models\GameService;
use App\Models\ServicePricingRule;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MarketplaceAddonRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('marketplace');
    }

    public function rules(): array
    {
        $addon = $this->route('addon');
        $addonId = $addon instanceof GameAddon ? $addon->id : null;

        return [
            'game_id' => ['required', 'integer', Rule::exists('games', 'id')],
            'label' => ['required', 'string', 'max:160'],
            'slug' => [
                'required',
                'string',
                'max:180',
                Rule::unique('game_addons', 'slug')
                    ->where(fn ($query) => $query->where('game_id', $this->input('game_id')))
                    ->ignore($addonId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'string', 'max:2048'],
            'status' => ['required', Rule::in(GameAddon::STATUSES)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'pricing_type' => ['required', Rule::in([
                'free',
                ServicePricingRule::PRICING_FIXED,
                ServicePricingRule::PRICING_PERCENTAGE,
                ServicePricingRule::PRICING_MULTIPLIER,
            ])],
            'pricing_value' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'service_ids' => ['array'],
            'service_ids.*' => ['integer', Rule::exists('game_services', 'id')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $gameId = (int) $this->input('game_id');
            $serviceIds = array_map('intval', (array) $this->input('service_ids', []));

            if ($serviceIds === []) {
                return;
            }

            $invalidServiceExists = GameService::query()
                ->whereIn('id', $serviceIds)
                ->where('game_id', '!=', $gameId)
                ->exists();

            if ($invalidServiceExists) {
                $validator->errors()->add('service_ids', 'Only services from the selected game can be assigned.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $label = trim((string) $this->input('label'));
        $slug = Str::slug((string) ($this->input('slug') ?: $label));
        $pricingType = (string) ($this->input('pricing_type') ?: 'free');
        $pricingValue = $pricingType === 'free' ? 0 : $this->input('pricing_value');

        $this->merge([
            'label' => $label,
            'slug' => $slug,
            'description' => $this->trimNullableString('description'),
            'icon' => $this->trimNullableString('icon'),
            'status' => $this->input('status') ?: GameAddon::STATUS_DRAFT,
            'sort_order' => $this->input('sort_order', 0),
            'pricing_type' => $pricingType,
            'pricing_value' => $pricingValue === '' ? null : $pricingValue,
            'service_ids' => array_values(array_filter((array) $this->input('service_ids', []), 'is_numeric')),
        ]);
    }

    public function catalogData(): array
    {
        return $this->safe()->only([
            'game_id',
            'slug',
            'label',
            'description',
            'icon',
            'status',
            'sort_order',
            'pricing_type',
            'pricing_value',
        ]);
    }
}
