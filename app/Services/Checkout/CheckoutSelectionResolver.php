<?php

namespace App\Services\Checkout;

use App\Support\BoostingCatalog;
use App\Support\GameCatalog;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CheckoutSelectionResolver
{
    public function __construct(
        protected GameCatalog $gameCatalog,
    ) {}

    public function contextFromQuery(mixed $gameSlug = null, mixed $serviceSlug = null): array
    {
        $requestedGameSlug = $this->gameCatalog->normalizeSlug($gameSlug);
        $game = $this->publicGame($requestedGameSlug);
        $errors = [];

        if ($game === null) {
            $errors['gameSlug'][] = 'Select a valid game.';
            $game = $this->publicGame(GameCatalog::DEFAULT_GAME_SLUG) ?? $this->gameCatalog->game(GameCatalog::DEFAULT_GAME_SLUG);
        }

        $service = null;
        $serviceProvided = $this->filled($serviceSlug);

        if ($serviceProvided) {
            $service = $this->resolveService($game, ['serviceSlug' => $serviceSlug], useDefault: false);

            if ($service === null) {
                $errors['serviceSlug'][] = 'Select a service that belongs to the selected game.';
            }
        }

        $service ??= $this->defaultService($game);
        $payload = $service ? $this->seedPayload($game, $service) : [];

        return [
            'gameSlug' => (string) ($game['slug'] ?? GameCatalog::DEFAULT_GAME_SLUG),
            'game' => $game,
            'service' => $service,
            'payload' => $payload,
            'errors' => $errors,
            'valid' => $errors === [] && $service !== null,
            'query' => [
                'gameProvided' => $this->filled($gameSlug),
                'serviceProvided' => $serviceProvided,
            ],
        ];
    }

    public function canonicalizePayload(array $payload): array
    {
        $gameSlug = $this->gameCatalog->resolveSlugFromPayload($payload);
        $game = $this->publicGame($gameSlug);
        $errors = [];

        if ($game === null) {
            $game = $this->publicGame(GameCatalog::DEFAULT_GAME_SLUG) ?? $this->gameCatalog->game(GameCatalog::DEFAULT_GAME_SLUG);
            $errors['gameSlug'][] = 'Select a valid game.';
        }

        $service = $this->resolveService($game, $payload, useDefault: true);

        if ($service === null) {
            $errors[$this->filled($payload['serviceSlug'] ?? $payload['service_slug'] ?? null) ? 'serviceSlug' : 'serviceType'][] =
                'Select a valid service for the selected game.';
        } elseif ($this->hasServiceConflict($service, $payload)) {
            $errors['serviceType'][] = 'Service type does not match the selected service.';
        }

        $canonical = $payload;
        $canonical['gameSlug'] = (string) ($game['slug'] ?? GameCatalog::DEFAULT_GAME_SLUG);
        $canonical['game'] = (string) ($game['name'] ?? $this->gameCatalog->gameName($canonical['gameSlug']));

        if ($service !== null) {
            $canonical['serviceSlug'] = (string) ($service['slug'] ?? '');
            $canonical['serviceType'] = (string) ($service['name'] ?? $canonical['serviceType'] ?? $canonical['orderType'] ?? '');
            $canonical['orderType'] = $canonical['serviceType'];
            $canonical['serviceKind'] = (string) ($service['kind'] ?? '');
        }

        [$addons, $addonErrors, $metadataAddons] = $this->canonicalizeAddons($game, $service, $canonical);
        $errors = array_merge_recursive($errors, $addonErrors);

        $canonical['selectedAddons'] = $addons;
        $canonical['addons'] = $addons;
        $canonical['requestedAddons'] = $addons;

        if (! $this->filled($canonical['queueType'] ?? null)) {
            $canonical['queueType'] = $this->queueTypeFromPayload($canonical);
        }

        return [
            'payload' => $canonical,
            'errors' => $this->uniqueErrors($errors),
            'game' => $game,
            'service' => $service,
            'addons' => $metadataAddons,
        ];
    }

    public function failurePayload(array $selection): array
    {
        $payload = (array) ($selection['payload'] ?? []);

        return [
            'gameSlug' => $payload['gameSlug'] ?? GameCatalog::DEFAULT_GAME_SLUG,
            'game' => $payload['game'] ?? $this->gameCatalog->gameName($payload['gameSlug'] ?? null),
            'serviceSlug' => $payload['serviceSlug'] ?? null,
            'serviceType' => $payload['serviceType'] ?? $payload['orderType'] ?? null,
            'orderType' => $payload['orderType'] ?? $payload['serviceType'] ?? null,
            'currentRank' => $payload['currentRank'] ?? null,
            'currentDivision' => $payload['currentDivision'] ?? null,
            'desiredRank' => $payload['desiredRank'] ?? null,
            'desiredDivision' => $payload['desiredDivision'] ?? null,
            'targetRank' => $payload['targetRank'] ?? null,
            'targetDivision' => $payload['targetDivision'] ?? null,
            'currentLevel' => $payload['currentLevel'] ?? null,
            'desiredLevel' => $payload['desiredLevel'] ?? null,
            'queueType' => $payload['queueType'] ?? 'normal',
            'boostMode' => $payload['boostMode'] ?? $payload['queueType'] ?? 'normal',
            'accountType' => $payload['accountType'] ?? null,
            'selectedOptions' => $payload['selectedOptions'] ?? [],
            'requestedAddons' => $payload['requestedAddons'] ?? $payload['selectedAddons'] ?? [],
            'selectedAddons' => $payload['selectedAddons'] ?? [],
            'addons' => [],
            'disabledAddons' => [],
            'disabledAddonReasons' => [],
            'specificAgents' => $payload['specificAgents'] ?? [],
            'oneTrickAgent' => $payload['oneTrickAgent'] ?? [],
            'basePrice' => 0,
            'rankPath' => [],
            'addonBreakdown' => [],
            'subtotalBeforeModifiers' => 0,
            'subtotalAfterRR' => 0,
            'subtotalAfterAddons' => 0,
            'subtotalAfterGlobalModifiers' => 0,
            'finalPrice' => 0,
            'validationErrors' => $this->uniqueErrors((array) ($selection['errors'] ?? [])),
            'pricing' => [
                'base' => 0,
                'basePrice' => 0,
                'subtotal' => 0,
                'subtotalBeforeModifiers' => 0,
                'subtotalAfterRR' => 0,
                'subtotalAfterAddons' => 0,
                'subtotalAfterGlobalModifiers' => 0,
                'addons' => 0,
                'fee' => 0,
                'tax' => 0,
                'total' => 0,
                'finalPrice' => 0,
                'currency' => 'USD',
            ],
            'modifiers' => [],
        ];
    }

    public function calculatorMetadata(array $payload, array $pricingEvidence = []): array
    {
        $selection = $this->canonicalizePayload($payload);
        $canonical = (array) ($selection['payload'] ?? $payload);
        $game = (array) ($selection['game'] ?? []);
        $service = (array) ($selection['service'] ?? []);

        return [
            'game' => [
                'id' => $game['id'] ?? $this->gameCatalog->gameId($canonical['gameSlug'] ?? null),
                'slug' => (string) ($canonical['gameSlug'] ?? $game['slug'] ?? GameCatalog::DEFAULT_GAME_SLUG),
                'name' => (string) ($canonical['game'] ?? $game['name'] ?? $this->gameCatalog->gameName($canonical['gameSlug'] ?? null)),
            ],
            'service' => [
                'id' => $service['id'] ?? $this->gameCatalog->serviceId($canonical['gameSlug'] ?? null, $canonical['serviceSlug'] ?? $canonical['serviceType'] ?? null),
                'slug' => (string) ($canonical['serviceSlug'] ?? $service['slug'] ?? ''),
                'name' => (string) ($canonical['serviceType'] ?? $canonical['orderType'] ?? $service['name'] ?? ''),
                'kind' => (string) ($canonical['serviceKind'] ?? $service['kind'] ?? ''),
            ],
            'selectedOptions' => $this->selectedOptionsMetadata($canonical),
            'addons' => $selection['addons'] ?? [],
            'pricing' => [
                'finalPriceCents' => (int) ($canonical['finalPriceCents'] ?? data_get($canonical, 'pricing.finalPriceCents', data_get($pricingEvidence, 'finalPriceCents', 0))),
                'finalPrice' => (float) data_get($canonical, 'pricing.total', $canonical['finalPrice'] ?? data_get($pricingEvidence, 'finalPrice', 0)),
                'currency' => (string) data_get($canonical, 'pricing.currency', data_get($pricingEvidence, 'currency', 'USD')),
                'config' => (array) ($canonical['pricingConfig'] ?? data_get($pricingEvidence, 'pricingConfig', [])),
            ],
        ];
    }

    protected function seedPayload(array $game, array $service): array
    {
        $defaults = (array) ($game['defaults'] ?? []);
        $ranks = array_values((array) ($game['rankOptions'] ?? []));
        $currentRank = $this->validOption($defaults['currentRank'] ?? null, $ranks) ?? ($ranks[0] ?? null);
        $desiredRank = $this->validOption($defaults['desiredRank'] ?? null, $ranks) ?? ($ranks[1] ?? $currentRank);
        $serviceKind = (string) ($service['kind'] ?? '');
        $selectedOptions = [
            'game' => $game['name'] ?? null,
            'service' => $service['name'] ?? null,
        ];

        return array_filter([
            'gameSlug' => $game['slug'] ?? GameCatalog::DEFAULT_GAME_SLUG,
            'game' => $game['name'] ?? $this->gameCatalog->gameName($game['slug'] ?? null),
            'serviceSlug' => $service['slug'] ?? null,
            'serviceType' => $service['name'] ?? null,
            'orderType' => $service['name'] ?? null,
            'serviceKind' => $serviceKind,
            'currentRank' => $currentRank,
            'currentDivision' => $currentRank,
            'desiredRank' => $serviceKind === 'radiant_boost' ? 'Radiant' : $desiredRank,
            'targetRank' => $serviceKind === 'radiant_boost' ? 'Radiant' : $desiredRank,
            'targetDivision' => $serviceKind === 'radiant_boost' ? 'Radiant' : $desiredRank,
            'desiredDivision' => $serviceKind === 'radiant_boost' ? 'Radiant' : $desiredRank,
            'currentRR' => 0,
            'avgRRPerWin' => '18',
            'averageRR' => '17-18 RR',
            'numberOfWins' => $serviceKind === 'ranked_wins' ? 1 : null,
            'numberOfPlacementGames' => $serviceKind === 'placement_matches' ? 5 : null,
            'queueType' => 'normal',
            'boostMode' => 'normal',
            'accountType' => 'Account Shared',
            'region' => 'EU',
            'platform' => 'PC',
            'selectedAddons' => [],
            'addons' => [],
            'selectedOptions' => array_filter($selectedOptions),
        ], static fn (mixed $value): bool => $value !== null);
    }

    protected function publicGame(string $slug): ?array
    {
        $game = $this->gameCatalog->game($slug);

        if ($game === []) {
            return null;
        }

        if (($game['status'] ?? null) !== 'published') {
            return null;
        }

        return $game;
    }

    protected function resolveService(array $game, array $payload, bool $useDefault): ?array
    {
        $services = collect((array) ($game['services'] ?? []))
            ->filter(fn (array $service): bool => ($service['status'] ?? 'published') === 'published')
            ->values();
        $serviceSlug = Str::slug((string) ($payload['serviceSlug'] ?? $payload['service_slug'] ?? ''));

        if ($serviceSlug !== '') {
            return $services->first(fn (array $service): bool => (string) ($service['slug'] ?? '') === $serviceSlug);
        }

        $needle = $this->normalizeComparable(
            $payload['serviceType']
            ?? $payload['orderType']
            ?? $payload['service']
            ?? null
        );

        if ($needle !== '') {
            $service = $services->first(function (array $candidate) use ($needle): bool {
                return in_array($needle, $this->serviceComparableValues($candidate), true);
            });

            if ($service !== null) {
                return $service;
            }
        }

        return $useDefault ? $services->first() : null;
    }

    protected function defaultService(array $game): ?array
    {
        return $this->resolveService($game, [], useDefault: true);
    }

    protected function hasServiceConflict(array $service, array $payload): bool
    {
        if (! $this->filled($payload['serviceSlug'] ?? $payload['service_slug'] ?? null)) {
            return false;
        }

        $candidate = $this->normalizeComparable($payload['serviceType'] ?? $payload['orderType'] ?? null);

        if ($candidate === '') {
            return false;
        }

        return ! in_array($candidate, $this->serviceComparableValues($service), true);
    }

    protected function serviceComparableValues(array $service): array
    {
        return collect([
            $service['slug'] ?? null,
            $service['name'] ?? null,
            $service['kind'] ?? null,
            ...((array) data_get($service, 'metadata.aliases', [])),
        ])
            ->map(fn (mixed $value): string => $this->normalizeComparable($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function canonicalizeAddons(array $game, ?array $service, array $payload): array
    {
        $requested = collect([
            ...$this->stringArray($payload['selectedAddons'] ?? []),
            ...$this->stringArray($payload['selected_addons'] ?? []),
            ...$this->stringArray($payload['addons'] ?? []),
            ...$this->stringArray($payload['requestedAddons'] ?? []),
        ]);

        if (($payload['duoQueue'] ?? $payload['duo_queue'] ?? false) || $this->queueTypeFromPayload($payload) === 'self_play') {
            $requested->push('Duo Queue');
        }

        if ($payload['streamGames'] ?? $payload['stream_games'] ?? false) {
            $requested->push('Streamed Games');
        }

        if ($payload['expressDelivery'] ?? $payload['express_delivery'] ?? false) {
            $requested->push('Express Delivery');
        }

        $requested = $requested->map(fn (string $value): string => trim($value))->filter()->unique()->values();

        if ($requested->isEmpty()) {
            return [[], [], []];
        }

        if (($game['slug'] ?? null) === GameCatalog::DEFAULT_GAME_SLUG && (int) ($service['id'] ?? 0) === 0) {
            $labels = BoostingCatalog::normalizeAddons($requested->all());

            return [$labels, [], $this->metadataAddonsFromLabels($labels, [])];
        }

        if ($service === null) {
            return [[], ['selectedAddons' => ['Select a valid addon for this service.']], []];
        }

        $serviceLookup = $this->addonLookup((array) ($service['addons'] ?? []));
        $gameLookup = $this->addonLookup((array) ($game['addons'] ?? []));
        $labels = [];
        $errors = [];

        foreach ($requested as $value) {
            $normalized = $this->normalizeComparable($value);
            $addon = $serviceLookup[$normalized] ?? null;

            if (is_array($addon)) {
                $labels[] = (string) $addon['label'];

                continue;
            }

            $gameAddon = $gameLookup[$normalized] ?? null;

            if (is_array($gameAddon)) {
                $errors['selectedAddons'][] = "{$gameAddon['label']} is not available for this service.";

                continue;
            }

            $errors['selectedAddons'][] = 'Select a valid addon for this service.';
        }

        $labels = array_values(array_unique($labels));

        return [$labels, $errors, $this->metadataAddonsFromLabels($labels, array_values($serviceLookup))];
    }

    protected function addonLookup(array $addons): array
    {
        $lookup = [];

        foreach ($addons as $addon) {
            foreach ([
                $addon['slug'] ?? null,
                $addon['label'] ?? null,
                ...((array) data_get($addon, 'metadata.aliases', [])),
            ] as $candidate) {
                $normalized = $this->normalizeComparable($candidate);

                if ($normalized !== '') {
                    $lookup[$normalized] = [
                        'id' => $addon['id'] ?? null,
                        'slug' => $addon['slug'] ?? null,
                        'label' => (string) ($addon['label'] ?? $candidate),
                    ];
                }
            }
        }

        foreach ([
            'duo' => 'Duo Queue',
            'duo self play' => 'Duo Queue',
            'self play' => 'Duo Queue',
            'streaming' => 'Streamed Games',
            'live streaming' => 'Streamed Games',
            'express order' => 'Express Delivery',
            'express' => 'Express Delivery',
            'priority' => 'Priority Order',
            'win streak' => 'Win Streak Guarantee',
        ] as $alias => $label) {
            $record = collect($lookup)->firstWhere('label', $label);

            if ($record !== null) {
                $lookup[$this->normalizeComparable($alias)] = $record;
            }
        }

        return $lookup;
    }

    protected function metadataAddonsFromLabels(array $labels, array $records): array
    {
        $byLabel = collect($records)->keyBy('label');

        return collect($labels)
            ->map(function (string $label) use ($byLabel): array {
                $record = (array) ($byLabel->get($label) ?? []);

                return [
                    'id' => $record['id'] ?? null,
                    'slug' => $record['slug'] ?? Str::slug($label),
                    'label' => $label,
                ];
            })
            ->values()
            ->all();
    }

    protected function selectedOptionsMetadata(array $payload): array
    {
        $options = [];

        foreach ((array) ($payload['selectedOptions'] ?? []) as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $display = trim((string) $value);
                $options[(string) $key] = [
                    'label' => Str::headline((string) $key),
                    'value' => $value,
                    'display' => $display,
                ];
            }
        }

        foreach ([
            'game' => ['Game', $payload['gameSlug'] ?? null, $payload['game'] ?? null],
            'service' => ['Service', $payload['serviceSlug'] ?? null, $payload['serviceType'] ?? $payload['orderType'] ?? null],
            'currentRank' => ['Current rank', $payload['currentDivision'] ?? $payload['currentRank'] ?? null, $payload['currentDivision'] ?? $payload['currentRank'] ?? null],
            'desiredRank' => ['Desired rank', $payload['desiredDivision'] ?? $payload['targetDivision'] ?? $payload['desiredRank'] ?? $payload['targetRank'] ?? null, $payload['desiredDivision'] ?? $payload['targetDivision'] ?? $payload['desiredRank'] ?? $payload['targetRank'] ?? null],
            'currentLevel' => ['Current level', $payload['currentLevel'] ?? null, $payload['currentLevel'] ?? null],
            'desiredLevel' => ['Desired level', $payload['desiredLevel'] ?? null, $payload['desiredLevel'] ?? null],
            'queueType' => ['Queue type', $payload['queueType'] ?? $payload['boostMode'] ?? null, $payload['accountType'] ?? $payload['queueType'] ?? $payload['boostMode'] ?? null],
            'region' => ['Region', $payload['region'] ?? null, $payload['region'] ?? null],
            'platform' => ['Platform', $payload['platform'] ?? null, $payload['platform'] ?? null],
            'wins' => ['Wins', $payload['numberOfWins'] ?? $payload['wins'] ?? null, $payload['numberOfWins'] ?? $payload['wins'] ?? null],
            'placements' => ['Placement matches', $payload['numberOfPlacementGames'] ?? $payload['placementGames'] ?? null, $payload['numberOfPlacementGames'] ?? $payload['placementGames'] ?? null],
        ] as $key => [$label, $value, $display]) {
            if ($display !== null && $display !== '') {
                $options[$key] = [
                    'label' => $label,
                    'value' => $value,
                    'display' => (string) $display,
                ];
            }
        }

        return $options;
    }

    protected function validOption(mixed $value, array $options): ?string
    {
        $needle = $this->normalizeComparable($value);

        if ($needle === '') {
            return null;
        }

        foreach ($options as $option) {
            if ($this->normalizeComparable($option) === $needle) {
                return (string) $option;
            }
        }

        return null;
    }

    protected function queueTypeFromPayload(array $payload): string
    {
        $value = $payload['queueType']
            ?? $payload['queue_type']
            ?? $payload['boostMode']
            ?? $payload['accountType']
            ?? $payload['playType']
            ?? null;
        $normalized = Str::of((string) $value)
            ->lower()
            ->replace(['-', '/', '\\'], '_')
            ->replace(' ', '_')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->value();

        return in_array($normalized, ['duo', 'duo_queue', 'self_play', 'duo_self_play', 'self_play_duo'], true)
            ? 'self_play'
            : 'normal';
    }

    protected function uniqueErrors(array $errors): array
    {
        return collect($errors)
            ->map(fn (mixed $messages): array => array_values(array_unique(Arr::flatten((array) $messages))))
            ->filter()
            ->all();
    }

    protected function stringArray(mixed $value): array
    {
        return collect(is_string($value) ? explode(',', $value) : (array) $value)
            ->map(fn (mixed $entry): string => is_scalar($entry) || $entry instanceof \Stringable ? trim((string) $entry) : '')
            ->filter()
            ->values()
            ->all();
    }

    protected function normalizeComparable(mixed $value): string
    {
        if (! is_scalar($value) && ! $value instanceof \Stringable) {
            return '';
        }

        return Str::of((string) $value)
            ->lower()
            ->replace('_', '-')
            ->replaceMatches('/[()+$%]/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();
    }

    protected function filled(mixed $value): bool
    {
        return is_scalar($value) || $value instanceof \Stringable
            ? trim((string) $value) !== ''
            : false;
    }
}
