<?php

namespace App\Support;

use App\Models\AddonSetting;
use App\Support\Pricing\ValorantPricingConfigRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class BoostingCatalog
{
    protected const RANK_ICON_BASE_URL = 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/%d/largeicon.png';

    protected const ACCOUNT_SHARED_BOOST_MODE_LABEL = 'Account Shared';

    protected const SELF_PLAY_BOOST_MODE_LABEL = 'Duo / Self-Play';

    protected const BONUS_WIN_ADDON_LABEL = 'Bonus Win';

    protected const EXPRESS_ORDER_ADDON_LABEL = 'Express Order';

    protected const SPECIFIC_AGENTS_ADDON_LABEL = 'Specific Agents';

    protected const ONE_TRICK_AGENT_ADDON_LABEL = 'One-Trick Agent';

    protected const SOLO_QUEUE_ONLY_ADDON_LABEL = 'Solo-Queue Only';

    protected const NO_FIVE_STACK_ADDON_LABEL = 'No 5-Stack';

    protected static ?array $serviceOptions = null;

    protected static ?array $rankOptions = null;

    protected static ?array $rankOptionsWithRadiant = null;

    protected static ?array $regions = null;

    protected static ?array $platforms = null;

    protected static ?array $boostModes = null;

    protected static ?array $averageRrOptions = null;

    protected static ?array $boostModeOptions = null;

    protected static ?array $averageRrOptionChoices = null;

    protected static ?array $addonDefinitions = null;

    protected static ?array $addons = null;

    protected static ?array $frontendPayload = null;

    protected static ?Collection $addonSettingsBySlugCache = null;

    protected static ?bool $hasAddonSettingsTableCache = null;

    protected static ?array $normalizedServiceLookup = null;

    protected static ?array $normalizedAddonLookup = null;

    protected static ?array $canonicalRankLookup = null;

    protected static ?array $rankIndexLookup = null;

    public static function serviceOptions(): array
    {
        return self::$serviceOptions ??= array_values(config('boosting.services', []));
    }

    public static function rankOptions(): array
    {
        return self::$rankOptions ??= array_values(config('boosting.ranks', []));
    }

    public static function rankOptionsWithRadiant(): array
    {
        if (self::$rankOptionsWithRadiant !== null) {
            return self::$rankOptionsWithRadiant;
        }

        $ranks = self::rankOptions();

        return self::$rankOptionsWithRadiant = in_array('Radiant', $ranks, true)
            ? $ranks
            : [...$ranks, 'Radiant'];
    }

    public static function defaultCurrentRank(): string
    {
        return (string) config('boosting.default_current_rank', 'Gold III');
    }

    public static function defaultDesiredRank(): string
    {
        return (string) config('boosting.default_desired_rank', 'Platinum III');
    }

    public static function regions(): array
    {
        return self::$regions ??= array_values(config('boosting.regions', []));
    }

    public static function platforms(): array
    {
        return self::$platforms ??= array_values(config('boosting.platforms', []));
    }

    public static function boostModes(): array
    {
        return self::$boostModes ??= array_column(self::boostModeOptions(), 'label');
    }

    public static function averageRrOptions(): array
    {
        return self::$averageRrOptions ??= array_column(self::averageRrOptionChoices(), 'label');
    }

    public static function boostModeOptions(): array
    {
        if (self::$boostModeOptions !== null) {
            return self::$boostModeOptions;
        }

        $labels = (array) self::pricing('labels.boost_modes', []);

        return self::$boostModeOptions = collect(array_keys((array) self::pricing('modifiers.boost_mode', [])))
            ->map(fn (string $code): array => [
                'value' => $code,
                'label' => (string) ($labels[$code] ?? Str::headline($code)),
            ])
            ->values()
            ->all();
    }

    public static function averageRrOptionChoices(): array
    {
        if (self::$averageRrOptionChoices !== null) {
            return self::$averageRrOptionChoices;
        }

        $labels = (array) self::pricing('labels.avg_rr', []);

        return self::$averageRrOptionChoices = collect(array_keys((array) self::pricing('rr_rules.avg_rr_modifiers', [])))
            ->map(fn (int|string $code): array => [
                'value' => (string) $code,
                'label' => (string) ($labels[$code] ?? "{$code} RR"),
            ])
            ->values()
            ->all();
    }

    public static function addonDefinitions(): array
    {
        if (self::$addonDefinitions !== null) {
            return self::$addonDefinitions;
        }

        return self::$addonDefinitions = collect(config('boosting.addons', []))
            ->map(fn (array $addon, string $slug) => [
                'slug' => $slug,
                'label' => $addon['label'],
                'description' => $addon['description'],
                'icon' => $addon['icon'] ?? null,
                'sort_order' => (int) ($addon['sort_order'] ?? 0),
                'aliases' => array_values($addon['aliases'] ?? []),
            ])
            ->sortBy('sort_order')
            ->values()
            ->all();
    }

    public static function addons(): array
    {
        if (self::$addons !== null) {
            return self::$addons;
        }

        $definitions = collect(self::addonDefinitions())->keyBy('slug');
        $settings = self::addonSettingsBySlug();

        return self::$addons = $definitions
            ->map(function (array $addon, string $slug) use ($settings) {
                /** @var AddonSetting|null $setting */
                $setting = $settings->get($slug);

                return [
                    'slug' => $slug,
                    'label' => $addon['label'],
                    'description' => $setting?->description ?: $addon['description'],
                    'icon' => $addon['icon'] ?? null,
                    'sort_order' => $addon['sort_order'],
                ];
            })
            ->sortBy('sort_order')
            ->values()
            ->all();
    }

    public static function addonSlugs(): array
    {
        return array_column(self::addonDefinitions(), 'slug');
    }

    public static function addonDefinitionBySlug(string $slug): ?array
    {
        foreach (self::addonDefinitions() as $addon) {
            if (($addon['slug'] ?? null) === $slug) {
                return $addon;
            }
        }

        return null;
    }

    public static function addonLabelBySlug(string $slug): ?string
    {
        return self::addonDefinitionBySlug($slug)['label'] ?? null;
    }

    public static function addonSlugByLabel(mixed $label): ?string
    {
        $normalizedLabel = self::normalizeAddon($label);

        if ($normalizedLabel === null) {
            return null;
        }

        foreach (self::addonDefinitions() as $addon) {
            if (($addon['label'] ?? null) === $normalizedLabel) {
                return $addon['slug'] ?? null;
            }
        }

        return null;
    }

    public static function allowedAddonLabels(): array
    {
        return array_column(self::addonDefinitions(), 'label');
    }

    public static function accountSharedBoostModeLabel(): string
    {
        return (string) self::pricing('labels.boost_modes.normal', self::ACCOUNT_SHARED_BOOST_MODE_LABEL);
    }

    public static function selfPlayBoostModeLabel(): string
    {
        return (string) self::pricing('labels.boost_modes.self_play', self::SELF_PLAY_BOOST_MODE_LABEL);
    }

    public static function bonusWinAddonLabel(): string
    {
        return self::BONUS_WIN_ADDON_LABEL;
    }

    public static function expressOrderAddonLabel(): string
    {
        return self::EXPRESS_ORDER_ADDON_LABEL;
    }

    public static function specificAgentsAddonLabel(): string
    {
        return self::SPECIFIC_AGENTS_ADDON_LABEL;
    }

    public static function oneTrickAgentAddonLabel(): string
    {
        return self::ONE_TRICK_AGENT_ADDON_LABEL;
    }

    public static function soloQueueOnlyAddonLabel(): string
    {
        return self::SOLO_QUEUE_ONLY_ADDON_LABEL;
    }

    public static function noFiveStackAddonLabel(): string
    {
        return self::NO_FIVE_STACK_ADDON_LABEL;
    }

    public static function selfPlayAllowedAddonLabels(): array
    {
        return [
            self::bonusWinAddonLabel(),
            self::expressOrderAddonLabel(),
        ];
    }

    public static function selfPlayDisabledAddonLabels(): array
    {
        return self::normalizeAddons(self::pricing('disabled_addons.self_play', []));
    }

    public static function normalizeServiceType(mixed $value): ?string
    {
        $needle = self::normalizeComparable($value);

        if ($needle === '') {
            return null;
        }

        return self::serviceLookup()[$needle] ?? null;
    }

    public static function serviceKind(mixed $serviceType): ?string
    {
        $service = self::normalizeServiceType($serviceType);

        return $service ? self::pricing("services.{$service}.kind") : null;
    }

    public static function normalizeBoostModeCode(mixed $value): ?string
    {
        $needle = Str::of((string) $value)
            ->trim()
            ->lower()
            ->replace(['-', '/', '\\'], '_')
            ->replace(' ', '_')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->value();

        return match ($needle) {
            'account_shared', 'normal' => 'normal',
            'self_play', 'duo', 'duo_self_play', 'self_play_duo' => 'self_play',
            default => self::boostModeCodeFromLabel($needle),
        };
    }

    public static function normalizeBoostModeLabel(mixed $value): ?string
    {
        $code = self::normalizeBoostModeCode($value);

        return $code ? self::pricing("labels.boost_modes.{$code}") : null;
    }

    public static function canonicalRankLabel(mixed $value): ?string
    {
        $needle = self::normalizeComparable($value);

        if ($needle === '') {
            return null;
        }

        $lookup = self::rankLookup();

        if (array_key_exists($needle, $lookup)) {
            return $lookup[$needle];
        }

        $numericNeedle = str_replace([' iii', ' ii', ' i'], [' 3', ' 2', ' 1'], $needle);

        return $lookup[$numericNeedle] ?? null;
    }

    public static function rankIndexFor(mixed $value): ?int
    {
        $rank = self::canonicalRankLabel($value);

        if ($rank === null) {
            return null;
        }

        return self::rankIndexMap()[$rank] ?? null;
    }

    public static function rankAtOrAbove(mixed $value, string $threshold): bool
    {
        $rankIndex = self::rankIndexFor($value);
        $thresholdIndex = self::rankIndexFor($threshold);

        return $rankIndex !== null && $thresholdIndex !== null && $rankIndex >= $thresholdIndex;
    }

    public static function agentSelectionAddons(): array
    {
        return [
            'specificAgents' => [
                'key' => 'specificAgents',
                'slug' => 'specific-agents',
                'label' => self::specificAgentsAddonLabel(),
                'input_name' => 'specific_agents',
                'min' => 3,
                'max' => null,
                'single_select' => false,
                'title' => 'Choose specific agents',
                'description' => 'Pick at least 3 agents you want prioritized for this order. The selection only applies while the Specific Agents addon is active.',
                'summary_empty' => 'No specific agents selected yet.',
                'button_label' => 'Manage Agents',
                'view_title' => 'Selected specific agents',
                'view_description' => 'Review the agents attached to this order.',
                'required_message' => 'Select at least 3 specific agents.',
                'invalid_message' => 'Specific agent selections must use supported Valorant agents.',
                'duplicate_message' => 'Each specific agent can only be selected once.',
                'addon_required_message' => 'Specific agent selections require the Specific Agents addon.',
                'disabled_message' => 'Specific Agents is unavailable for Duo / Self-Play orders.',
            ],
            'oneTrickAgent' => [
                'key' => 'oneTrickAgent',
                'slug' => 'one-trick-agent',
                'label' => self::oneTrickAgentAddonLabel(),
                'input_name' => 'one_trick_agent',
                'min' => 1,
                'max' => 1,
                'single_select' => true,
                'title' => 'Choose your one-trick agent',
                'description' => 'Pick exactly 1 main agent you want the booster to focus on for this order. The selection only applies while the One-Trick Agent addon is active.',
                'summary_empty' => 'No one-trick agent selected yet.',
                'button_label' => 'Choose Agent',
                'view_title' => 'Selected one-trick agent',
                'view_description' => 'Review the one-trick agent attached to this order.',
                'required_message' => 'Select exactly 1 one-trick agent.',
                'invalid_message' => 'One-trick agent selections must use supported Valorant agents.',
                'duplicate_message' => 'The one-trick agent selection can only include one unique agent.',
                'addon_required_message' => 'One-trick agent selections require the One-Trick Agent addon.',
                'disabled_message' => 'One-Trick Agent is unavailable for Duo / Self-Play orders.',
            ],
        ];
    }

    public static function agentSelectionAddon(string $key): ?array
    {
        return self::agentSelectionAddons()[$key] ?? null;
    }

    public static function agentSelectionAddonBySlug(string $slug): ?array
    {
        foreach (self::agentSelectionAddons() as $definition) {
            if (($definition['slug'] ?? null) === $slug) {
                return $definition;
            }
        }

        return null;
    }

    public static function normalizeSpecificAgents(mixed $values): array
    {
        return ValorantAgentCatalog::normalizeSelection($values);
    }

    public static function normalizeOneTrickAgent(mixed $values): array
    {
        return ValorantAgentCatalog::normalizeSelection($values);
    }

    public static function hasAddon(mixed $values, string $label): bool
    {
        return in_array($label, self::normalizeAddons($values), true);
    }

    public static function addonSupportsService(string $addonSlug, mixed $serviceType): bool
    {
        $addon = self::addonDefinitionBySlug($addonSlug);
        $normalizedService = self::normalizeServiceType($serviceType);

        if (! $addon || ! $normalizedService) {
            return false;
        }

        $supportedServices = array_values(array_filter(
            array_map(fn ($service) => self::normalizeServiceType($service), $addon['services'] ?? self::serviceOptions())
        ));

        return in_array($normalizedService, $supportedServices, true);
    }

    public static function addonSettingsForAdmin(): array
    {
        self::syncAddonSettings();

        $settings = self::addonSettingsBySlug();

        return collect(self::addonDefinitions())
            ->map(function (array $addon) use ($settings) {
                /** @var AddonSetting|null $setting */
                $setting = $settings->get($addon['slug']);

                return [
                    'slug' => $addon['slug'],
                    'label' => $addon['label'],
                    'description' => $setting?->description ?: $addon['description'],
                    'icon' => $addon['icon'] ?? null,
                    'sort_order' => $addon['sort_order'],
                ];
            })
            ->all();
    }

    public static function syncAddonSettings(): void
    {
        if (! self::hasAddonSettingsTable()) {
            return;
        }

        foreach (self::addonDefinitions() as $addon) {
            $setting = AddonSetting::query()->firstOrCreate(
                ['slug' => $addon['slug']],
                [
                    'label' => $addon['label'],
                    'description' => $addon['description'],
                    'sort_order' => $addon['sort_order'],
                ]
            );

            $setting->fill([
                'label' => $addon['label'],
                'sort_order' => $addon['sort_order'],
            ]);

            if ($setting->isDirty()) {
                $setting->save();
            }
        }
    }

    public static function normalizeAddons(mixed $values): array
    {
        $items = collect(is_string($values) ? explode(',', $values) : (array) $values)
            ->flatten()
            ->map(fn ($value) => self::normalizeAddon($value))
            ->filter()
            ->unique()
            ->values();

        return $items->all();
    }

    public static function normalizeAddon(mixed $value): ?string
    {
        $normalized = self::normalizeComparable($value);

        if ($normalized === '') {
            return null;
        }

        return self::addonLookup()[$normalized] ?? null;
    }

    public static function addonDisplayLabel(mixed $value, bool $showPricing = false): string
    {
        $label = self::normalizeAddon($value) ?? trim((string) $value);

        if (! $showPricing || $label === '') {
            return $label;
        }

        $suffix = self::addonPercentageSuffix($label);

        return $suffix ? "{$label} {$suffix}" : $label;
    }

    public static function addonDisplayList(mixed $values, bool $showPricing = false): array
    {
        return collect(self::normalizeAddons($values))
            ->map(fn (string $label): string => self::addonDisplayLabel($label, $showPricing))
            ->values()
            ->all();
    }

    public static function addonPercentageSuffix(string $label): ?string
    {
        $definition = self::pricing("addons.{$label}");

        if (! is_array($definition) || ($definition['type'] ?? null) !== 'percent') {
            return null;
        }

        $value = (float) ($definition['value'] ?? 0);
        if ($value <= 0) {
            return null;
        }

        $percentage = $value * 100;
        $formatted = fmod($percentage, 1.0) === 0.0
            ? number_format($percentage, 0, '.', '')
            : rtrim(rtrim(number_format($percentage, 2, '.', ''), '0'), '.');

        return "(+{$formatted}%)";
    }

    public static function sanitizeOrderPayload(array $payload): array
    {
        if (array_key_exists('addons', $payload)) {
            $payload['addons'] = self::normalizeAddons($payload['addons']);
        }

        if (array_key_exists('selectedAddons', $payload)) {
            $payload['selectedAddons'] = self::normalizeAddons($payload['selectedAddons']);
        }

        if (array_key_exists('requestedAddons', $payload)) {
            $payload['requestedAddons'] = self::normalizeAddons($payload['requestedAddons']);
        }

        if (array_key_exists('disabledAddons', $payload)) {
            $payload['disabledAddons'] = self::normalizeAddons($payload['disabledAddons']);
        }

        if (array_key_exists('specificAgents', $payload) || array_key_exists('specific_agents', $payload)) {
            $payload['specificAgents'] = self::normalizeSpecificAgents(
                $payload['specificAgents'] ?? $payload['specific_agents'] ?? []
            );
        }

        if (array_key_exists('oneTrickAgent', $payload) || array_key_exists('one_trick_agent', $payload)) {
            $payload['oneTrickAgent'] = self::normalizeOneTrickAgent(
                $payload['oneTrickAgent'] ?? $payload['one_trick_agent'] ?? []
            );
        }

        unset($payload['specific_agents']);
        unset($payload['one_trick_agent']);

        return OrderAddonRules::stripInactiveSelections($payload);
    }

    public static function sanitizeOrderDetails(array $details): array
    {
        if (array_key_exists('addons', $details)) {
            $details['addons'] = self::normalizeAddons($details['addons']);
        }

        if (array_key_exists('specificAgents', $details) || array_key_exists('specific_agents', $details)) {
            $details['specificAgents'] = self::normalizeSpecificAgents(
                $details['specificAgents'] ?? $details['specific_agents'] ?? []
            );
        }

        if (array_key_exists('oneTrickAgent', $details) || array_key_exists('one_trick_agent', $details)) {
            $details['oneTrickAgent'] = self::normalizeOneTrickAgent(
                $details['oneTrickAgent'] ?? $details['one_trick_agent'] ?? []
            );
        }

        unset($details['specific_agents']);
        unset($details['one_trick_agent']);

        if (isset($details['order']) && is_array($details['order'])) {
            $details['order'] = self::sanitizeOrderPayload($details['order']);
            $details['addons'] = self::normalizeAddons($details['order']['addons'] ?? $details['addons'] ?? []);
            $details['specificAgents'] = self::normalizeSpecificAgents(
                $details['order']['specificAgents'] ?? $details['specificAgents'] ?? []
            );
            $details['oneTrickAgent'] = self::normalizeOneTrickAgent(
                $details['order']['oneTrickAgent'] ?? $details['oneTrickAgent'] ?? []
            );
        }

        $sanitizedSelectionPayload = OrderAddonRules::stripInactiveSelections([
            'serviceType' => $details['service'] ?? data_get($details, 'order.orderType') ?? data_get($details, 'order.serviceType'),
            'boostMode' => $details['accountType'] ?? data_get($details, 'order.accountType') ?? data_get($details, 'order.boostMode'),
            'currentDivision' => $details['from'] ?? data_get($details, 'order.currentDivision') ?? data_get($details, 'order.currentRank'),
            'targetDivision' => $details['to'] ?? data_get($details, 'order.targetDivision') ?? data_get($details, 'order.desiredDivision') ?? data_get($details, 'order.targetRank') ?? data_get($details, 'order.desiredRank'),
            'addons' => $details['addons'] ?? [],
            'specificAgents' => $details['specificAgents'] ?? [],
            'oneTrickAgent' => $details['oneTrickAgent'] ?? [],
        ]);

        $details['addons'] = self::normalizeAddons($sanitizedSelectionPayload['addons'] ?? $details['addons'] ?? []);
        $details['specificAgents'] = self::normalizeSpecificAgents($sanitizedSelectionPayload['specificAgents'] ?? []);
        $details['oneTrickAgent'] = self::normalizeOneTrickAgent($sanitizedSelectionPayload['oneTrickAgent'] ?? []);

        if (isset($details['order']) && is_array($details['order'])) {
            $details['order']['addons'] = $details['addons'];
            $details['order']['specificAgents'] = $details['specificAgents'];
            $details['order']['oneTrickAgent'] = $details['oneTrickAgent'];
        }

        return $details;
    }

    public static function frontendPayload(): array
    {
        if (self::$frontendPayload !== null) {
            return self::$frontendPayload;
        }

        return self::$frontendPayload = [
            'services' => self::serviceOptions(),
            'ranks' => self::rankOptions(),
            'ranksWithRadiant' => self::rankOptionsWithRadiant(),
            'regions' => self::regions(),
            'platforms' => self::platforms(),
            'boostModes' => self::boostModes(),
            'averageRrOptions' => self::averageRrOptions(),
            'defaults' => [
                'currentRank' => self::defaultCurrentRank(),
                'desiredRank' => self::defaultDesiredRank(),
            ],
            'addons' => self::addons(),
            'addonRules' => OrderAddonRules::frontendConfig(),
            'pricingPreview' => self::frontendPricingPreview(),
        ];
    }

    public static function rankIconUrl(mixed $rank): string
    {
        $normalized = self::normalizeRank($rank);
        $tier = self::rankIconTierMap()[$normalized] ?? self::rankIconTierMap()['unranked'];

        return sprintf(self::RANK_ICON_BASE_URL, $tier);
    }

    public static function normalizeRank(mixed $rank): string
    {
        $clean = Str::of((string) $rank)
            ->lower()
            ->trim()
            ->replaceMatches('/\s+/', ' ')
            ->value();

        if ($clean === '') {
            return 'unranked';
        }

        $tiers = self::rankIconTierMap();

        if (array_key_exists($clean, $tiers)) {
            return $clean;
        }

        $numeric = str_replace(
            [' 1', ' 2', ' 3'],
            [' i', ' ii', ' iii'],
            $clean
        );

        if (array_key_exists($numeric, $tiers)) {
            return $numeric;
        }

        if (str_contains($clean, 'radiant')) {
            return 'radiant';
        }

        if (str_contains($clean, 'unranked')) {
            return 'unranked';
        }

        return 'unranked';
    }

    protected static function addonSettingsBySlug(): Collection
    {
        if (self::$addonSettingsBySlugCache !== null) {
            return self::$addonSettingsBySlugCache;
        }

        if (! self::hasAddonSettingsTable()) {
            return self::$addonSettingsBySlugCache = collect();
        }

        try {
            return self::$addonSettingsBySlugCache = AddonSetting::query()
                ->orderBy('sort_order')
                ->get()
                ->keyBy('slug');
        } catch (Throwable) {
            return self::$addonSettingsBySlugCache = collect();
        }
    }

    protected static function hasAddonSettingsTable(): bool
    {
        if (self::$hasAddonSettingsTableCache !== null) {
            return self::$hasAddonSettingsTableCache;
        }

        try {
            return self::$hasAddonSettingsTableCache = Schema::hasTable('addon_settings');
        } catch (Throwable) {
            return self::$hasAddonSettingsTableCache = false;
        }
    }

    protected static function frontendPricingPreview(): array
    {
        $payload = app(ValorantPricingConfigRepository::class)->publicPayload();

        return $payload['pricingPreview'] ?? [];
    }

    public static function flushRuntimeCaches(): void
    {
        self::$serviceOptions = null;
        self::$rankOptions = null;
        self::$rankOptionsWithRadiant = null;
        self::$regions = null;
        self::$platforms = null;
        self::$boostModes = null;
        self::$averageRrOptions = null;
        self::$boostModeOptions = null;
        self::$averageRrOptionChoices = null;
        self::$addonDefinitions = null;
        self::$addons = null;
        self::$frontendPayload = null;
        self::$addonSettingsBySlugCache = null;
        self::$hasAddonSettingsTableCache = null;
        self::$normalizedServiceLookup = null;
        self::$normalizedAddonLookup = null;
        self::$canonicalRankLookup = null;
        self::$rankIndexLookup = null;
    }

    protected static function pricing(?string $key = null, mixed $default = null): mixed
    {
        $config = app(ValorantPricingConfigRepository::class)->config();

        return $key === null ? $config : Arr::get($config, $key, $default);
    }

    protected static function boostModeCodeFromLabel(string $needle): ?string
    {
        foreach ((array) self::pricing('labels.boost_modes', []) as $code => $label) {
            $labelNeedle = Str::of((string) $label)
                ->trim()
                ->lower()
                ->replace(['-', '/', '\\'], '_')
                ->replace(' ', '_')
                ->replaceMatches('/_+/', '_')
                ->trim('_')
                ->value();

            if ($needle === $labelNeedle || $needle === (string) $code) {
                return (string) $code;
            }
        }

        return null;
    }

    protected static function serviceLookup(): array
    {
        if (self::$normalizedServiceLookup !== null) {
            return self::$normalizedServiceLookup;
        }

        $lookup = [];

        foreach (array_keys(self::pricing('services', [])) as $service) {
            $lookup[self::normalizeComparable($service)] = $service;
        }

        foreach (self::serviceOptions() as $service) {
            $lookup[self::normalizeComparable($service)] = $service;
        }

        foreach ([
            'placement game' => 'Placement Matches',
            'placement games' => 'Placement Matches',
            'rank boost' => 'Rank Boosting',
        ] as $alias => $service) {
            $lookup[self::normalizeComparable($alias)] = $service;
        }

        return self::$normalizedServiceLookup = $lookup;
    }

    protected static function addonLookup(): array
    {
        if (self::$normalizedAddonLookup !== null) {
            return self::$normalizedAddonLookup;
        }

        $lookup = [];

        foreach (self::addonDefinitions() as $addon) {
            foreach ([
                $addon['slug'],
                $addon['label'],
                ...($addon['aliases'] ?? []),
            ] as $candidate) {
                $normalized = self::normalizeComparable($candidate);

                if ($normalized !== '') {
                    $lookup[$normalized] = $addon['label'];
                }
            }
        }

        return self::$normalizedAddonLookup = $lookup;
    }

    protected static function rankLookup(): array
    {
        if (self::$canonicalRankLookup !== null) {
            return self::$canonicalRankLookup;
        }

        $lookup = [];

        foreach (self::pricing('rank_order', []) as $rank) {
            $lookup[self::normalizeComparable($rank)] = $rank;
        }

        return self::$canonicalRankLookup = $lookup;
    }

    protected static function rankIndexMap(): array
    {
        if (self::$rankIndexLookup !== null) {
            return self::$rankIndexLookup;
        }

        $lookup = [];

        foreach (self::pricing('rank_order', []) as $index => $rank) {
            $lookup[$rank] = $index;
        }

        return self::$rankIndexLookup = $lookup;
    }

    protected static function normalizeComparable(mixed $value): string
    {
        return Str::of((string) $value)
            ->lower()
            ->replace('_', '-')
            ->replaceMatches('/[()+$%]/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();
    }

    protected static function rankIconTierMap(): array
    {
        return [
            'unranked' => 0,
            'iron i' => 3,
            'iron ii' => 4,
            'iron iii' => 5,
            'bronze i' => 6,
            'bronze ii' => 7,
            'bronze iii' => 8,
            'silver i' => 9,
            'silver ii' => 10,
            'silver iii' => 11,
            'gold i' => 12,
            'gold ii' => 13,
            'gold iii' => 14,
            'platinum i' => 15,
            'platinum ii' => 16,
            'platinum iii' => 17,
            'diamond i' => 18,
            'diamond ii' => 19,
            'diamond iii' => 20,
            'ascendant i' => 21,
            'ascendant ii' => 22,
            'ascendant iii' => 23,
            'immortal i' => 24,
            'immortal ii' => 25,
            'immortal iii' => 26,
            'radiant' => 27,
        ];
    }
}
