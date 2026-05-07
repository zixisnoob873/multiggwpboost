<?php

namespace App\Http\Requests\Admin;

use App\Models\PromoCode;
use App\Support\BoostingCatalog;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePromoCodeRequest extends AdminRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAdminModule('marketing');
    }

    public function rules(): array
    {
        /** @var PromoCode|null $promoCode */
        $promoCode = $this->route('promoCode');

        return [
            'code' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-_]+$/', Rule::unique('promo_codes', 'code')->ignore($promoCode)],
            'type' => ['required', Rule::in(PromoCode::supportedTypes())],
            'value' => ['nullable', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'unlimited_validity' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'addon_rules' => ['nullable', 'array'],
            'addon_rules.*.addon_slug' => ['required', 'string', Rule::in(BoostingCatalog::addonSlugs()), 'distinct'],
            'addon_rules.*.discount_type' => ['required', Rule::in(PromoCode::supportedAddonDiscountTypes())],
            'addon_rules.*.discount_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'type' => Str::lower(trim((string) $this->input('type'))),
            'value' => $this->normalizeDecimal($this->input('value')),
            'addon_rules' => $this->normalizeAddonRules($this->input('addon_rules', [])),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        /** @var PromoCode|null $promoCode */
        $promoCode = $this->route('promoCode');

        $validator->after(function (Validator $validator) use ($promoCode) {
            $maxUses = $this->input('max_uses');
            $type = (string) $this->input('type');
            $value = $this->input('value');
            $addonRules = $this->input('addon_rules', []);

            if ($promoCode && $maxUses !== null && (int) $maxUses < (int) $promoCode->used_count) {
                $validator->errors()->add('max_uses', 'Max uses cannot be lower than the current used count.');
            }

            if ($type !== PromoCode::TYPE_ADDON_PROMOCODE && (! is_numeric($value) || (float) $value <= 0)) {
                $validator->errors()->add('value', 'Enter a discount value greater than zero.');
            }

            if ($type !== PromoCode::TYPE_ADDON_PROMOCODE && $addonRules !== []) {
                $validator->errors()->add('addon_rules', 'Addon promo rules can only be saved when the type is Addon Promo.');
            }

            if ($type === PromoCode::TYPE_ADDON_PROMOCODE && $addonRules === []) {
                $validator->errors()->add('addon_rules', 'Select at least one addon rule for addon promo codes.');
            }

            foreach ((array) $addonRules as $index => $rule) {
                $discountType = $rule['discount_type'] ?? null;
                $discountValue = $rule['discount_value'] ?? null;

                if ($discountType === PromoCode::ADDON_DISCOUNT_TYPE_PERCENTAGE
                    && (! is_numeric($discountValue) || (float) $discountValue > 100 || (float) $discountValue < 0)
                ) {
                    $validator->errors()->add("addon_rules.{$index}.discount_value", 'Percentage pricing must be between 0 and 100.');
                }

                if ($discountType === PromoCode::ADDON_DISCOUNT_TYPE_FIXED
                    && (! is_numeric($discountValue) || (float) $discountValue < 0)
                ) {
                    $validator->errors()->add("addon_rules.{$index}.discount_value", 'Fixed promo pricing cannot be negative.');
                }
            }
        });
    }

    protected function normalizeAddonRules(mixed $rules): array
    {
        return collect(is_array($rules) ? $rules : [])
            ->map(function (mixed $rule): array {
                $rule = is_array($rule) ? $rule : [];

                return [
                    'selected' => filter_var($rule['selected'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'addon_slug' => trim((string) ($rule['addon_slug'] ?? '')),
                    'discount_type' => Str::lower(trim((string) ($rule['discount_type'] ?? PromoCode::ADDON_DISCOUNT_TYPE_FREE))),
                    'discount_value' => $this->normalizeDecimal($rule['discount_value'] ?? null) ?? 0,
                ];
            })
            ->filter(fn (array $rule): bool => (bool) ($rule['selected'] ?? false))
            ->map(fn (array $rule): array => Arr::except($rule, ['selected']))
            ->all();
    }

    protected function normalizeDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? round((float) $value, 2) : null;
    }
}
