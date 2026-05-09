<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\MarketplaceGameRequest;
use App\Models\Game;
use App\Models\GameCategory;
use App\Support\MarketplaceCatalogCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminMarketplaceGameController extends AdminController
{
    public function __construct(
        protected MarketplaceCatalogCache $catalogCache,
    ) {}

    public function index(): View
    {
        return $this->renderPage('admin.marketplace.games.index', [
            'games' => Game::query()
                ->with('category')
                ->withCount(['services', 'addons'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return $this->renderPage('admin.marketplace.games.create', $this->formPayload());
    }

    public function store(MarketplaceGameRequest $request): RedirectResponse
    {
        $data = $request->catalogData();
        $data['assets'] = [];
        $data['metadata'] = [
            'featured' => $request->boolean('featured'),
        ];

        $game = Game::query()->create($data);
        $this->syncSeo($game, $request->seoData());
        $this->catalogCache->clear();
        $this->audit('marketplace', 'game_created', $game, [
            'slug' => $game->slug,
            'status' => $game->status,
        ], $request);

        return redirect()
            ->route('admin-marketplace.games.edit', $game)
            ->with('status', 'Game created.');
    }

    public function edit(Game $game): View
    {
        return $this->renderPage('admin.marketplace.games.edit', $this->formPayload([
            'game' => $game->load('seoMetadata'),
        ]));
    }

    public function update(MarketplaceGameRequest $request, Game $game): RedirectResponse
    {
        $data = $request->catalogData();
        $metadata = $game->metadata ?? [];
        $metadata['featured'] = $request->boolean('featured');
        $data['metadata'] = $metadata;

        $game->update($data);
        $this->syncSeo($game, $request->seoData());
        $this->catalogCache->clear();
        $this->audit('marketplace', 'game_updated', $game, [
            'slug' => $game->slug,
            'status' => $game->status,
            'featured' => (bool) data_get($game->metadata, 'featured', false),
        ], $request);

        return redirect()
            ->route('admin-marketplace.games.edit', $game)
            ->with('status', 'Game updated.');
    }

    public function archive(Request $request, Game $game): RedirectResponse
    {
        $game->update(['status' => Game::STATUS_ARCHIVED]);
        $this->catalogCache->clear();
        $this->audit('marketplace', 'game_archived', $game, [
            'slug' => $game->slug,
        ], $request);

        return redirect()
            ->route('admin-marketplace.games.index')
            ->with('status', 'Game archived.');
    }

    public function publish(Request $request, Game $game): RedirectResponse
    {
        $game->update(['status' => Game::STATUS_PUBLISHED]);
        $this->catalogCache->clear();
        $this->audit('marketplace', 'game_published', $game, [
            'slug' => $game->slug,
        ], $request);

        return redirect()
            ->route('admin-marketplace.games.edit', $game)
            ->with('status', 'Game published.');
    }

    protected function formPayload(array $payload = []): array
    {
        return array_merge([
            'categories' => GameCategory::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'statuses' => Game::STATUSES,
        ], $payload);
    }

    protected function syncSeo(Game $game, array $data): void
    {
        $game->seoMetadata()->updateOrCreate(
            ['context' => 'default'],
            [
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'robots' => 'index,follow',
                'schema_type' => 'WebPage',
                'include_in_sitemap' => true,
                'changefreq' => 'weekly',
                'priority' => 0.8,
                'metadata' => [],
            ]
        );
    }
}
