<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\MarketplaceAddonRequest;
use App\Models\Game;
use App\Models\GameAddon;
use App\Models\GameService;
use App\Models\ServicePricingRule;
use App\Support\MarketplaceCatalogCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminMarketplaceAddonController extends AdminController
{
    public function __construct(
        protected MarketplaceCatalogCache $catalogCache,
    ) {}

    public function index(): View
    {
        return $this->renderPage('admin.marketplace.addons.index', [
            'addons' => GameAddon::query()
                ->with('game')
                ->withCount('services')
                ->orderBy('game_id')
                ->orderBy('sort_order')
                ->orderBy('label')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return $this->renderPage('admin.marketplace.addons.create', $this->formPayload());
    }

    public function store(MarketplaceAddonRequest $request): RedirectResponse
    {
        $addon = DB::transaction(function () use ($request): GameAddon {
            $addon = GameAddon::query()->create($this->addonData($request));
            $this->syncPricingRule($addon);
            $this->syncServiceAssignments($addon, (array) $request->validated('service_ids', []));

            return $addon;
        });

        $this->catalogCache->clear();
        $this->audit('marketplace', 'addon_created', $addon, [
            'game_id' => $addon->game_id,
            'slug' => $addon->slug,
            'status' => $addon->status,
        ], $request);

        return redirect()
            ->route('admin-marketplace.addons.edit', $addon)
            ->with('status', 'Addon created.');
    }

    public function edit(GameAddon $addon): View
    {
        return $this->renderPage('admin.marketplace.addons.edit', $this->formPayload([
            'addon' => $addon->load(['game', 'services', 'pricingRules']),
        ]));
    }

    public function update(MarketplaceAddonRequest $request, GameAddon $addon): RedirectResponse
    {
        DB::transaction(function () use ($request, $addon): void {
            $addon->update($this->addonData($request));
            $addon->refresh();
            $this->syncPricingRule($addon);
            $this->syncServiceAssignments($addon, (array) $request->validated('service_ids', []));
        });

        $this->catalogCache->clear();
        $this->audit('marketplace', 'addon_updated', $addon, [
            'game_id' => $addon->game_id,
            'slug' => $addon->slug,
            'status' => $addon->status,
        ], $request);

        return redirect()
            ->route('admin-marketplace.addons.edit', $addon)
            ->with('status', 'Addon updated.');
    }

    public function archive(Request $request, GameAddon $addon): RedirectResponse
    {
        $addon->update(['status' => GameAddon::STATUS_ARCHIVED]);
        $this->syncPricingRule($addon->fresh());
        $this->catalogCache->clear();
        $this->audit('marketplace', 'addon_archived', $addon, [
            'game_id' => $addon->game_id,
            'slug' => $addon->slug,
        ], $request);

        return redirect()
            ->route('admin-marketplace.addons.index')
            ->with('status', 'Addon archived.');
    }

    public function publish(Request $request, GameAddon $addon): RedirectResponse
    {
        $addon->update(['status' => GameAddon::STATUS_PUBLISHED]);
        $this->syncPricingRule($addon->fresh());
        $this->catalogCache->clear();
        $this->audit('marketplace', 'addon_published', $addon, [
            'game_id' => $addon->game_id,
            'slug' => $addon->slug,
        ], $request);

        return redirect()
            ->route('admin-marketplace.addons.edit', $addon)
            ->with('status', 'Addon published.');
    }

    protected function formPayload(array $payload = []): array
    {
        $activeGameId = (int) old('game_id', data_get($payload, 'addon.game_id', 0));

        return array_merge([
            'games' => Game::query()->orderBy('sort_order')->orderBy('name')->get(),
            'statuses' => GameAddon::STATUSES,
            'pricingTypes' => [
                'free' => 'Free',
                ServicePricingRule::PRICING_FIXED => 'Fixed amount',
                ServicePricingRule::PRICING_PERCENTAGE => 'Percentage',
                ServicePricingRule::PRICING_MULTIPLIER => 'Multiplier',
            ],
            'services' => GameService::query()
                ->when($activeGameId > 0, fn ($query) => $query->where('game_id', $activeGameId))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'selectedServiceIds' => collect(data_get($payload, 'addon.services', []))->pluck('id')->all(),
        ], $payload);
    }

    protected function addonData(MarketplaceAddonRequest $request): array
    {
        $data = $request->catalogData();
        $pricingValue = (float) ($data['pricing_value'] ?? 0);

        $data['pricing_value'] = $pricingValue;
        $data['pricing_rule'] = [
            'type' => $data['pricing_type'],
            'value' => $pricingValue,
        ];
        $data['availability_rule'] = [];
        $data['metadata'] = [];

        return $data;
    }

    protected function syncPricingRule(?GameAddon $addon): void
    {
        if (! $addon instanceof GameAddon) {
            return;
        }

        $rule = ServicePricingRule::query()
            ->where('addon_id', $addon->id)
            ->where('scope', ServicePricingRule::SCOPE_ADDON)
            ->whereNull('service_id')
            ->first() ?? new ServicePricingRule;

        $rule->forceFill([
            'game_id' => $addon->game_id,
            'service_id' => null,
            'addon_id' => $addon->id,
            'slug' => "{$addon->slug}-addon",
            'name' => "{$addon->label} addon pricing",
            'scope' => ServicePricingRule::SCOPE_ADDON,
            'calculator_key' => 'addon_modifier',
            'pricing_type' => $addon->pricing_type,
            'amount' => (float) ($addon->pricing_value ?? 0),
            'currency' => 'USD',
            'status' => $addon->status,
            'sort_order' => 100 + (int) $addon->sort_order,
            'conditions' => [
                'addon_slug' => $addon->slug,
            ],
            'tiers' => [],
            'metadata' => [
                'admin_managed' => true,
            ],
        ])->save();
    }

    protected function syncServiceAssignments(GameAddon $addon, array $serviceIds): void
    {
        $syncPayload = collect($serviceIds)
            ->mapWithKeys(fn (mixed $serviceId, int $index): array => [
                (int) $serviceId => [
                    'status' => GameAddon::STATUS_PUBLISHED,
                    'sort_order' => $index + 1,
                    'availability_rule' => json_encode([
                        'addon_slug' => $addon->slug,
                    ], JSON_THROW_ON_ERROR),
                    'metadata' => json_encode([], JSON_THROW_ON_ERROR),
                ],
            ])
            ->all();

        $addon->services()->sync($syncPayload);
    }
}
