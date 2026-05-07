<?php

namespace App\Support;

use InvalidArgumentException;

class AgentSelectionValidator
{
    public static function inspect(string $key, mixed $values): array
    {
        $selection = ValorantAgentCatalog::parseSelection($values);

        return [
            ...$selection,
            'messages' => self::messagesFromSelection($key, $selection),
        ];
    }

    public static function inspectPayload(array $payload): array
    {
        $selections = [];

        foreach (BoostingCatalog::agentSelectionAddons() as $key => $definition) {
            $rawValue = $payload[$key]
                ?? $payload[$definition['input_name'] ?? '']
                ?? [];

            $selections[$key] = self::inspect($key, $rawValue);
        }

        return $selections;
    }

    public static function messages(string $key, mixed $values, bool $required = false): array
    {
        return self::messagesFromSelection($key, self::inspect($key, $values), $required);
    }

    public static function messagesFromSelection(string $key, array $selection, bool $required = false): array
    {
        $definition = self::definition($key);
        $messages = [];
        $count = count($selection['uuids'] ?? []);
        $min = (int) ($definition['min'] ?? 0);
        $max = $definition['max'];
        $max = $max !== null ? (int) $max : null;

        if ($selection['hasInvalidItems'] ?? false) {
            $messages[] = $definition['invalid_message'];
        }

        if ($selection['hasDuplicates'] ?? false) {
            $messages[] = $definition['duplicate_message'];
        }

        if ($max !== null && $count > $max) {
            $messages[] = $definition['required_message'];
        }

        if ($required && $count < $min) {
            $messages[] = $definition['required_message'];
        }

        if ($required && $max !== null && $count !== $max && $min === $max && ! in_array($definition['required_message'], $messages, true)) {
            $messages[] = $definition['required_message'];
        }

        return array_values(array_unique($messages));
    }

    public static function validateSelections(array $inspectedSelections, array $selectedAddons = [], array $disabledAddons = []): array
    {
        $errors = [];
        $normalizedAddons = BoostingCatalog::normalizeAddons($selectedAddons);
        $normalizedDisabledAddons = BoostingCatalog::normalizeAddons($disabledAddons);

        foreach (BoostingCatalog::agentSelectionAddons() as $key => $definition) {
            $selection = $inspectedSelections[$key] ?? self::inspect($key, []);
            $hasAddon = in_array($definition['label'], $normalizedAddons, true);
            $isDisabled = in_array($definition['label'], $normalizedDisabledAddons, true);
            $messages = self::messagesFromSelection($key, $selection, $hasAddon && ! $isDisabled);

            if (! $hasAddon && ($selection['uuids'] ?? []) !== []) {
                $messages[] = $definition['addon_required_message'];
            }

            if ($isDisabled && ($selection['uuids'] ?? []) !== []) {
                $messages[] = $definition['disabled_message'];
            }

            if ($messages !== []) {
                $errors[$key] = array_values(array_unique($messages));
            }
        }

        return $errors;
    }

    protected static function definition(string $key): array
    {
        $definition = BoostingCatalog::agentSelectionAddon($key);

        if (! is_array($definition)) {
            throw new InvalidArgumentException("Unsupported agent selection key [{$key}].");
        }

        return $definition;
    }
}
