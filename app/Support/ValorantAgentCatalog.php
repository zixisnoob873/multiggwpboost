<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ValorantAgentCatalog
{
    protected static ?array $allAgents = null;

    protected static ?Collection $agentsByUuid = null;

    public static function all(): array
    {
        if (self::$allAgents !== null) {
            return self::$allAgents;
        }

        return self::$allAgents = collect(config('valorant_agents', []))
            ->map(fn (array $agent) => self::sanitizeAgent($agent))
            ->filter(fn (array $agent) => $agent['uuid'] !== '' && $agent['displayName'] !== '' && $agent['displayIcon'] !== '')
            ->sortBy('displayName', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    public static function allowedUuids(): array
    {
        return array_column(self::all(), 'uuid');
    }

    public static function has(mixed $uuid): bool
    {
        $normalized = self::normalizeUuid($uuid);

        return $normalized !== null && self::byUuid()->has($normalized);
    }

    public static function resolve(mixed $uuid): ?array
    {
        $normalized = self::normalizeUuid($uuid);

        if ($normalized === null) {
            return null;
        }

        $agent = self::byUuid()->get($normalized);

        return is_array($agent) ? $agent : null;
    }

    public static function resolveMany(mixed $values): array
    {
        return collect(self::normalizeSelection($values))
            ->map(fn (string $uuid) => self::resolve($uuid))
            ->filter(fn (?array $agent) => $agent !== null)
            ->values()
            ->all();
    }

    public static function normalizeSelection(mixed $values): array
    {
        return self::parseSelection($values)['uuids'];
    }

    public static function parseSelection(mixed $values): array
    {
        $rawItems = self::rawSelectionItems($values);
        $uuids = [];
        $hasDuplicates = false;
        $hasInvalidItems = false;
        $seen = [];

        foreach ($rawItems as $item) {
            if (! is_scalar($item) || is_bool($item)) {
                $hasInvalidItems = true;

                continue;
            }

            $uuid = self::normalizeUuid($item);

            if ($uuid === null || ! self::has($uuid)) {
                $hasInvalidItems = true;

                continue;
            }

            if (isset($seen[$uuid])) {
                $hasDuplicates = true;

                continue;
            }

            $seen[$uuid] = true;
            $uuids[] = $uuid;
        }

        return [
            'uuids' => $uuids,
            'submitted' => $rawItems !== [],
            'hasDuplicates' => $hasDuplicates,
            'hasInvalidItems' => $hasInvalidItems,
        ];
    }

    protected static function byUuid(): Collection
    {
        if (self::$agentsByUuid !== null) {
            return self::$agentsByUuid;
        }

        return self::$agentsByUuid = collect(self::all())
            ->keyBy(fn (array $agent) => Str::lower($agent['uuid']));
    }

    protected static function sanitizeAgent(array $agent): array
    {
        return [
            'uuid' => trim((string) ($agent['uuid'] ?? '')),
            'displayName' => trim((string) ($agent['displayName'] ?? '')),
            'displayIcon' => trim((string) ($agent['displayIcon'] ?? '')),
            'role' => trim((string) ($agent['role'] ?? 'Agent')),
        ];
    }

    protected static function normalizeUuid(mixed $uuid): ?string
    {
        if (! is_scalar($uuid) || is_bool($uuid)) {
            return null;
        }

        $normalized = Str::lower(trim((string) $uuid));

        return $normalized !== '' ? $normalized : null;
    }

    protected static function rawSelectionItems(mixed $values): array
    {
        if ($values instanceof Collection) {
            $values = $values->all();
        }

        if ($values === null) {
            return [];
        }

        if (is_string($values)) {
            $trimmed = trim($values);

            if ($trimmed === '') {
                return [];
            }

            if (Str::startsWith($trimmed, '[')) {
                $decoded = json_decode($trimmed, true);

                if (is_array($decoded)) {
                    return array_values($decoded);
                }
            }

            if (str_contains($trimmed, ',')) {
                return collect(explode(',', $trimmed))
                    ->map(fn (string $item) => trim($item))
                    ->filter()
                    ->values()
                    ->all();
            }

            return [$trimmed];
        }

        if (is_array($values)) {
            return array_values($values);
        }

        return [$values];
    }
}
