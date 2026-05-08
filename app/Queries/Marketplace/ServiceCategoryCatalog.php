<?php

namespace App\Queries\Marketplace;

use Illuminate\Support\Str;

class ServiceCategoryCatalog
{
    protected const DEFINITIONS = [
        'rank-boosting' => [
            'name' => 'Rank Boosting',
            'nav_label' => 'Rank Boosting',
            'kinds' => ['rank_boost', 'ranked_boosting', 'division_boosting', 'divisions', 'premier_boosting'],
            'description' => 'Compare rank-to-rank boosting for supported competitive games with clear starting prices and exact service pages.',
            'summary' => 'Rank boosting covers orders where a vetted booster helps move an account or self-play session from the current rank toward a chosen target rank. Each supported game keeps its own service page, pricing rules, and checkout context.',
            'seo' => [
                'title' => 'Rank Boosting Services | GGWPBoost',
                'description' => 'Compare rank boosting for VALORANT, League, CS2, Apex, and Overwatch 2 with clear pricing and vetted boosters.',
            ],
        ],
        'coaching' => [
            'name' => 'Coaching',
            'nav_label' => 'Coaching',
            'kinds' => ['coaching'],
            'description' => 'Book focused coaching across supported games for mechanics, VOD review, strategy, and climb planning.',
            'summary' => 'Coaching services connect players with game-specific experts for practical review sessions. Open the exact game service page to compare the coaching scope, starting price, and available order details.',
            'seo' => [
                'title' => 'Game Coaching Services | GGWPBoost',
                'description' => 'Book game coaching across supported titles with vetted players, focused reviews, and exact service pages.',
            ],
        ],
        'power-leveling' => [
            'name' => 'Power Leveling',
            'nav_label' => 'Power Leveling',
            'kinds' => ['power_leveling'],
            'description' => 'Compare power leveling services for level ranges, seasonal progress, and endgame-ready account goals.',
            'summary' => 'Power leveling is for progression goals where levels, milestones, or account readiness matter more than ranked divisions. Each card links to the exact game page for service-specific pricing and scope.',
            'seo' => [
                'title' => 'Power Leveling Services | GGWPBoost',
                'description' => 'Compare power leveling services with starting prices, scoped goals, and secure checkout paths.',
            ],
        ],
        'unlock-services' => [
            'name' => 'Unlock Services',
            'nav_label' => 'Unlock Services',
            'kinds' => ['unlock_services', 'unlock_all', 'camos', 'camos_unlock_service', 'skin_unlocks', 'operator_unlocks', 'dark_ops', 'calling_cards'],
            'description' => 'Find unlock services for eligible objectives, account goals, camos, and progression rewards.',
            'summary' => 'Unlock services cover scoped goals such as account unlocks, objective completion, camo paths, and eligible reward progress. The service page for each game confirms the exact supported goal before checkout.',
            'seo' => [
                'title' => 'Unlock Services | GGWPBoost',
                'description' => 'Compare unlock services for supported games with scoped objectives, clear pricing, and exact service pages.',
            ],
        ],
        'battle-pass' => [
            'name' => 'Battle Pass',
            'nav_label' => 'Battle Pass',
            'kinds' => ['battle_pass_completion'],
            'description' => 'Compare battle pass completion services for seasonal tier progress and managed delivery.',
            'summary' => 'Battle pass services focus on seasonal tier completion and managed progression. Use the game-specific cards to confirm the available service, price floor, and delivery path.',
            'seo' => [
                'title' => 'Battle Pass Services | GGWPBoost',
                'description' => 'Compare battle pass completion services with clear tier pricing, managed delivery, and secure checkout.',
            ],
        ],
        'weapon-leveling' => [
            'name' => 'Weapon Leveling',
            'nav_label' => 'Weapon Leveling',
            'kinds' => ['weapon_leveling', 'weapon_mastery', 'vehicle_leveling'],
            'description' => 'Level weapons, unlock attachments, and prepare loadouts through supported game service pages.',
            'summary' => 'Weapon leveling services are built around weapon XP, attachment readiness, and loadout preparation. Each supported game keeps its own order path so the scope stays clear before checkout.',
            'seo' => [
                'title' => 'Weapon Leveling Services | GGWPBoost',
                'description' => 'Compare weapon leveling services with starting prices, scoped goals, and exact game service pages.',
            ],
        ],
    ];

    public static function all(): array
    {
        return collect(self::DEFINITIONS)
            ->map(fn (array $definition, string $slug): array => self::payload($slug, $definition))
            ->values()
            ->all();
    }

    public static function find(mixed $slug): ?array
    {
        $normalized = Str::slug(trim((string) $slug));
        $definition = self::DEFINITIONS[$normalized] ?? null;

        return $definition === null ? null : self::payload($normalized, $definition);
    }

    public static function related(string $slug): array
    {
        return collect(self::all())
            ->reject(fn (array $definition): bool => $definition['slug'] === $slug)
            ->values()
            ->all();
    }

    public static function matches(array $definition, mixed $kind): bool
    {
        return in_array(self::normalizeKind($kind), self::normalizedKinds($definition), true);
    }

    public static function normalizedKinds(array $definition): array
    {
        return collect($definition['kinds'] ?? [])
            ->map(fn (mixed $kind): string => self::normalizeKind($kind))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected static function payload(string $slug, array $definition): array
    {
        return array_merge($definition, [
            'slug' => $slug,
            'url' => route('services.categories.show', ['category' => $slug]),
            'faqs' => self::faqsFor($definition['name']),
        ]);
    }

    protected static function faqsFor(string $name): array
    {
        return [
            [
                'question' => "Which games offer {$name}?",
                'answer' => 'This page lists every published game with a matching service category, then links to the exact service page for that game.',
            ],
            [
                'question' => 'How do starting prices work?',
                'answer' => 'Starting prices come from the active pricing rules for each service. When a dynamic calculator is used, the lowest configured base price is shown.',
            ],
            [
                'question' => 'Can I compare services before checkout?',
                'answer' => 'Yes. Open any service card to review the full game-specific service page, pricing details, add-ons, FAQs, and order flow before checkout.',
            ],
            [
                'question' => 'What happens if a game does not support this service?',
                'answer' => 'Only published services that match this category are shown. Unsupported games are left out so every CTA leads to a valid service page.',
            ],
        ];
    }

    protected static function normalizeKind(mixed $kind): string
    {
        return Str::slug(trim((string) $kind), '_');
    }
}
