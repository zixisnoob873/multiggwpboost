<?php

namespace App\Support;

use App\Models\Game;
use App\Models\GameAddon;
use App\Models\GameService;
use App\Queries\Marketplace\GameRepository;
use App\Queries\Marketplace\ServiceRepository;
use Illuminate\Support\Str;

class GameCatalog
{
    public const DEFAULT_GAME_SLUG = 'valorant';

    public function __construct(
        protected GameRepository $games,
        protected ServiceRepository $services,
    ) {}

    public function defaultSlug(): string
    {
        return self::DEFAULT_GAME_SLUG;
    }

    public function normalizeSlug(mixed $value): string
    {
        $slug = Str::slug(trim((string) $value));

        return $slug !== '' ? $slug : self::DEFAULT_GAME_SLUG;
    }

    public function resolveSlugFromPayload(array $payload): string
    {
        return $this->normalizeSlug(
            $payload['gameSlug']
            ?? $payload['game_slug']
            ?? $payload['game']
            ?? data_get($payload, 'game.slug')
            ?? self::DEFAULT_GAME_SLUG
        );
    }

    public function exists(mixed $slug): bool
    {
        $gameSlug = $this->normalizeSlug($slug);

        if ($gameSlug === self::DEFAULT_GAME_SLUG) {
            return true;
        }

        return $this->games->findBySlug($gameSlug) instanceof Game;
    }

    public function all(bool $includeDrafts = false): array
    {
        $games = $this->games->games($includeDrafts)
            ->map(fn (Game $game): array => $this->payloadFromModel($game))
            ->values()
            ->all();

        return $games !== [] ? $games : [$this->fallbackGame()];
    }

    public function game(mixed $slug = null): array
    {
        $gameSlug = $this->normalizeSlug($slug ?? self::DEFAULT_GAME_SLUG);
        $model = $this->model($gameSlug);

        if ($model instanceof Game) {
            return $this->payloadFromModel($model);
        }

        return $gameSlug === self::DEFAULT_GAME_SLUG ? $this->fallbackGame() : [];
    }

    public function gameName(mixed $slug = null): string
    {
        $game = $this->game($slug);

        return (string) ($game['name'] ?? 'Valorant');
    }

    public function gameShortName(mixed $slug = null): string
    {
        $game = $this->game($slug);

        return (string) ($game['shortName'] ?? $game['name'] ?? 'VALORANT');
    }

    public function gameId(mixed $slug = null): ?int
    {
        $model = $this->model($this->normalizeSlug($slug ?? self::DEFAULT_GAME_SLUG));

        return $model?->id;
    }

    public function serviceId(mixed $gameSlug, mixed $service): ?int
    {
        return $this->services->findComparableServiceId($gameSlug, $service);
    }

    public function serviceKind(mixed $gameSlug, mixed $service): ?string
    {
        $game = $this->game($gameSlug);
        $serviceNeedle = $this->normalizeComparable($service);

        foreach ($game['services'] ?? [] as $candidate) {
            if (in_array($serviceNeedle, [
                $this->normalizeComparable($candidate['slug'] ?? null),
                $this->normalizeComparable($candidate['name'] ?? null),
                $this->normalizeComparable($candidate['kind'] ?? null),
            ], true)) {
                return (string) ($candidate['kind'] ?? '');
            }
        }

        return null;
    }

    public function publicPayload(mixed $slug = null, array $pricingPreview = []): array
    {
        $game = $this->game($slug);

        if ($game === []) {
            return [];
        }

        return [
            'game' => $game,
            'gameSlug' => $game['slug'],
            'gameName' => $game['name'],
            'gameShortName' => $game['shortName'],
            'services' => $game['serviceOptions'],
            'ranks' => $game['rankOptions'],
            'ranksWithRadiant' => $game['rankOptionsWithRadiant'],
            'regions' => BoostingCatalog::regions(),
            'platforms' => BoostingCatalog::platforms(),
            'boostModes' => BoostingCatalog::boostModes(),
            'averageRrOptions' => BoostingCatalog::averageRrOptions(),
            'defaults' => [
                'currentRank' => $game['defaults']['currentRank'] ?? BoostingCatalog::defaultCurrentRank(),
                'desiredRank' => $game['defaults']['desiredRank'] ?? BoostingCatalog::defaultDesiredRank(),
            ],
            'addons' => $game['addons'] !== [] ? $game['addons'] : BoostingCatalog::addons(),
            'addonRules' => OrderAddonRules::frontendConfig(),
            'pricingPreview' => $pricingPreview,
        ];
    }

