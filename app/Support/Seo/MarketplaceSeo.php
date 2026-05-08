<?php

namespace App\Support\Seo;

use App\Models\BlogArticle;
use App\Models\GameCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MarketplaceSeo
{
    public const MAX_DESCRIPTION_LENGTH = 130;

    public function home(array $seo): array
    {
        return $this->payload(
            $seo,
            'Premium Game Boosting Services for Every Competitive Title',
            'Order professional boosting across VALORANT, League of Legends, CS2, Apex Legends, Overwatch 2, Diablo 4, and more.',
            route('home')
        );
    }

    public function game(array $game, ?string $canonical = null): array
    {
        $gameSeo = array_replace(
            (array) data_get($game, 'metadata.seo.home', []),
            (array) data_get($game, 'seo', [])
        );
        $slug = (string) data_get($game, 'slug', 'valorant');
        $name = (string) data_get($game, 'name', data_get($game, 'shortName', 'Game'));
        $shortName = (string) data_get($game, 'shortName', $name);

        return $this->payload(
            $gameSeo,
            $this->gameTitle($slug, $name, $shortName),
            $this->gameDescription($slug, $name, $shortName),
            $canonical ?: route('game.show', ['game' => $slug]),
            image: data_get($gameSeo, 'image')
        );
    }

    public function service(array $game, array $service, ?string $canonical = null): array
    {
        $serviceSeo = array_replace(
            (array) data_get($service, 'metadata.seo', []),
            (array) data_get($service, 'seo', [])
        );
        $gameSlug = (string) data_get($game, 'slug', 'valorant');
        $gameName = (string) data_get($game, 'name', data_get($game, 'shortName', 'Game'));
        $gameShortName = (string) data_get($game, 'shortName', $gameName);
        $serviceSlug = (string) data_get($service, 'slug', Str::slug((string) data_get($service, 'name', 'service')));
        $serviceName = (string) data_get($service, 'name', 'Boosting Service');
        $kind = (string) data_get($service, 'kind', '');

        return $this->payload(
            $serviceSeo,
            $this->serviceTitle($gameSlug, $gameShortName, $serviceSlug, $serviceName, $kind),
            $this->serviceDescription($gameSlug, $gameShortName, $serviceSlug, $serviceName, $kind),
            $canonical ?: route('game.services.show', ['game' => $gameSlug, 'service' => $serviceSlug]),
            image: data_get($serviceSeo, 'image')
        );
    }

    public function category(GameCategory $category, Collection $games, ?string $canonical = null): array
    {
        $seo = $this->metadataPayload($category->seoMetadata);
        $name = (string) $category->name;
        $gameNames = $games
            ->pluck('short_name')
            ->filter()
            ->take(4)
            ->implode(', ');
        $gameNames = $gameNames !== '' ? $gameNames : 'active games';

        return $this->payload(
            $seo,
            "{$name} Boosting Games | GGWPBoost",
            "Compare {$name} boosting services for {$gameNames} with secure checkout, clear pricing, and support.",
            $canonical ?: route('games.categories.show', ['category' => $category->slug])
        );
    }

    public function serviceCategory(array $category, Collection $games, Collection $services, ?string $canonical = null): array
    {
        $seo = (array) data_get($category, 'seo', []);
        $name = (string) data_get($category, 'name', 'Service');
        $slug = (string) data_get($category, 'slug', Str::slug($name));
        $gameNames = $games
            ->pluck('short_name')
            ->filter()
            ->take(5)
            ->implode(', ');
        $gameNames = $gameNames !== '' ? $gameNames : 'supported games';

        return $this->payload(
            $seo,
            "{$name} Services | GGWPBoost",
            "Compare {$name} services for {$gameNames} with clear starting prices and exact service pages.",
            $canonical ?: route('services.categories.show', ['category' => $slug])
        );
    }

    public function article(BlogArticle $article): array
    {
        $seo = $this->metadataPayload($article->seoMetadata);

        return $this->payload(
            $seo + [
                'title' => $article->meta_title,
                'description' => $article->meta_description,
                'canonical' => $article->canonical_url,
                'robots' => $article->robots,
                'type' => 'article',
            ],
            $article->title,
            $article->excerpt,
            route('blog.show', ['slug' => $article->slug]),
            type: 'article',
            image: $article->effectiveFeaturedImageUrl()
        );
    }

    public function payload(
        array $seo,
        string $fallbackTitle,
        string $fallbackDescription,
        string $canonical,
        string $robots = 'index,follow',
        string $type = 'website',
        ?string $image = null
    ): array {
        $resolvedImage = $this->resolvedImage($seo['image'] ?? $image);

        return [
            'title' => $this->plain($seo['title'] ?? null) ?: $fallbackTitle,
            'description' => $this->description($seo['description'] ?? null, $fallbackDescription),
            'canonical' => $this->plain($seo['canonical'] ?? null) ?: $canonical,
            'robots' => $this->plain($seo['robots'] ?? null) ?: $robots,
            'type' => $this->openGraphType($seo['type'] ?? $type),
            'image' => $resolvedImage,
            'twitter_card' => $resolvedImage ? 'summary_large_image' : 'summary',
            'metadata' => is_array($seo['metadata'] ?? null) ? $seo['metadata'] : [],
        ];
    }

    public function metadataPayload(mixed $metadata): array
    {
        if (! $metadata || ! method_exists($metadata, 'payload')) {
            return [];
        }

        return array_filter($metadata->payload(), static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    protected function gameTitle(string $slug, string $name, string $shortName): string
    {
        return match ($slug) {
            'valorant' => 'VALORANT Boosting Services | GGWPBoost',
            'apex-legends' => 'Apex Legends Boosting and Predator Boost | GGWPBoost',
            'cs2' => 'CS2 Boosting and Faceit ELO | GGWPBoost',
            'overwatch-2' => 'Overwatch 2 Boosting | GGWPBoost',
            'league-of-legends' => 'League of Legends Boosting | GGWPBoost',
            'modern-warfare-3' => 'MW3 Boosting and Camos Unlocks | GGWPBoost',
            default => "{$name} Boosting Services | GGWPBoost",
        };
    }

    protected function gameDescription(string $slug, string $name, string $shortName): string
    {
        return match ($slug) {
            'valorant' => 'Order Valorant boosting, Radiant boosts, placements, coaching, and ranked wins with secure checkout.',
            'apex-legends' => 'Order Apex Legends boosting, Predator boost, placements, coaching, and battle pass services.',
            'cs2' => 'Order CS2 boosting, Faceit ELO, placements, and coaching with clear pricing and secure checkout.',
            'overwatch-2' => 'Compare Overwatch 2 boosting, placements, coaching, and priority delivery with vetted boosters.',
            'league-of-legends' => 'Compare League of Legends boosting, placements, coaching, and duo queue services with secure checkout.',
            'modern-warfare-3' => 'Order MW3 boosting and camos unlock services with scoped delivery, secure checkout, and live support.',
            default => "Order {$name} boosting services with professional boosters, secure checkout, clear pricing, and live support.",
        };
    }

    protected function serviceTitle(string $gameSlug, string $gameShortName, string $serviceSlug, string $serviceName, string $kind): string
    {
        return match (true) {
            $kind === 'radiant_boost' => "Buy Radiant Boost for {$gameShortName} | GGWPBoost",
            $kind === 'predator_boost' => 'Apex Predator Boost Service | GGWPBoost',
            $kind === 'faceit_elo' => 'CS2 Faceit ELO Boost | GGWPBoost',
            $gameSlug === 'modern-warfare-3' && str_contains($serviceSlug, 'camos') => 'MW3 Camos Unlock Service | GGWPBoost',
            default => "{$gameShortName} {$serviceName} | GGWPBoost",
        };
    }

    protected function serviceDescription(string $gameSlug, string $gameShortName, string $serviceSlug, string $serviceName, string $kind): string
    {
        return match (true) {
            $kind === 'radiant_boost' => "Buy a {$gameShortName} Radiant boost with vetted high-rank boosters, secure checkout, and live support.",
            $kind === 'predator_boost' => 'Order an Apex Predator boost service with high-rank boosters, clear scope, secure checkout, and support.',
            $kind === 'faceit_elo' => 'Order a CS2 Faceit ELO boost with clear pricing, vetted boosters, secure checkout, and live support.',
            $gameSlug === 'modern-warfare-3' && str_contains($serviceSlug, 'camos') => 'Order an MW3 camos unlock service with scoped camo goals, secure checkout, and live order support.',
            default => "Order {$gameShortName} {$serviceName} with secure checkout, clear pricing, vetted boosters, and live support.",
        };
    }

    protected function description(mixed $stored, string $fallback): string
    {
        $description = $this->plain($stored) ?: $fallback;

        return Str::limit($description, self::MAX_DESCRIPTION_LENGTH, '');
    }

    protected function plain(mixed $value): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    protected function resolvedImage(mixed $image): ?string
    {
        $value = $this->plain($image);

        if ($value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return asset(ltrim($value, '/'));
    }

    protected function openGraphType(mixed $type): string
    {
        $value = Str::lower($this->plain($type));

        return match ($value) {
            'article', 'blogposting' => 'article',
            default => 'website',
        };
    }
}
