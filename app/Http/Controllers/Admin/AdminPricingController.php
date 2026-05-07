<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ResetPricingSettingsRequest;
use App\Http\Requests\Admin\UpdatePricingSettingsRequest;
use App\Support\GameCatalog;
use App\Support\Pricing\ValorantPricingConfigRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminPricingController extends AdminController
{
    public function __construct(
        protected ValorantPricingConfigRepository $pricingConfigRepository,
        protected GameCatalog $gameCatalog,
    ) {}

    public function index(Request $request): View
    {
        $gameSlug = $this->gameSlug($request->query('game'));
        $snapshot = $this->pricingConfigRepository->current($gameSlug);
        $config = $snapshot['config'];
        $activeGame = $this->gameCatalog->game($gameSlug);

        return $this->renderPage('admin.pricing.index', [
            'availableGames' => $this->gameCatalog->all(includeDrafts: true),
            'activeGame' => $activeGame,
            'activeGameSlug' => $gameSlug,
            'pricingSnapshot' => $snapshot,
            'pricingConfig' => $config,
            'rankOrder' => $config['rank_order'] ?? [],
            'basePrices' => $config['base_prices'] ?? [],
            'specialRankBoostRows' => $this->specialRankBoostRows($config['special_rank_boost_steps'] ?? []),
            'rrRules' => $config['rr_rules'] ?? [],
            'addons' => $config['addons'] ?? [],
            'modifiers' => $config['modifiers'] ?? [],
            'labels' => $config['labels'] ?? [],
        ]);
    }

    public function update(UpdatePricingSettingsRequest $request): RedirectResponse
    {
        $gameSlug = $this->gameSlug($request->input('game'));
        $activeGame = $this->gameCatalog->game($gameSlug);
        $gameName = (string) ($activeGame['name'] ?? 'Valorant');
        $before = $this->pricingConfigRepository->current($gameSlug);
        $config = $request->pricingConfig();
        $changedSections = $this->changedSections($before['config'] ?? [], $config);
        $setting = $this->pricingConfigRepository->update($config, $request->user(), 'update', [
            'changed_sections' => $changedSections,
            'previous_version' => $before['version'] ?? null,
            'previous_checksum' => $before['checksum'] ?? null,
        ], $gameSlug);

        $this->audit('system', 'pricing_updated', "{$gameName} Pricing", [
            'game_slug' => $gameSlug,
            'changed_sections' => $changedSections,
            'previous_version' => $before['version'] ?? null,
            'new_version' => $setting->version,
            'previous_checksum' => $before['checksum'] ?? null,
            'new_checksum' => $setting->checksum,
        ], $request);

        return redirect()
            ->to($this->indexUrl($gameSlug))
            ->with('status', "{$gameName} pricing updated.");
    }

    public function reset(ResetPricingSettingsRequest $request): RedirectResponse
    {
        $gameSlug = $this->gameSlug($request->input('game'));
        $activeGame = $this->gameCatalog->game($gameSlug);
        $gameName = (string) ($activeGame['name'] ?? 'Valorant');
        $before = $this->pricingConfigRepository->current($gameSlug);
        $setting = $this->pricingConfigRepository->resetToDefaults($request->user(), [
            'previous_version' => $before['version'] ?? null,
            'previous_checksum' => $before['checksum'] ?? null,
        ], $gameSlug);

        $this->audit('system', 'pricing_reset', "{$gameName} Pricing", [
            'game_slug' => $gameSlug,
            'previous_version' => $before['version'] ?? null,
            'new_version' => $setting->version,
            'previous_checksum' => $before['checksum'] ?? null,
            'new_checksum' => $setting->checksum,
            'actor_id' => Auth::id(),
        ], $request);

        return redirect()
            ->to($this->indexUrl($gameSlug))
            ->with('status', "{$gameName} pricing reset to config defaults.");
    }

    protected function specialRankBoostRows(array $steps): array
    {
        return collect($steps)
            ->map(function (mixed $price, string $key): array {
                [$fromRank, $toRank] = array_pad(array_map('trim', explode('->', $key, 2)), 2, '');

                return [
                    'from' => $fromRank,
                    'to' => $toRank,
                    'price' => $price,
                ];
            })
            ->values()
            ->all();
    }

    protected function changedSections(array $before, array $after): array
    {
        $sections = [
            'base_prices',
            'special_rank_boost_steps',
            'rr_rules',
            'addons',
            'modifiers',
            'labels',
        ];

        return collect($sections)
            ->filter(fn (string $section): bool => ($before[$section] ?? null) !== ($after[$section] ?? null))
            ->values()
            ->all();
    }

    protected function gameSlug(mixed $value): string
    {
        $slug = $this->gameCatalog->normalizeSlug($value);

        return $this->gameCatalog->exists($slug) ? $slug : GameCatalog::DEFAULT_GAME_SLUG;
    }

    protected function indexUrl(string $gameSlug): string
    {
        return $gameSlug === GameCatalog::DEFAULT_GAME_SLUG
            ? route('admin-pricing.index')
            : route('admin-pricing.index', ['game' => $gameSlug]);
    }
}