    protected function model(string $slug): ?Game
    {
        return $this->games->findBySlug($slug);
    }

    protected function payloadFromModel(Game $game): array
    {
        $services = $game->relationLoaded('services') ? $game->services : $game->services()->get();
        $ranks = $game->relationLoaded('ranks') ? $game->ranks : $game->ranks()->get();
        $addons = $game->relationLoaded('addons') ? $game->addons : $game->addons()->get();
        $pricingRules = $game->relationLoaded('pricingRules') ? $game->pricingRules : collect();
        $services = $services->where('status', Game::STATUS_PUBLISHED)->values();
        $addons = $addons->where('status', Game::STATUS_PUBLISHED)->values();
        $rankLabels = $ranks->pluck('label')->filter()->values()->all();

        if ($game->slug === self::DEFAULT_GAME_SLUG && $rankLabels === []) {
            $rankLabels = BoostingCatalog::rankOptions();
        }

        return [
            'id' => $game->id,
            'slug' => $game->slug,
            'name' => $game->name,
            'shortName' => $game->short_name ?: $game->name,
            'description' => $game->description,
            'status' => $game->status,
            'sortOrder' => (int) $game->sort_order,
            'category' => $game->category ? [
                'id' => $game->category->id,
                'slug' => $game->category->slug,
                'name' => $game->category->name,
            ] : null,
            'assets' => $game->assets ?? [],
            'metadata' => $game->metadata ?? [],
            'seo' => $this->seoPayload($game->seoMetadata),
            'defaults' => [
                'currentRank' => data_get($game->metadata, 'default_current_rank', BoostingCatalog::defaultCurrentRank()),
                'desiredRank' => data_get($game->metadata, 'default_desired_rank', BoostingCatalog::defaultDesiredRank()),
            ],
            'pricingRules' => $this->pricingRulesPayload($pricingRules),
            'services' => $services->map(fn (GameService $service): array => [
                'id' => $service->id,
                'slug' => $service->slug,
                'name' => $service->name,
                'kind' => $service->kind,
                'description' => $service->description,
                'status' => $service->status,
                'sortOrder' => (int) $service->sort_order,
                'config' => $service->config ?? [],
                'metadata' => $service->metadata ?? [],
                'seo' => $this->seoPayload($service->seoMetadata),
                'addons' => $service->relationLoaded('addons')
                    ? $service->addons
                        ->where('status', Game::STATUS_PUBLISHED)
                        ->map(fn (GameAddon $addon): array => [
                            'id' => $addon->id,
                            'slug' => $addon->slug,
                            'label' => $addon->label,
                        ])
                        ->values()
                        ->all()
                    : [],
                'pricingRules' => $this->pricingRulesPayload(
                    $service->relationLoaded('pricingRules') ? $service->pricingRules : collect()
                ),
            ])->values()->all() ?: $this->fallbackServices(),
            'serviceOptions' => $services->pluck('name')->filter()->values()->all() ?: BoostingCatalog::serviceOptions(),
            'ranks' => $ranks->map(fn ($rank): array => [
                'id' => $rank->id,
                'slug' => $rank->slug,
                'label' => $rank->label,
                'division' => $rank->division,
                'sortOrder' => (int) $rank->sort_order,
                'iconUrl' => $rank->icon_url,
                'iconPath' => $rank->icon_path,
                'metadata' => $rank->metadata ?? [],
            ])->values()->all(),
            'rankOptions' => $rankLabels,
            'rankOptionsWithRadiant' => $game->slug === self::DEFAULT_GAME_SLUG && ! in_array('Radiant', $rankLabels, true)
                ? [...$rankLabels, 'Radiant']
                : $rankLabels,
            'addons' => $addons->map(fn (GameAddon $addon): array => [
                'id' => $addon->id,
                'slug' => $addon->slug,
                'label' => $addon->label,
                'description' => $addon->description,
                'icon' => $addon->icon,
                'status' => $addon->status,
                'sortOrder' => (int) $addon->sort_order,
                'pricingType' => $addon->pricing_type,
                'pricingValue' => $addon->pricing_value !== null ? (float) $addon->pricing_value : null,
                'pricingRule' => $addon->pricing_rule ?? [],
                'availabilityRule' => $addon->availability_rule ?? [],
                'metadata' => $addon->metadata ?? [],
                'services' => $addon->relationLoaded('services')
                    ? $addon->services
                        ->where('status', Game::STATUS_PUBLISHED)
                        ->map(fn (GameService $service): array => [
                            'id' => $service->id,
                            'slug' => $service->slug,
                            'name' => $service->name,
                            'kind' => $service->kind,
                        ])
                        ->values()
                        ->all()
                    : [],
                'pricingRules' => $this->pricingRulesPayload(
                    $addon->relationLoaded('pricingRules') ? $addon->pricingRules : collect()
                ),
            ])->values()->all(),
        ];
    }

