<?php

namespace App\Support\Pricing;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ValorantPricingConfigValidator
{
    public function normalize(array $candidate): array
    {
        $defaults = $this->defaults();
        $errors = [];

        $this->validateLockedSection($candidate, $defaults, 'rank_order', $errors);
        $this->validateLockedSection($candidate, $defaults, 'services', $errors);

        $normalized = [
            'rank_order' => $defaults['rank_order'],
            'services' => $defaults['services'],
            'base_prices' => $this->normalizeBasePrices($candidate['base_prices'] ?? null, $defaults, $errors),
            'special_rank_boost_steps' => $this->normalizeSpecialRankBoostSteps($candidate['special_rank_boost_steps'] ?? null, $defaults, $errors),
            'rr_rules' => $this->normalizeRrRules($candidate['rr_rules'] ?? null, $defaults, $errors),
            'addons' => $this->normalizeAddons($candidate['addons'] ?? null, $defaults, $errors),
            'disabled_addons' => $defaults['disabled_addons'] ?? [],
            'modifiers' => $this->normalizeModifiers($candidate['modifiers'] ?? null, $defaults, $errors),
            'labels' => $this->normalizeLabels($candidate['labels'] ?? null, $defaults, $errors),
        ];

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    public function checksum(array $config): string
    {
        $normalized = $this->sortForChecksum($config);

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
    }

    protected function normalizeBasePrices(mixed $value, array $defaults, array &$errors): array
    {
        if (! is_array($value)) {
            $errors['base_prices'][] = 'Base prices must be a complete pricing table.';

            return [];
        }

        $expected = (array) ($defaults['base_prices'] ?? []);
        $this->validateExactKeys($value, $expected, 'base_prices', 'base price service', $errors);
        $normalized = [];

        foreach ($expected as $service => $rankPrices) {
            $serviceValues = $value[$service] ?? null;
            if (! is_array($serviceValues)) {
                $errors["base_prices.{$service}"][] = "{$service} base prices are required.";

                continue;
            }

            $this->validateExactKeys($serviceValues, $rankPrices, "base_prices.{$service}", 'rank', $errors);
            $normalized[$service] = [];

            foreach (array_keys($rankPrices) as $rank) {
                $normalized[$service][$rank] = $this->number(
                    $serviceValues[$rank] ?? null,
                    "base_prices.{$service}.{$rank}",
                    $errors,
                    min: 0,
                );
            }
        }

        return $normalized;
    }

    protected function normalizeSpecialRankBoostSteps(mixed $value, array $defaults, array &$errors): array
    {
        if (! is_array($value)) {
            $errors['special_rank_boost_steps'][] = 'Special rank boost steps must be an array.';

            return [];
        }

        $rankOrder = array_values((array) ($defaults['rank_order'] ?? []));
        $rankIndexes = array_flip($rankOrder);
        $normalized = [];

        foreach ($value as $key => $price) {
            $stepKey = trim((string) $key);

            if (! str_contains($stepKey, '->')) {
                $errors['special_rank_boost_steps'][] = "Special rank step [{$stepKey}] is malformed.";

                continue;
            }

            [$fromRank, $toRank] = array_map('trim', explode('->', $stepKey, 2));

            if (! array_key_exists($fromRank, $rankIndexes) || ! array_key_exists($toRank, $rankIndexes)) {
                $errors['special_rank_boost_steps'][] = "Special rank step [{$stepKey}] uses unsupported ranks.";

                continue;
            }

            if (((int) $rankIndexes[$toRank]) !== ((int) $rankIndexes[$fromRank]) + 1) {
                $errors['special_rank_boost_steps'][] = "Special rank step [{$stepKey}] must use consecutive ranks.";

                continue;
            }

            $normalized["{$fromRank}->{$toRank}"] = $this->number(
                $price,
                "special_rank_boost_steps.{$fromRank}->{$toRank}",
                $errors,
                min: 0,
            );
        }

        return $normalized;
    }

    protected function normalizeRrRules(mixed $value, array $defaults, array &$errors): array
    {
        if (! is_array($value)) {
            $errors['rr_rules'][] = 'RR rules must be present.';

            return [];
        }

        $defaultModifiers = (array) Arr::get($defaults, 'rr_rules.avg_rr_modifiers', []);
        $submittedModifiers = $value['avg_rr_modifiers'] ?? null;

        if (! is_array($submittedModifiers)) {
            $errors['rr_rules.avg_rr_modifiers'][] = 'Average RR modifiers are required.';
            $submittedModifiers = [];
        } else {
            $this->validateExactKeys($submittedModifiers, $defaultModifiers, 'rr_rules.avg_rr_modifiers', 'average RR option', $errors);
        }

        $normalizedModifiers = [];
        foreach (array_keys($defaultModifiers) as $key) {
            $normalizedModifiers[$key] = $this->number(
                $submittedModifiers[$key] ?? null,
                "rr_rules.avg_rr_modifiers.{$key}",
                $errors,
                min: 0,
                max: 5,
            );
        }

        return [
            'current_rr_discount_threshold' => $this->integer(
                $value['current_rr_discount_threshold'] ?? null,
                'rr_rules.current_rr_discount_threshold',
                $errors,
                0,
                100,
            ),
            'first_step_discount_multiplier' => $this->number(
                $value['first_step_discount_multiplier'] ?? null,
                'rr_rules.first_step_discount_multiplier',
                $errors,
                min: 0,
                max: 5,
            ),
            'avg_rr_modifiers' => $normalizedModifiers,
        ];
    }

    protected function normalizeAddons(mixed $value, array $defaults, array &$errors): array
    {
        if (! is_array($value)) {
            $errors['addons'][] = 'Addon pricing rules must be present.';

            return [];
        }

        $expected = (array) ($defaults['addons'] ?? []);
        $this->validateExactKeys($value, $expected, 'addons', 'addon', $errors);
        $normalized = [];

        foreach ($expected as $label => $defaultRule) {
            $rule = $value[$label] ?? null;
            if (! is_array($rule)) {
                $errors["addons.{$label}"][] = "{$label} addon pricing is required.";

                continue;
            }

            $type = Str::lower(trim((string) ($rule['type'] ?? '')));
            if (! in_array($type, ['free', 'percent', 'bonus_win'], true)) {
                $errors["addons.{$label}.type"][] = "{$label} must use free, percent, or bonus win pricing.";
                $type = (string) ($defaultRule['type'] ?? 'free');
            }

            $normalized[$label] = [
                'type' => $type,
                'value' => $type === 'percent'
                    ? $this->number($rule['value'] ?? null, "addons.{$label}.value", $errors, min: 0, max: 5)
                    : 0,
            ];

        }

        return $normalized;
    }

    protected function normalizeModifiers(mixed $value, array $defaults, array &$errors): array
    {
        if (! is_array($value)) {
            $errors['modifiers'][] = 'Modifier pricing rules must be present.';

            return [];
        }

        $expected = (array) ($defaults['modifiers'] ?? []);
        $this->validateExactKeys($value, $expected, 'modifiers', 'modifier group', $errors);
        $normalized = [];

        foreach ($expected as $group => $groupDefaults) {
            $submittedGroup = $value[$group] ?? null;
            if (! is_array($submittedGroup)) {
                $errors["modifiers.{$group}"][] = "{$group} modifiers are required.";
                $submittedGroup = [];
            } else {
                $this->validateExactKeys($submittedGroup, $groupDefaults, "modifiers.{$group}", 'modifier key', $errors);
            }

            $normalized[$group] = [];
            foreach (array_keys((array) $groupDefaults) as $key) {
                $normalized[$group][$key] = $this->number(
                    $submittedGroup[$key] ?? null,
                    "modifiers.{$group}.{$key}",
                    $errors,
                    min: 0,
                    max: 5,
                );
            }
        }

        return $normalized;
    }

    protected function normalizeLabels(mixed $value, array $defaults, array &$errors): array
    {
        if (! is_array($value)) {
            $errors['labels'][] = 'Display labels must be present.';

            return [];
        }

        $expected = (array) ($defaults['labels'] ?? []);
        $this->validateExactKeys($value, $expected, 'labels', 'label group', $errors);
        $normalized = [];

        foreach ($expected as $group => $groupDefaults) {
            $submittedGroup = $value[$group] ?? null;
            if (! is_array($submittedGroup)) {
                $errors["labels.{$group}"][] = "{$group} labels are required.";
                $submittedGroup = [];
            } else {
                $this->validateExactKeys($submittedGroup, $groupDefaults, "labels.{$group}", 'label key', $errors);
            }

            $normalized[$group] = [];
            foreach (array_keys((array) $groupDefaults) as $key) {
                $label = trim((string) ($submittedGroup[$key] ?? ''));

                if ($label === '') {
                    $errors["labels.{$group}.{$key}"][] = 'Display labels cannot be empty.';
                }

                if (mb_strlen($label) > 80) {
                    $errors["labels.{$group}.{$key}"][] = 'Display labels may not exceed 80 characters.';
                }

                $normalized[$group][$key] = $label;
            }
        }

        return $normalized;
    }

    protected function validateLockedSection(array $candidate, array $defaults, string $key, array &$errors): void
    {
        if (! array_key_exists($key, $candidate)) {
            $errors[$key][] = "{$key} is required.";

            return;
        }

        if ($candidate[$key] !== ($defaults[$key] ?? null)) {
            $errors[$key][] = "{$key} is locked and must match the default pricing configuration.";
        }
    }

    protected function validateExactKeys(array $submitted, array $expected, string $field, string $label, array &$errors): void
    {
        $submittedKeys = array_map('strval', array_keys($submitted));
        $expectedKeys = array_map('strval', array_keys($expected));
        $missing = array_values(array_diff($expectedKeys, $submittedKeys));
        $unsupported = array_values(array_diff($submittedKeys, $expectedKeys));

        if ($missing !== []) {
            $errors[$field][] = 'Missing '.$label.' entries: '.implode(', ', $missing).'.';
        }

        if ($unsupported !== []) {
            $errors[$field][] = 'Unsupported '.$label.' entries: '.implode(', ', $unsupported).'.';
        }
    }

    protected function number(mixed $value, string $field, array &$errors, float $min = 0, ?float $max = null): float
    {
        if (! is_numeric($value)) {
            $errors[$field][] = 'This value must be numeric.';

            return 0.0;
        }

        $number = (float) $value;

        if ($number < $min) {
            $errors[$field][] = "This value must be at least {$min}.";
        }

        if ($max !== null && $number > $max) {
            $errors[$field][] = "This value must be no greater than {$max}.";
        }

        return round($number + 0.0000001, 4);
    }

    protected function integer(mixed $value, string $field, array &$errors, int $min, int $max): int
    {
        if (! is_numeric($value) || (string) (int) $value !== trim((string) $value)) {
            $errors[$field][] = 'This value must be an integer.';

            return $min;
        }

        $integer = (int) $value;

        if ($integer < $min || $integer > $max) {
            $errors[$field][] = "This value must be between {$min} and {$max}.";
        }

        return max($min, min($max, $integer));
    }

    protected function defaults(): array
    {
        return (array) config('pricing', []);
    }

    protected function sortForChecksum(array $value): array
    {
        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item): mixed => is_array($item) ? $this->sortForChecksum($item) : $item,
                $value
            );
        }

        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortForChecksum($item);
            }
        }

        return $value;
    }
}
