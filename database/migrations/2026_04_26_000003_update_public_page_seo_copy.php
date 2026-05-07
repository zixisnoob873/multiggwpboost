<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->syncPages($this->pageUpdates());
        $this->syncBlogArticles($this->blogArticleUpdates());
    }

    public function down(): void
    {
        $this->syncBlogArticles($this->blogArticleRollbacks());
        $this->syncPages($this->pageRollbacks());
    }

    protected function syncPages(array $pages): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        foreach ($pages as $key => $updates) {
            $page = DB::table('pages')->where('key', $key)->first();

            if (! $page) {
                continue;
            }

            $payload = [];

            foreach (['meta_title', 'meta_description'] as $column) {
                $candidate = $updates[$column] ?? null;

                if (! is_array($candidate)) {
                    continue;
                }

                $current = $page->{$column} ?? null;

                if ($this->shouldReplace($current, $candidate['from'] ?? null)) {
                    $payload[$column] = $candidate['to'] ?? null;
                }
            }

            $content = $this->decodeContent($page->content ?? null);
            $contentChanged = false;

            foreach (($updates['content'] ?? []) as $path => $candidate) {
                $current = data_get($content, $path);

                if ($this->shouldReplace($current, $candidate['from'] ?? null)) {
                    data_set($content, $path, $candidate['to'] ?? null);
                    $contentChanged = true;
                }
            }

            if ($contentChanged) {
                $payload['content'] = json_encode($content, JSON_UNESCAPED_SLASHES);
            }

            if ($payload !== []) {
                $payload['updated_at'] = now();
                DB::table('pages')->where('key', $key)->update($payload);
            }
        }
    }

    protected function shouldReplace(mixed $current, mixed $expected): bool
    {
        if ($current === null || $current === '') {
            return true;
        }

        return $current == $expected;
    }

    protected function decodeContent(mixed $content): array
    {
        if (is_array($content)) {
            return $content;
        }

        $decoded = json_decode((string) $content, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function syncBlogArticles(array $articles): void
    {
        if (! Schema::hasTable('blog_articles')) {
            return;
        }

        foreach ($articles as $slug => $updates) {
            $article = DB::table('blog_articles')->where('slug', $slug)->first();

            if (! $article) {
                continue;
            }

            $payload = [];

            foreach (['title', 'excerpt', 'intro', 'meta_title', 'meta_description', 'cta_label'] as $column) {
                $candidate = $updates[$column] ?? null;

                if (! is_array($candidate)) {
                    continue;
                }

                $current = $article->{$column} ?? null;

                if ($this->shouldReplace($current, $candidate['from'] ?? null)) {
                    $payload[$column] = $candidate['to'] ?? null;
                }
            }

            $body = (string) ($article->body ?? '');
            $bodyChanged = false;

            foreach (($updates['body_replacements'] ?? []) as $replacement) {
                $from = (string) ($replacement['from'] ?? '');
                $to = (string) ($replacement['to'] ?? '');

                if ($from !== '' && str_contains($body, $from)) {
                    $body = str_replace($from, $to, $body);
                    $bodyChanged = true;
                }
            }

            if ($bodyChanged) {
                $payload['body'] = $body;
            }

            $faqItems = $this->decodeContent($article->faq_items ?? null);
            $faqChanged = false;
            $faqItems = $this->replaceNestedStrings($faqItems, $updates['faq_replacements'] ?? [], $faqChanged);

            if ($faqChanged) {
                $payload['faq_items'] = json_encode($faqItems, JSON_UNESCAPED_SLASHES);
            }

            if ($payload !== []) {
                $payload['updated_at'] = now();
                DB::table('blog_articles')->where('slug', $slug)->update($payload);
            }
        }
    }

    protected function replaceNestedStrings(mixed $value, array $replacements, bool &$changed): mixed
    {
        if (is_string($value)) {
            $updated = $value;

            foreach ($replacements as $replacement) {
                $from = (string) ($replacement['from'] ?? '');
                $to = (string) ($replacement['to'] ?? '');

                if ($from !== '' && str_contains($updated, $from)) {
                    $updated = str_replace($from, $to, $updated);
                }
            }

            if ($updated !== $value) {
                $changed = true;
            }

            return $updated;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->replaceNestedStrings($item, $replacements, $changed);
            }
        }

        return $value;
    }

    protected function swap(array $updates): array
    {
        return collect($updates)
            ->map(function (array $page): array {
                foreach (['meta_title', 'meta_description'] as $column) {
                    if (isset($page[$column])) {
                        $page[$column] = [
                            'from' => $page[$column]['to'],
                            'to' => $page[$column]['from'],
                        ];
                    }
                }

                foreach (($page['content'] ?? []) as $path => $candidate) {
                    $page['content'][$path] = [
                        'from' => $candidate['to'],
                        'to' => $candidate['from'],
                    ];
                }

                return $page;
            })
            ->all();
    }

    protected function pageRollbacks(): array
    {
        return $this->swap($this->pageUpdates());
    }

    protected function blogArticleRollbacks(): array
    {
        return $this->swapBlogArticles($this->blogArticleUpdates());
    }

    protected function swapBlogArticles(array $updates): array
    {
        return collect($updates)
            ->map(function (array $article): array {
                foreach (['title', 'excerpt', 'intro', 'meta_title', 'meta_description', 'cta_label'] as $column) {
                    if (isset($article[$column])) {
                        $article[$column] = [
                            'from' => $article[$column]['to'],
                            'to' => $article[$column]['from'],
                        ];
                    }
                }

                foreach (['body_replacements', 'faq_replacements'] as $group) {
                    foreach (($article[$group] ?? []) as $index => $candidate) {
                        $article[$group][$index] = [
                            'from' => $candidate['to'],
                            'to' => $candidate['from'],
                        ];
                    }
                }

                return $article;
            })
            ->all();
    }

    protected function seededBlogCta(): array
    {
        return [
            'cta_label' => [
                'from' => 'Explore Services',
                'to' => 'Explore VALORANT Boosts',
            ],
        ];
    }

    protected function blogArticleUpdates(): array
    {
        $cta = $this->seededBlogCta();

        return [
            'is-valorant-boosting-safe' => [
                ...$cta,
                'meta_description' => [
                    'from' => 'Learn the real risks behind Valorant boosting, how account-shared and self-play modes differ, and what to check before choosing a provider.',
                    'to' => 'Learn the real risks of VALORANT boosting, how account-shared and Duo / Self-Play modes differ, and what to check first.',
                ],
                'body_replacements' => [
                    [
                        'from' => 'If you are giving access to your Riot account, you are trusting another party with sensitive credentials. That means account-shared orders carry a higher privacy and access risk than duo or self-play style services.',
                        'to' => 'If you are giving access to your Riot account, you are trusting another party with sensitive credentials. That means account-shared orders carry a higher privacy and access risk than Duo / Self-Play services, where you stay involved in the games.',
                    ],
                    [
                        'from' => '- Duo or self-play style assistance usually gives the customer more control and more visibility, but it can take longer and depends more on schedule coordination.',
                        'to' => '- Duo / Self-Play assistance usually gives the customer more control and more visibility, but it can take longer and depends more on schedule coordination.',
                    ],
                    [
                        'from' => 'If you are deciding between modes, read [Duo vs Self-Play Valorant Boosting](/blog/duo-vs-self-play-valorant-boosting) before paying. Mode choice is one of the biggest levers in the entire safety discussion.',
                        'to' => 'If you are deciding between modes, read [Duo / Self-Play Valorant Boosting](/blog/duo-vs-self-play-valorant-boosting) before paying. Mode choice is one of the biggest levers in the entire safety discussion.',
                    ],
                    [
                        'from' => '- cannot explain the difference between account-shared and self-play workflows',
                        'to' => '- cannot explain how account-shared and Duo / Self-Play workflows differ',
                    ],
                ],
                'faq_replacements' => [
                    [
                        'from' => 'Is account-shared boosting riskier than duo or self-play?',
                        'to' => 'Is account-shared boosting riskier than Duo / Self-Play?',
                    ],
                    [
                        'from' => 'Usually yes, because it requires direct account access. Duo and self-play style options reduce that specific risk, but they can still involve schedule, coordination, and quality trade-offs.',
                        'to' => 'Usually yes, because it requires direct account access. Duo / Self-Play options reduce that specific risk, but they can still involve schedule, coordination, and quality trade-offs.',
                    ],
                ],
            ],
            'how-long-does-valorant-rank-boosting-take' => [
                ...$cta,
                'body_replacements' => [
                    [
                        'from' => 'Account-shared orders are often faster because the schedule is easier to control. Duo or self-play style assistance adds coordination and queue timing, so the pace can be slower even when the total target is the same.',
                        'to' => 'Account-shared orders are often faster because the schedule is easier to control. Duo / Self-Play assistance adds coordination and queue timing, so the pace can be slower even when the total target is the same.',
                    ],
                    [
                        'from' => 'If you are still choosing between approaches, [Duo vs Self-Play Valorant Boosting](/blog/duo-vs-self-play-valorant-boosting) breaks down the practical trade-offs.',
                        'to' => 'If you are still choosing between approaches, [Duo / Self-Play Valorant Boosting](/blog/duo-vs-self-play-valorant-boosting) breaks down the practical trade-offs.',
                    ],
                    [
                        'from' => 'Restrictions can slow delivery even when they are worthwhile. Preferences like self-play, specific roles, narrow queue windows, or extra handling requirements reduce flexibility and usually extend timelines.',
                        'to' => 'Restrictions can slow delivery even when they are worthwhile. Preferences like Duo / Self-Play, specific roles, narrow queue windows, or extra handling requirements reduce flexibility and usually extend timelines.',
                    ],
                    [
                        'from' => '- schedule coordination for duo/self-play work',
                        'to' => '- schedule coordination for Duo / Self-Play work',
                    ],
                ],
                'faq_replacements' => [
                    [
                        'from' => 'In many cases yes, because the provider can control scheduling more directly. Self-play or duo-style orders usually move slower because both sides must align on timing.',
                        'to' => 'In many cases yes, because the provider can control scheduling more directly. Duo / Self-Play orders usually move slower because both sides must align on timing.',
                    ],
                ],
            ],
            'duo-vs-self-play-valorant-boosting' => [
                ...$cta,
                'title' => [
                    'from' => 'Duo vs Self-Play Valorant Boosting: Which Option Fits Your Goal?',
                    'to' => 'VALORANT Duo Boosting / Self-Play: How It Works',
                ],
                'excerpt' => [
                    'from' => 'Duo and self-play style services give the customer more direct involvement, but they trade speed and flexibility for visibility and control. Choosing the right mode depends on your priorities, not just the headline price.',
                    'to' => 'Duo / Self-Play VALORANT boosting lets you stay in the games with your booster. Learn how it differs from account-shared boosting, where it helps, and what trade-offs to expect.',
                ],
                'intro' => [
                    'from' => 'The best boost mode is not always the fastest one. Some players care most about control and direct participation, while others prioritize speed, convenience, or a simpler delivery path.',
                    'to' => 'Duo and Self-Play refer to the same customer-involved boost mode here: you play alongside your booster instead of handing off the account for a fully managed run.',
                ],
                'meta_title' => [
                    'from' => 'Duo vs Self-Play Valorant Boosting: Which Mode Makes Sense?',
                    'to' => 'VALORANT Duo Boosting | Self-Play Boost For VALORANT',
                ],
                'meta_description' => [
                    'from' => 'Compare duo and self-play Valorant boosting options, including control, speed, coordination, account access, and which mode fits different goals.',
                    'to' => 'Learn how Duo / Self-Play VALORANT boosting works and how it compares with account-shared boosting.',
                ],
                'body_replacements' => [
                    [
                        'from' => 'That is why duo and self-play style services should be evaluated as workflow choices, not just billing options.',
                        'to' => 'On GGWP-Boost, Duo and Self-Play refer to the same customer-involved workflow, where you play alongside your booster instead of handing off the account for a fully managed run.',
                    ],
                    [
                        'from' => '## What duo-style boosting usually means',
                        'to' => '## How Duo / Self-Play VALORANT Boosting works',
                    ],
                    [
                        'from' => 'In a duo-style setup, the customer stays active and queues with a higher-skill player or coach-like partner.',
                        'to' => 'In a Duo / Self-Play setup, the customer stays active and queues with a higher-skill player or coach-like partner.',
                    ],
                    [
                        'from' => "## What self-play assistance usually means\n\nSelf-play style help keeps the customer actively playing, but the support model is often more structured around guidance, coordination, or a narrow service scope instead of a pure handoff. It can be a better fit for buyers who want to stay fully engaged in the climb.\n\nThe trade-off is that progress depends more on the customer's own schedule, consistency, and in-session execution.\n\n## Where account-shared orders still differ",
                        'to' => '## Where account-shared orders differ',
                    ],
                    [
                        'from' => 'Even though this article is about duo and self-play paths, it helps to understand why some customers still choose account-shared boosting: it is often the easiest mode to schedule and can be the fastest for straightforward climbs.',
                        'to' => 'Account-shared boosting is often easier to schedule and can be the fastest for straightforward climbs because the provider has more control over timing.',
                    ],
                    [
                        'from' => '### Duo or self-play advantages',
                        'to' => '### Duo / Self-Play advantages',
                    ],
                    [
                        'from' => '### Duo or self-play drawbacks',
                        'to' => '### Duo / Self-Play drawbacks',
                    ],
                    [
                        'from' => '## Which customers usually prefer duo or self-play?',
                        'to' => '## Which customers usually prefer Duo / Self-Play?',
                    ],
                    [
                        'from' => 'These modes are usually a stronger fit if you:',
                        'to' => 'This mode is usually a stronger fit if you:',
                    ],
                    [
                        'from' => 'They are usually a weaker fit if you:',
                        'to' => 'It is usually a weaker fit if you:',
                    ],
                    [
                        'from' => 'That is why mode appears so often in pricing discussions.',
                        'to' => 'That is why Duo / Self-Play appears so often in pricing discussions.',
                    ],
                    [
                        'from' => 'Duo and self-play style services are best for buyers who want more control and visibility.',
                        'to' => 'Duo / Self-Play services are best for buyers who want more control and visibility.',
                    ],
                ],
                'faq_replacements' => [
                    [
                        'from' => 'Is duo boosting usually slower than account-shared boosting?',
                        'to' => 'Is Duo / Self-Play usually slower than account-shared boosting?',
                    ],
                    [
                        'from' => 'Does self-play remove all risk?',
                        'to' => 'Does Self-Play remove all risk?',
                    ],
                    [
                        'from' => 'No. It can reduce concerns around direct account access, but it still depends on provider quality, coordination, and realistic expectations.',
                        'to' => 'No. Duo / Self-Play can reduce concerns around direct account access, but it still depends on provider quality, coordination, and realistic expectations.',
                    ],
                ],
            ],
            'best-valorant-boosting-services' => [
                ...$cta,
                'body_replacements' => [
                    [
                        'from' => 'Mode is important enough that it deserves its own comparison. Read [Duo vs Self-Play Valorant Boosting](/blog/duo-vs-self-play-valorant-boosting) if you have not chosen yet.',
                        'to' => 'Mode is important enough that it deserves its own explanation. Read [Duo / Self-Play Valorant Boosting](/blog/duo-vs-self-play-valorant-boosting) if you have not chosen yet.',
                    ],
                ],
            ],
            'how-to-rank-up-in-valorant-fast' => [
                ...$cta,
                'body_replacements' => [
                    [
                        'from' => 'If you are weighing those trade-offs, read [Is Valorant Boosting Safe?](/blog/is-valorant-boosting-safe) and [Duo vs Self-Play Valorant Boosting](/blog/duo-vs-self-play-valorant-boosting).',
                        'to' => 'If you are weighing those trade-offs, read [Is Valorant Boosting Safe?](/blog/is-valorant-boosting-safe) and [Duo / Self-Play Valorant Boosting](/blog/duo-vs-self-play-valorant-boosting).',
                    ],
                ],
            ],
            'valorant-placement-matches-explained' => $cta,
            'what-affects-valorant-boosting-price' => [
                ...$cta,
                'body_replacements' => [
                    [
                        'from' => 'That is why mode also affects time. If you want the full comparison, read [Duo vs Self-Play Valorant Boosting](/blog/duo-vs-self-play-valorant-boosting) and [How Long Does Valorant Rank Boosting Take](/blog/how-long-does-valorant-rank-boosting-take).',
                        'to' => 'That is why mode also affects time. If you want the full comparison, read [Duo / Self-Play Valorant Boosting](/blog/duo-vs-self-play-valorant-boosting) and [How Long Does Valorant Rank Boosting Take](/blog/how-long-does-valorant-rank-boosting-take).',
                    ],
                ],
            ],
            'valorant-radiant-boost-explained' => $cta,
        ];
    }

    protected function pageUpdates(): array
    {
        return [
            'home' => [
                'meta_title' => [
                    'from' => 'Home',
                    'to' => 'VALORANT Rank Boosting | Fast, Safe VALORANT Boost',
                ],
                'meta_description' => [
                    'from' => 'Secure high-elo Valorant boosting with verified boosters, live order tracking, and premium support.',
                    'to' => 'Get fast, safe VALORANT rank boosting with Solo and Duo / Self-Play options, live tracking, and clear pricing.',
                ],
                'content' => [
                    'hero.eyebrow' => [
                        'from' => 'VALORANT BOOSTING',
                        'to' => 'VALORANT RANK BOOSTING',
                    ],
                    'hero.headline' => [
                        'from' => 'Secure high-elo VALORANT boosting with elite boosters.',
                        'to' => 'Fast, Safe VALORANT Rank Boosting Built Around Your Goal.',
                    ],
                    'hero.description' => [
                        'from' => 'Fast, discreet rank boosting with live order tracking, verified boosters, and premium support from start to finish.',
                        'to' => 'Configure a VALORANT boost with Solo or Duo / Self-Play options, fair pricing, verified boosters, and live order tracking from start to finish.',
                    ],
                    'hero.primary_cta_label' => [
                        'from' => 'Start Boost',
                        'to' => 'Start My VALORANT Boost',
                    ],
                    'hero.trust_bullets' => [
                        'from' => [
                            ['text' => 'Verified Boosters'],
                            ['text' => 'Secure Account Handling'],
                            ['text' => 'Live Order Tracking'],
                        ],
                        'to' => [
                            ['text' => 'Verified Boosters'],
                            ['text' => 'Safe Account Handling'],
                            ['text' => 'Live Order Tracking'],
                            ['text' => 'Solo or Duo / Self-Play'],
                        ],
                    ],
                    'how_it_works.title' => [
                        'from' => 'How It Works',
                        'to' => 'How Your VALORANT Boost Works',
                    ],
                    'how_it_works.steps' => [
                        'from' => [
                            [
                                'title' => '1. Configure Your Boost',
                                'body' => 'Choose the service, set your ranks, queue preferences, region, and platform, then review the live quote.',
                            ],
                            [
                                'title' => '2. Customize + Secure Checkout',
                                'body' => 'Add premium options like solo queue, streaming, or express delivery, then lock in the order with secure checkout.',
                            ],
                            [
                                'title' => '3. Track Progress Live',
                                'body' => 'Follow rank progress, receive completion updates, and reach support quickly whenever you need order help.',
                            ],
                        ],
                        'to' => [
                            [
                                'title' => '1. Configure Your Boost',
                                'body' => 'Choose rank boosting for VALORANT, placement matches, ranked wins, or Radiant service, then set your ranks, region, platform, and boost mode.',
                            ],
                            [
                                'title' => '2. Customize and Checkout',
                                'body' => 'Pick useful add-ons, choose Solo or Duo / Self-Play where available, and review the live price before secure checkout.',
                            ],
                            [
                                'title' => '3. Track Progress Live',
                                'body' => 'Follow your VALORANT boost from the dashboard, receive completion updates, and reach support quickly whenever you need help.',
                            ],
                        ],
                    ],
                    'latest_blogs.title' => [
                        'from' => 'Latest From the Blog',
                        'to' => 'VALORANT Boosting Guides',
                    ],
                    'latest_blogs.description' => [
                        'from' => 'Fresh guides, service comparisons, pricing explainers, and rank-up insights pulled from the latest published articles.',
                        'to' => 'Fresh guides on VALORANT rank boosting, Duo / Self-Play choices, pricing factors, safety, and smarter ways to climb.',
                    ],
                    'latest_blogs.button_label' => [
                        'from' => 'Browse All Articles',
                        'to' => 'Read VALORANT Guides',
                    ],
                ],
            ],
            'blog-index' => [
                'meta_title' => [
                    'from' => 'Valorant Blog',
                    'to' => 'VALORANT Boosting Blog | Rank Boosting Guides',
                ],
                'meta_description' => [
                    'from' => 'Guides on Valorant rank boosting, duo queue choices, placements, pricing, and faster improvement paths.',
                    'to' => 'Read practical VALORANT rank boosting guides on Duo / Self-Play, pricing, safety, placements, and faster improvement paths.',
                ],
                'content' => [
                    'hero.eyebrow' => [
                        'from' => 'Content Hub',
                        'to' => 'VALORANT BOOSTING BLOG',
                    ],
                    'hero.headline' => [
                        'from' => 'Valorant Guides, Boosting Advice, and Smarter Decision-Making',
                        'to' => 'VALORANT Boosting Guides, Safety Advice, and Rank-Up Strategy',
                    ],
                    'hero.description' => [
                        'from' => 'Clear articles on queue choices, placement strategy, pricing factors, service comparisons, and realistic ways to climb faster without wasting time.',
                        'to' => 'Clear articles on VALORANT rank boosting, Duo / Self-Play choices, placement strategy, pricing factors, and realistic ways to climb faster without wasting time.',
                    ],
                    'hero.aside_title' => [
                        'from' => 'Want to compare service types?',
                        'to' => 'Compare VALORANT boost options',
                    ],
                    'hero.aside_description' => [
                        'from' => 'Jump straight to the service hub to compare boost modes, placement options, ranked wins, and Radiant paths.',
                        'to' => 'Jump to the service hub to compare rank boosting, placements, ranked wins, Radiant paths, and Duo / Self-Play modes.',
                    ],
                    'hero.cta_label' => [
                        'from' => 'Explore Services',
                        'to' => 'Explore VALORANT Boosts',
                    ],
                    'listing.title' => [
                        'from' => 'Latest Articles',
                        'to' => 'Latest VALORANT Boosting Articles',
                    ],
                    'listing.description' => [
                        'from' => 'Updated for maintainable long-term publishing, not one-off landing pages.',
                        'to' => 'Practical reading for safer orders, clearer pricing, and better VALORANT boost decisions.',
                    ],
                ],
            ],
            'faq' => [
                'meta_title' => [
                    'from' => 'FAQ',
                    'to' => 'VALORANT Boosting FAQ | Safety, Speed & Pricing',
                ],
                'meta_description' => [
                    'from' => 'Frequently asked questions about ordering, support, pricing, and account handling.',
                    'to' => 'Answers about VALORANT rank boosting, Duo / Self-Play, pricing, speed, account handling, refunds, and support.',
                ],
                'content' => [
                    'hero.headline' => [
                        'from' => 'Frequently Asked Questions',
                        'to' => 'VALORANT Boosting FAQ',
                    ],
                    'hero.description' => [
                        'from' => 'Everything customers usually ask before ordering a boost. If you still need help, our support team is ready to guide you.',
                        'to' => 'Everything customers usually ask before ordering a VALORANT boost, from safety and speed to Duo / Self-Play, pricing, and support.',
                    ],
                    'sidebar.description' => [
                        'from' => 'Reach out to support for questions about orders, pricing, account safety, or custom requests.',
                        'to' => 'Reach out for help with VALORANT boost pricing, account safety, Duo / Self-Play orders, or custom requests.',
                    ],
                    'sidebar.secondary_cta_label' => [
                        'from' => 'Start Boost',
                        'to' => 'Start VALORANT Boost',
                    ],
                    'listing.description' => [
                        'from' => 'Answers below are managed from your admin content page.',
                        'to' => 'Quick answers about safe VALORANT boosting, order flow, payment, and support.',
                    ],
                ],
            ],
            'contact' => [
                'meta_title' => [
                    'from' => 'Contact Us',
                    'to' => 'VALORANT Boosting Support & Contact',
                ],
                'meta_description' => [
                    'from' => 'Contact support for order help, billing questions, and custom requests.',
                    'to' => 'Contact GGWP-Boost support for VALORANT boost orders, pricing, billing, custom requests, or Duo / Self-Play guidance.',
                ],
                'content' => [
                    'info.title' => [
                        'from' => 'Need Help?',
                        'to' => 'Need VALORANT Boosting Help?',
                    ],
                    'notice.suffix' => [
                        'from' => 'server for 24/7 instant support!',
                        'to' => 'server for faster support.',
                    ],
                    'info.items' => [
                        'from' => [
                            [
                                'title' => 'Order Issues',
                                'body' => 'If your order is delayed or incomplete, include your Order ID so we can assist you faster.',
                            ],
                            [
                                'title' => 'Payments',
                                'body' => 'Questions about charges, refunds, or billing problems? Describe the issue in detail.',
                            ],
                            [
                                'title' => 'General Support',
                                'body' => 'For partnerships, business inquiries, or anything else, we usually respond within 24 hours.',
                            ],
                        ],
                        'to' => [
                            [
                                'title' => 'Order Issues',
                                'body' => 'If your VALORANT boost is delayed or needs review, include your Order ID so we can assist you faster.',
                            ],
                            [
                                'title' => 'Payments',
                                'body' => 'Questions about charges, refunds, cheap VALORANT boosting offers, or billing problems? Describe the issue in detail.',
                            ],
                            [
                                'title' => 'General Support',
                                'body' => 'For Duo / Self-Play questions, partnerships, business inquiries, or anything else, we usually respond within 24 hours.',
                            ],
                        ],
                    ],
                    'form.title' => [
                        'from' => 'Contact Us',
                        'to' => 'Contact VALORANT Boosting Support',
                    ],
                    'form.description' => [
                        'from' => 'Fill out the form below and we\'ll get back to you shortly.',
                        'to' => 'Send your question and we\'ll help with your order, quote, Duo / Self-Play setup, or custom VALORANT boost request.',
                    ],
                ],
            ],
            'become-booster' => [
                'meta_title' => [
                    'from' => 'Become a Booster',
                    'to' => 'Become a VALORANT Booster | Apply Today',
                ],
                'meta_description' => [
                    'from' => 'Apply to join the booster team and tell us about your experience.',
                    'to' => 'Apply to join GGWP-Boost as a VALORANT booster. Share your rank, experience, regions, and marketplace history for review.',
                ],
                'content' => [
                    'header.title' => [
                        'from' => 'Become a Booster',
                        'to' => 'Become a VALORANT Booster',
                    ],
                    'header.description' => [
                        'from' => 'Tell us about your experience and we will review your application. Fill in the form below, if you are selected, you will be contacted by us. Please don\'t open tickets or contact support team for jobs.',
                        'to' => 'Tell us about your VALORANT experience and we will review your application. If you are selected, our team will contact you directly. Please do not open support tickets for job requests.',
                    ],
                ],
            ],
            'reviews' => [
                'meta_title' => [
                    'from' => 'Reviews',
                    'to' => 'VALORANT Boosting Reviews | Customer Proof',
                ],
                'meta_description' => [
                    'from' => 'Customer reviews and social proof for GGWP-Boost.',
                    'to' => 'Read customer reviews and proof from completed VALORANT boost orders, rank boosting, delivery, and support.',
                ],
                'content' => [
                    'hero.title' => [
                        'from' => 'Reviews',
                        'to' => 'VALORANT Boosting Reviews',
                    ],
                    'hero.description' => [
                        'from' => 'Verified customer feedback, recent order highlights, and public proof from completed boosts.',
                        'to' => 'Verified customer feedback, recent order highlights, and public proof from completed VALORANT boost orders.',
                    ],
                ],
            ],
        ];
    }
};
