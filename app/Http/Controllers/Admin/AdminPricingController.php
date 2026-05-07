<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ResetPricingSettingsRequest;
use App\Http\Requests\Admin\UpdatePricingSettingsRequest;
use App\Support\Pricing\ValorantPricingConfigRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminPricingController extends AdminController
{
    public function __construct(protected ValorantPricingConfigRepository $pricingConfigRepository) {}

    public function index(): View
    {
        $snapshot = $this->pricingConfigRepository->current();
        $config = $snapshot['config'];

        return $this->renderPage('admin.pricing.index', [
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
        $before = $this->pricingConfigRepository->current();
        $config = $request->pricingConfig();
        $changedSections = $this->changedSections($before['config'] ?? [], $config);
        $setting = $this->pricingConfigRepository->update($config, $request->user(), 'update', [
            'changed_sections' => $changedSections,
            'previous_version' => $before['version'] ?? null,
            'previous_checksum' => $before['checksum'] ?? null,
        ]);

        $this->audit('system', 'pricing_updated', 'Valorant Pricing', [
            'changed_sections' => $changedSections,
            'previous_version' => $before['version'] ?? null,
            'new_version' => $setting->version,
            'previous_checksum' => $before['checksum'] ?? null,
            'new_checksum' => $setting->checksum,
        ], $request);

        return redirect()
            ->route('admin-pricing.index')
            ->with('status', 'Valorant pricing updated.');
    }

    public function reset(ResetPricingSettingsRequest $request): RedirectResponse
    {
        $before = $this->pricingConfigRepository->current();
        $setting = $this->pricingConfigRepository->resetToDefaults($request->user(), [
            'previous_version' => $before['version'] ?? null,
            'previous_checksum' => $before['checksum'] ?? null,
        ]);

        $this->audit('system', 'pricing_reset', 'Valorant Pricing', [
            'previous_version' => $before['version'] ?? null,
            'new_version' => $setting->version,
            'previous_checksum' => $before['checksum'] ?? null,
            'new_checksum' => $setting->checksum,
            'actor_id' => Auth::id(),
        ], $request);

        return redirect()
            ->route('admin-pricing.index')
            ->with('status', 'Valorant pricing reset to config defaults.');
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
}
