<?php

namespace App\Http\Requests\Admin;

use App\Support\Pricing\ValorantPricingConfigValidator;
use Illuminate\Validation\Validator;

class UpdatePricingSettingsRequest extends AdminRequest
{
    protected ?array $normalizedPricingConfig = null;

    public function authorize(): bool
    {
        return $this->authorizeAdminModule('system') && $this->user()?->adminRole() === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'base_prices' => ['required', 'array'],
            'special_rank_boost_rows' => ['nullable', 'array'],
            'special_rank_boost_steps' => ['nullable', 'array'],
            'rr_rules' => ['required', 'array'],
            'addons' => ['required', 'array'],
            'modifiers' => ['required', 'array'],
            'labels' => ['required', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $candidate = $this->candidateConfig();
                $duplicates = $this->duplicateSpecialStepKeys();

                $this->validateSpecialRankBoostRows($validator);

                foreach ($duplicates as $duplicate) {
                    $validator->errors()->add('special_rank_boost_steps', "Duplicate special rank step [{$duplicate}] is not allowed.");
                }

                try {
                    $this->normalizedPricingConfig = app(ValorantPricingConfigValidator::class)->normalize($candidate);
                } catch (\Illuminate\Validation\ValidationException $exception) {
                    foreach ($exception->errors() as $field => $messages) {
                        foreach ($messages as $message) {
                            $validator->errors()->add($field, $message);
                        }
                    }
                }
            },
        ];
    }

    public function pricingConfig(): array
    {
        if ($this->normalizedPricingConfig !== null) {
            return $this->normalizedPricingConfig;
        }

        return app(ValorantPricingConfigValidator::class)->normalize($this->candidateConfig());
    }

    protected function candidateConfig(): array
    {
        $defaults = (array) config('pricing', []);

        return [
            'rank_order' => $defaults['rank_order'] ?? [],
            'services' => $defaults['services'] ?? [],
            'base_prices' => $this->input('base_prices', []),
            'special_rank_boost_steps' => $this->specialRankBoostSteps(),
            'rr_rules' => $this->input('rr_rules', []),
            'addons' => $this->input('addons', []),
            'disabled_addons' => $defaults['disabled_addons'] ?? [],
            'modifiers' => $this->input('modifiers', []),
            'labels' => $this->input('labels', []),
        ];
    }

    protected function specialRankBoostSteps(): array
    {
        $rows = $this->specialRankBoostRowsInput();

        if ($rows !== []) {
            return $this->specialRankBoostStepsFromRows($rows);
        }

        $canonicalSteps = $this->input('special_rank_boost_steps', []);

        if (! is_array($canonicalSteps)) {
            return [];
        }

        $steps = [];

        foreach ($canonicalSteps as $key => $price) {
            if (is_array($price)) {
                continue;
            }

            $stepKey = trim((string) $key);
            $stepPrice = trim((string) $price);

            if ($stepKey === '' && $stepPrice === '') {
                continue;
            }

            $steps[$stepKey] = $stepPrice;
        }

        return $steps;
    }

    protected function specialRankBoostStepsFromRows(array $rows): array
    {
        $steps = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $fromRank = trim((string) ($row['from'] ?? ''));
            $toRank = trim((string) ($row['to'] ?? ''));
            $price = trim((string) ($row['price'] ?? ''));

            if ($fromRank === '' && $toRank === '' && $price === '') {
                continue;
            }

            $steps["{$fromRank}->{$toRank}"] = $price;
        }

        return $steps;
    }

    protected function duplicateSpecialStepKeys(): array
    {
        $rows = $this->specialRankBoostRowsInput();

        if ($rows === []) {
            return [];
        }

        $seen = [];
        $duplicates = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $fromRank = trim((string) ($row['from'] ?? ''));
            $toRank = trim((string) ($row['to'] ?? ''));
            $price = trim((string) ($row['price'] ?? ''));

            if ($fromRank === '' && $toRank === '' && $price === '') {
                continue;
            }

            $key = "{$fromRank}->{$toRank}";

            if (isset($seen[$key])) {
                $duplicates[] = $key;

                continue;
            }

            $seen[$key] = true;
        }

        return array_values(array_unique($duplicates));
    }

    protected function specialRankBoostRowsInput(): array
    {
        $rows = $this->input('special_rank_boost_rows');

        if (is_array($rows)) {
            return $rows;
        }

        $legacyRows = $this->input('special_rank_boost_steps', []);

        if (is_array($legacyRows) && $this->looksLikeSpecialRankBoostRows($legacyRows)) {
            return $legacyRows;
        }

        return [];
    }

    protected function looksLikeSpecialRankBoostRows(array $value): bool
    {
        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }

            foreach (['from', 'to', 'price'] as $rowKey) {
                if (array_key_exists($rowKey, $row)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function validateSpecialRankBoostRows(Validator $validator): void
    {
        $rows = $this->specialRankBoostRowsInput();

        if ($rows === []) {
            return;
        }

        $rankIndexes = array_flip(array_values((array) config('pricing.rank_order', [])));
        $seen = [];

        foreach ($rows as $index => $row) {
            $fieldPrefix = "special_rank_boost_rows.{$index}";

            if (! is_array($row)) {
                $validator->errors()->add($fieldPrefix, 'Special rank step rows must include from, to, and price fields.');

                continue;
            }

            $fromRank = trim((string) ($row['from'] ?? ''));
            $toRank = trim((string) ($row['to'] ?? ''));
            $price = trim((string) ($row['price'] ?? ''));

            if ($fromRank === '' && $toRank === '' && $price === '') {
                continue;
            }

            if ($fromRank === '') {
                $validator->errors()->add("{$fieldPrefix}.from", 'Choose the starting rank for this special step.');
            }

            if ($toRank === '') {
                $validator->errors()->add("{$fieldPrefix}.to", 'Choose the target rank for this special step.');
            }

            if ($price === '') {
                $validator->errors()->add("{$fieldPrefix}.price", 'Enter a special rank step price.');
            } elseif (! is_numeric($price)) {
                $validator->errors()->add("{$fieldPrefix}.price", 'This value must be numeric.');
            } elseif ((float) $price < 0) {
                $validator->errors()->add("{$fieldPrefix}.price", 'This value must be at least 0.');
            }

            if ($fromRank !== '' && ! array_key_exists($fromRank, $rankIndexes)) {
                $validator->errors()->add("{$fieldPrefix}.from", 'Choose a supported starting rank.');
            }

            if ($toRank !== '' && ! array_key_exists($toRank, $rankIndexes)) {
                $validator->errors()->add("{$fieldPrefix}.to", 'Choose a supported target rank.');
            }

            if ($fromRank === '' || $toRank === '' || ! array_key_exists($fromRank, $rankIndexes) || ! array_key_exists($toRank, $rankIndexes)) {
                continue;
            }

            if (((int) $rankIndexes[$toRank]) !== ((int) $rankIndexes[$fromRank]) + 1) {
                $validator->errors()->add("{$fieldPrefix}.to", 'Special rank steps must use consecutive ranks.');

                continue;
            }

            $key = "{$fromRank}->{$toRank}";

            if (isset($seen[$key])) {
                $validator->errors()->add("{$fieldPrefix}.from", "Duplicate special rank step [{$key}] is not allowed.");
                $validator->errors()->add("{$fieldPrefix}.to", "Duplicate special rank step [{$key}] is not allowed.");
            }

            $seen[$key] = true;
        }
    }
}
