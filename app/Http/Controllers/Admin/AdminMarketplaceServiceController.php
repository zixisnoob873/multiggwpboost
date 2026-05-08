<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\MarketplaceServiceRequest;
use App\Models\Game;
use App\Models\GameAddon;
use App\Models\GameService;
use App\Models\ServicePricingRule;
use App\Support\MarketplaceCatalogCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminMarketplaceServiceController extends AdminController
{
    public function __construct(
        protected MarketplaceCatalogCache $catalogCache,
    ) {}

    public function index(): View
    {
        return $this->renderPage('admin.marketplace.services.index', [
            'services' => GameService::query()
                ->with(['game', 'pricingRules'])
                ->withCount('addons')
                ->orderBy('game_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return $this->renderPage('admin.marketplace.services.create', $this->formPayload());
    }

    public function store(MarketplaceServiceRequest $request): RedirectResponse
    {
        $service = DB::transaction(function () use ($request): GameService {
            $data = $request->catalogData();
            $data['config'] = [];
            $data['metadata'] = [
                'homepage_featured' => $request->boolean('homepage_featured'),
            ];

            $service = GameService::query()->create($data);
            $this->syncSeo($service, $request->seoData());
            $this->syncBasePrice($service, $request->validated('base_price'));
            $this->syncAddonAssignments($service, (array) $request->validated('addon_ids', []));

            return $service;
        });

        $this->catalogCache->clear();
        $this->audit('marketplace', 'service_created', $service, [
            'game_id' => $service->game_id,
            'slug' => $service->slug,
            'status' => $service->status,
        ], $request);

        return redirect()
            ->route('admin-marketplace.services.edit', $service)
            ->with('status', 'Service created.');
    }

    public function edit(GameService $service): View
    {
        return $this->renderPage('admin.marketplace.services.edit', $this->formPayload([
            'service' => $service->load(['game', 'seoMetadata', 'addons', 'pricingRules']),
        ]));
    }

    public function update(MarketplaceServiceRequest $request, GameService $service): RedirectResponse
    {
        DB::transaction(function () use ($request, $service): void {
            $data = $request->catalogData();
            $metadata = $service->metadata ?? [];
            $metadata['homepage_featured'] = $request->boolean('homepage_featured');
            $data['metadata'] = $metadata;

            $service->update($data);
            $service->refresh();
            $this->syncSeo($service, $request->seoData());
            $this->syncBasePrice($service, $request->validated('base_price'));
            $this->syncAddonAssignments($service, (array) $request->validated('addon_ids', []));
        });

        $this->catalogCache->clear();
        $this->audit('marketplace', 'service_updated', $service, [
            'game_id' => $service->game_id,
            'slug' => $service->slug,
            'status' => $service->status,
            'homepage_featured' => (bool) data_get($service->metadata, 'homepage_featured', false),
        ], $request);

        return redirect()
            ->route('admin-marketplace.services.edit', $service)
            ->with('status', 'Service updated.');
    }

    public function archive(Request $request, GameService $service): RedirectResponse
    {
        $service->update(['status' => GameService::STATUS_ARCHIVED]);
        $this->catalogCache->clear();
        $this->audit('marketplace', 'service_archived', $service, [
            'game_id' => $service->game_id,
            'slug' => $service->slug,
        ], $request);

        return redirect()
            ->route('admin-marketplace.services.index')
            ->with('status', 'Service archived.');
    }

    public function publish(Request $request, GameService $service): RedirectResponse
    {
        $service->update(['status' => GameService::STATUS_PUBLISHED]);
        $this->catalogCache->clear();
        $this->audit('marketplace', 'service_published', $service, [
            'game_id' => $service->game_id,
            'slug' => $service->slug,
        ], $request);

        return redirect()
            ->route('admin-marketplace.services.edit', $service)
            ->with('status', 'Service published.');
    }

    protected function formPayload(array $payload = []): array
    {
        $activeGameId = (int) old('game_id', data_get($payload, 'service.game_id', 0));

        return array_merge([
            'games' => Game::query()->orderBy('sort_order')->orderBy('name')->get(),
            'statuses' => GameService::STATUSES,
            'addons' => GameAddon::query()
                ->when($activeGameId > 0, fn ($query) => $query->where('game_id', $activeGameId))
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get(),
            'selectedAddonIds' => collect(data_get($payload, 'service.addons', []))->pluck('id')->all(),
            'basePrice' => $this->basePrice(data_get($payload, 'service')),
        ], $payload);
    }

    protected function syncSeo(GameService $service, array $data): void
    {
        $service->seoMetadata()->updateOrCreate(
            ['context' => 'default'],
            [
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'robots' => 'index,follow',
                'schema_type' => 'Service',
                'include_in_sitemap' => true,
                'changefreq' => 'weekly',
                'priority' => 0.7,
                'metadata' => [],
            ]
        );
    }

    protected function syncBasePrice(GameService $service, mixed $basePrice): void
    {
        if ($basePrice === null || $basePrice === '') {
            return;
        }

        $rule = ServicePricingRule::query()
            ->where('service_id', $service->id)
            ->where('scope', ServicePricingRule::SCOPE_BASE)
            ->whereNull('addon_id')
            ->first() ?? new ServicePricingRule();

        $rule->forceFill([
            'game_id' => $service->game_id,
            'service_id' => $service->id,
            'addon_id' => null,
            'slug' => "{$service->slug}-base",
            'name' => "{$service->name} base pricing",
            'scope' => ServicePricingRule::SCOPE_BASE,
            'calculator_key' => 'flat_service',
            'pricing_type' => ServicePricingRule::PRICING_FIXED,
            'amount' => (float) $basePrice,
            'currency' => 'USD',
            'status' => ServicePricingRule::STATUS_PUBLISHED,
            'sort_order' => 1,
            'conditions' => [],
            'tiers' => [],
            'metadata' => [],
        ])->save();
    }

    protected function syncAddonAssignments(GameService $service, array $addonIds): void
    {
        $syncPayload = collect($addonIds)
            ->mapWithKeys(fn (mixed $addonId, int $index): array => [
                (int) $addonId => [
                    'status' => GameService::STATUS_PUBLISHED,
                    'sort_order' => $index + 1,
                    'availability_rule' => json_encode([
                        'service_slug' => $service->slug,
                    ], JSON_THROW_ON_ERROR),
                    'metadata' => json_encode([], JSON_THROW_ON_ERROR),
                ],
            ])
            ->all();

        $service->addons()->sync($syncPayload);
    }

    protected function basePrice(mixed $service): ?float
    {
        if (! $service instanceof GameService) {
            return null;
        }

        $rule = $service->relationLoaded('pricingRules')
            ? $service->pricingRules->first(fn (ServicePricingRule $rule): bool => $rule->scope === ServicePricingRule::SCOPE_BASE)
            : $service->pricingRules()->where('scope', ServicePricingRule::SCOPE_BASE)->first();

        return $rule?->amount !== null ? (float) $rule->amount : null;
    }
}