    protected function fallbackGame(): array
    {
        $rankLabels = BoostingCatalog::rankOptions();

        return [
            'id' => null,
            'slug' => self::DEFAULT_GAME_SLUG,
            'name' => 'Valorant',
            'shortName' => 'VALORANT',
            'description' => null,
            'status' => Game::STATUS_PUBLISHED,
            'sortOrder' => 1,
            'category' => null,
            'assets' => [],
            'metadata' => [],
            'seo' => [],
            'defaults' => [
                'currentRank' => BoostingCatalog::defaultCurrentRank(),
                'desiredRank' => BoostingCatalog::defaultDesiredRank(),
            ],
            'pricingRules' => [],
            'services' => $this->fallbackServices(),
            'serviceOptions' => BoostingCatalog::serviceOptions(),
            'ranks' => collect($rankLabels)->map(fn (string $rank, int $index): array => [
                'id' => null,
                'slug' => Str::slug($rank),
                'label' => $rank,
                'division' => null,
                'sortOrder' => $index + 1,
                'iconUrl' => BoostingCatalog::rankIconUrl($rank),
                'iconPath' => null,
                'metadata' => [],
            ])->all(),
            'rankOptions' => $rankLabels,
            'rankOptionsWithRadiant' => BoostingCatalog::rankOptionsWithRadiant(),
            'addons' => BoostingCatalog::addons(),
        ];
    }

    protected function fallbackServices(): array
    {
        return collect(config('pricing.services', []))
            ->map(fn (array $service, string $name): array => [
                'id' => null,
                'slug' => Str::slug($name),
                'name' => $name,
                'kind' => (string) ($service['kind'] ?? Str::slug($name, '_')),
                'description' => null,
                'status' => Game::STATUS_PUBLISHED,
                'sortOrder' => 0,
                'config' => $service,
                'metadata' => [],
                'seo' => [],
                'addons' => [],
                'pricingRules' => [],
            ])
            ->values()
            ->all();
    }

    protected function pricingRulesPayload(mixed $rules): array
    {
        return collect($rules)
            ->map(fn ($rule): array => [
                'id' => $rule->id,
                'slug' => $rule->slug,
                'name' => $rule->name,
                'scope' => $rule->scope,
                'calculatorKey' => $rule->calculator_key,
                'pricingType' => $rule->pricing_type,
                'amount' => $rule->amount !== null ? (float) $rule->amount : null,
                'currency' => $rule->currency,
                'minQuantity' => $rule->min_quantity,
                'maxQuantity' => $rule->max_quantity,
                'status' => $rule->status,
                'sortOrder' => (int) $rule->sort_order,
                'conditions' => $rule->conditions ?? [],
                'tiers' => $rule->tiers ?? [],
                'metadata' => $rule->metadata ?? [],
            ])
            ->values()
            ->all();
    }

    protected function seoPayload(mixed $seoMetadata): array
    {
        if (! $seoMetadata) {
            return [];
        }

        return method_exists($seoMetadata, 'payload')
            ? array_filter($seoMetadata->payload(), static fn (mixed $value): bool => $value !== null && $value !== [])
            : [];
    }

    protected function normalizeComparable(mixed $value): string
    {
        return Str::of((string) $value)
            ->lower()
            ->replace('_', '-')
            ->replaceMatches('/[()+$%]/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();
    }
}
