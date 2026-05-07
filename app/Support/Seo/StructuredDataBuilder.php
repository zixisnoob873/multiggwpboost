<?php

namespace App\Support\Seo;

use App\Models\BlogArticle;
use App\Support\PageTitle;
use DateTimeInterface;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StructuredDataBuilder
{
    public function home(array $content, array $seo, mixed $faqs = [], mixed $latestArticles = [], mixed $dateModified = null, ?array $game = null): array
    {
        $canonical = $this->canonical($seo, route('home'));
        $game = $this->schemaGame($game);
        $gameShortName = $this->gameShortName($game);
        $serviceId = $this->serviceId($game['slug'] ?? null);
        $faqNode = $this->faqPageNode($canonical, $faqs, "Homepage {$gameShortName} boosting FAQ");
        $howToNode = $this->howToNode($canonical, data_get($content, 'how_it_works', []));
        $articleListNode = $this->articleItemListNode(
            $canonical,
            $latestArticles,
            'latest-guides',
            "Latest {$gameShortName} boosting guides"
        );

        $hasPart = $this->references([$faqNode, $howToNode, $articleListNode]);
        $homeRoute = ($game['slug'] ?? 'valorant') === 'valorant'
            ? route('home')
            : route('games.show', ['game' => $game['slug']]);
        $breadcrumbs = [
            ['name' => 'Home', 'url' => route('home')],
        ];

        if (($game['slug'] ?? 'valorant') !== 'valorant') {
            $breadcrumbs[] = ['name' => $gameShortName, 'url' => $canonical];
        }

        return $this->graph([
            ...$this->baseNodes(),
            $this->webPageNode($canonical, $seo, [
                'type' => 'WebPage',
                'mainEntity' => ['@id' => $serviceId],
                'about' => $this->gameAbout($game),
                'mentions' => $this->boostingEntities(),
                'audience' => $this->audiences(["{$gameShortName} players comparing rank boosting options"]),
                'dateModified' => $this->date($dateModified),
                'significantLink' => [
                    $homeRoute.'#services',
                    $homeRoute.'#servicesTab',
                    route('faq'),
                    route('blog.index'),
                    route('reviews'),
                    route('contact'),
                    route('become-booster'),
                ],
                'hasPart' => $hasPart,
            ]),
            $this->breadcrumbNode($canonical, $breadcrumbs),
            $this->gameServiceNode($game, $homeRoute),
            $howToNode,
            $faqNode,
            $articleListNode,
        ]);
    }

    public function faq(array $content, array $seo, mixed $faqs = [], mixed $dateModified = null): array
    {
        $canonical = $this->canonical($seo, route('faq'));
        $faqNode = $this->faqPageNode($canonical, $faqs, 'VALORANT boosting questions and answers');

        return $this->graph([
            ...$this->baseNodes(),
            $this->webPageNode($canonical, $seo, [
                'type' => 'WebPage',
                'mainEntity' => $faqNode ? ['@id' => $faqNode['@id']] : null,
                'about' => $this->valorantAbout(),
                'mentions' => $this->boostingEntities([
                    'VALORANT boost safety',
                    'VALORANT boost pricing',
                    'VALORANT boost refunds',
                    'VALORANT Duo / Self-Play',
                ]),
                'audience' => $this->audiences(['Customers with questions before ordering a VALORANT boost']),
                'dateModified' => $this->date($dateModified),
                'significantLink' => [
                    route('contact'),
                    route('home').'#servicesTab',
                    route('terms-and-conditions'),
                    route('refund-policy'),
                ],
            ]),
            $this->breadcrumbNode($canonical, [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => data_get($content, 'hero.headline', 'VALORANT Boosting FAQ'), 'url' => $canonical],
            ]),
            $faqNode,
        ]);
    }

    public function contact(array $content, array $seo, mixed $dateModified = null): array
    {
        $canonical = $this->canonical($seo, route('contact'));
        $contactPoint = [
            '@type' => 'ContactPoint',
            '@id' => $canonical.'#support-contact',
            'name' => data_get($content, 'form.title', 'Contact VALORANT Boosting Support'),
            'contactType' => 'customer support',
            'url' => $canonical,
            'email' => config('footer.support.email'),
            'availableLanguage' => ['en'],
            'areaServed' => $this->serviceRegions(),
            'description' => data_get($content, 'form.description', $seo['description'] ?? null),
        ];

        return $this->graph([
            ...$this->baseNodes(),
            $this->webPageNode($canonical, $seo, [
                'type' => 'ContactPage',
                'mainEntity' => ['@id' => $contactPoint['@id']],
                'about' => $this->things([
                    'VALORANT boosting support',
                    'Order support',
                    'Payment support',
                    'Custom VALORANT boost requests',
                ]),
                'mentions' => $this->boostingEntities(),
                'audience' => $this->audiences(['Customers who need help with a VALORANT boost order or quote']),
                'dateModified' => $this->date($dateModified),
                'significantLink' => [
                    route('faq'),
                    route('refund-policy'),
                    route('terms-and-conditions'),
                    config('footer.support.community_url'),
                ],
            ]),
            $this->breadcrumbNode($canonical, [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Contact', 'url' => $canonical],
            ]),
            $contactPoint,
        ]);
    }

    public function becomeBooster(array $content, array $seo, mixed $dateModified = null): array
    {
        $canonical = $this->canonical($seo, route('become-booster'));
        $applyAction = [
            '@type' => 'ApplyAction',
            '@id' => $canonical.'#apply',
            'name' => 'Apply to become a VALORANT booster',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => $canonical,
                'actionPlatform' => [
                    'https://schema.org/DesktopWebPlatform',
                    'https://schema.org/MobileWebPlatform',
                ],
            ],
            'object' => [
                '@type' => 'Role',
                'roleName' => 'VALORANT booster',
                'description' => data_get($content, 'header.description', $seo['description'] ?? null),
            ],
        ];

        return $this->graph([
            ...$this->baseNodes(),
            $this->webPageNode($canonical, $seo, [
                'type' => 'WebPage',
                'mainEntity' => ['@id' => $applyAction['@id']],
                'about' => $this->things([
                    'VALORANT booster applications',
                    'Booster recruitment',
                    'VALORANT rank boosting work',
                ]),
                'mentions' => $this->boostingEntities(['VALORANT rank experience', 'booster regions', 'marketplace history']),
                'audience' => $this->audiences(['Experienced VALORANT players applying to join the booster team']),
                'dateModified' => $this->date($dateModified),
                'potentialAction' => ['@id' => $applyAction['@id']],
                'significantLink' => [
                    route('home'),
                    route('code-of-ethics'),
                    route('contact'),
                ],
            ]),
            $this->breadcrumbNode($canonical, [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => data_get($content, 'header.title', 'Become a VALORANT Booster'), 'url' => $canonical],
            ]),
            $applyAction,
        ]);
    }

    public function reviews(array $content, array $seo, mixed $reviews = [], mixed $dateModified = null): array
    {
        $canonical = $this->canonical($seo, route('reviews'));
        $reviewList = $this->reviewItemListNode($canonical, $reviews);

        return $this->graph([
            ...$this->baseNodes(),
            $this->webPageNode($canonical, $seo, [
                'type' => 'CollectionPage',
                'mainEntity' => $reviewList ? ['@id' => $reviewList['@id']] : null,
                'about' => $this->things(['VALORANT boosting reviews', 'Customer proof', 'Completed VALORANT boost orders']),
                'mentions' => $this->boostingEntities(),
                'audience' => $this->audiences(['Customers comparing proof before ordering a VALORANT boost']),
                'dateModified' => $this->date($dateModified),
                'significantLink' => [
                    route('home').'#servicesTab',
                    route('faq'),
                    route('contact'),
                ],
            ]),
            $this->breadcrumbNode($canonical, [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => data_get($content, 'hero.title', 'VALORANT Boosting Reviews'), 'url' => $canonical],
            ]),
            $reviewList,
        ]);
    }

    public function legalPage(string $pageKey, array $content, array $seo, mixed $dateModified = null): array
    {
        $canonical = $this->canonical($seo, route($pageKey));
        $document = [
            '@type' => 'DigitalDocument',
            '@id' => $canonical.'#policy-document',
            'name' => data_get($content, 'hero.title', $seo['title'] ?? Str::headline($pageKey)),
            'description' => data_get($content, 'hero.intro', $seo['description'] ?? null),
            'url' => $canonical,
            'publisher' => ['@id' => $this->organizationId()],
            'inLanguage' => 'en',
            'dateModified' => $this->date($dateModified),
            'about' => $this->policyTopics($pageKey),
            'audience' => $this->audiences($this->policyAudiences($pageKey)),
        ];

        return $this->graph([
            ...$this->baseNodes(),
            $this->webPageNode($canonical, $seo, [
                'type' => 'WebPage',
                'mainEntity' => ['@id' => $document['@id']],
                'about' => $this->policyTopics($pageKey),
                'mentions' => $this->boostingEntities(['customer accounts', 'payments', 'refunds', 'support']),
                'audience' => $this->audiences($this->policyAudiences($pageKey)),
                'dateModified' => $this->date($dateModified),
                'significantLink' => [
                    route('terms-and-conditions'),
                    route('privacy-policy'),
                    route('refund-policy'),
                    route('code-of-ethics'),
                    route('contact'),
                ],
            ]),
            $this->breadcrumbNode($canonical, [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => data_get($content, 'hero.title', $seo['title'] ?? Str::headline($pageKey)), 'url' => $canonical],
            ]),
            $document,
        ]);
    }

    public function checkout(array $seo): array
    {
        $canonical = $this->canonical($seo, route('checkout'));

        return $this->graph([
            ...$this->baseNodes(),
            $this->webPageNode($canonical, $seo, [
                'type' => 'WebPage',
                'name' => 'Secure VALORANT Boost Checkout',
                'mainEntity' => ['@id' => $this->serviceId()],
                'about' => $this->things([
                    'VALORANT boost checkout',
                    'Secure payment',
                    'Live boost quote',
                    'Order summary',
                ]),
                'mentions' => $this->boostingEntities(['Stripe payments', 'Cryptomus payments', 'promo codes']),
                'audience' => $this->audiences(['Customers ready to confirm a VALORANT boost quote and payment method']),
                'significantLink' => [
                    route('home').'#servicesTab',
                    route('terms-and-conditions'),
                    route('refund-policy'),
                    route('privacy-policy'),
                    route('contact'),
                ],
                'potentialAction' => [
                    '@type' => 'OrderAction',
                    'name' => 'Start a VALORANT boost order',
                    'target' => $canonical,
                    'object' => ['@id' => $this->serviceId()],
                ],
            ]),
            $this->breadcrumbNode($canonical, [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Checkout', 'url' => $canonical],
            ]),
            $this->valorantServiceNode(),
        ]);
    }

    public function servicePage(array $game, array $service, array $seo, mixed $faqs = [], mixed $relatedServices = []): array
    {
        $canonical = $this->canonical(
            $seo,
            route('games.services.show', [
                'game' => $game['slug'] ?? 'valorant',
                'service' => $service['slug'] ?? Str::slug((string) ($service['name'] ?? 'service')),
            ])
        );
        $gameShortName = $this->gameShortName($game);
        $serviceName = (string) ($service['name'] ?? 'Boosting Service');
        $serviceId = $canonical.'#service';
        $faqNode = $this->faqPageNode($canonical, $faqs, "{$gameShortName} {$serviceName} FAQ");
        $relatedList = $this->serviceItemListNode($canonical, $relatedServices);

        return $this->graph([
            ...$this->baseNodes(),
            $this->webPageNode($canonical, $seo, [
                'type' => 'WebPage',
                'mainEntity' => ['@id' => $serviceId],
                'about' => $this->gameAbout($game),
                'mentions' => $this->boostingEntities([$serviceName, "{$gameShortName} {$serviceName}"]),
                'audience' => $this->audiences(["{$gameShortName} players comparing {$serviceName} options"]),
                'significantLink' => [
                    route('games.show', ['game' => $game['slug'] ?? 'valorant']),
                    route('checkout', ['game' => $game['slug'] ?? 'valorant']),
                    route('faq'),
                    route('contact'),
                ],
                'hasPart' => $this->references([$faqNode, $relatedList]),
            ]),
            $this->breadcrumbNode($canonical, [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => $gameShortName, 'url' => route('games.show', ['game' => $game['slug'] ?? 'valorant'])],
                ['name' => $serviceName, 'url' => $canonical],
            ]),
            [
                '@type' => 'Service',
                '@id' => $serviceId,
                'name' => "{$gameShortName} {$serviceName}",
                'serviceType' => $serviceName,
                'category' => [
                    'Digital gaming service',
                    "{$gameShortName} boosting",
                    "{$gameShortName} {$serviceName}",
                ],
                'description' => $seo['description'] ?? data_get($service, 'description'),
                'provider' => ['@id' => $this->organizationId()],
                'url' => $canonical,
                'termsOfService' => route('terms-and-conditions'),
                'areaServed' => $this->serviceRegions(),
                'audience' => $this->audiences(["{$gameShortName} players ready to order {$serviceName}"]),
                'about' => $this->gameAbout($game),
            ],
            $faqNode,
            $relatedList,
        ]);
    }

    public function blogIndex(array $content, array $seo, mixed $articles): array
    {
        $canonical = $this->canonical($seo, route('blog.index'));
        $articleList = $this->articleItemListNode($canonical, $articles, 'articles', 'Latest VALORANT boosting articles');

        return $this->graph([
            ...$this->baseNodes(),
            $this->webPageNode($canonical, $seo, [
                'type' => 'CollectionPage',
                'mainEntity' => $articleList ? ['@id' => $articleList['@id']] : null,
                'about' => $this->things(['VALORANT boosting guides', 'Rank boosting safety', 'Boosting price education']),
                'mentions' => $this->boostingEntities(),
                'audience' => $this->audiences(['VALORANT players researching boosting options before ordering']),
                'significantLink' => [
                    route('home').'#servicesTab',
                    route('faq'),
                    route('contact'),
                ],
            ]),
            $this->breadcrumbNode($canonical, [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => data_get($content, 'hero.headline', 'VALORANT Boosting Blog'), 'url' => $canonical],
            ]),
            $articleList,
        ]);
    }

    public function blogArticle(BlogArticle $article, mixed $relatedArticles = []): array
    {
        $canonical = $article->effectiveCanonicalUrl();
        $articleId = $canonical.'#article';
        $faqNode = $this->faqPageNode($canonical, $article->faqItems(), 'Article FAQ');
        $relatedList = $this->articleItemListNode($canonical, $relatedArticles, 'related-articles', 'Related VALORANT boosting articles');

        return $this->graph([
            ...$this->baseNodes(),
            $this->webPageNode($canonical, [
                'title' => $article->effectiveMetaTitle(),
                'description' => $article->effectiveMetaDescription(),
            ], [
                'type' => 'WebPage',
                'mainEntity' => ['@id' => $articleId],
                'about' => $this->articleTopics($article),
                'mentions' => $this->boostingEntities(),
                'audience' => $this->audiences(['VALORANT players researching rank boosting decisions']),
                'datePublished' => $this->date($article->published_at),
                'dateModified' => $this->date($article->updated_at),
                'significantLink' => [
                    route('blog.index'),
                    route('home').'#servicesTab',
                    route('faq'),
                    route('contact'),
                ],
                'hasPart' => $this->references([$faqNode, $relatedList]),
            ]),
            $this->breadcrumbNode($canonical, [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Blog', 'url' => route('blog.index')],
                ['name' => $article->title, 'url' => $canonical],
            ]),
            [
                '@type' => 'BlogPosting',
                '@id' => $articleId,
                'headline' => $article->title,
                'description' => $article->effectiveMetaDescription(),
                'abstract' => $article->intro,
                'url' => $canonical,
                'mainEntityOfPage' => ['@id' => $canonical.'#webpage'],
                'isPartOf' => ['@id' => route('blog.index').'#webpage'],
                'author' => ['@id' => $this->organizationId()],
                'publisher' => ['@id' => $this->organizationId()],
                'datePublished' => $this->date($article->published_at),
                'dateModified' => $this->date($article->updated_at),
                'inLanguage' => 'en',
                'articleSection' => 'VALORANT boosting guides',
                'keywords' => $this->articleKeywords($article),
                'about' => $this->articleTopics($article),
                'mentions' => $this->boostingEntities(),
                'audience' => $this->audiences(['VALORANT players comparing boosting risks, pricing, timelines, or service modes']),
                'wordCount' => str_word_count(strip_tags((string) $article->renderedBody())),
            ],
            $faqNode,
            $relatedList,
        ]);
    }

    protected function baseNodes(): array
    {
        return [
            $this->organizationNode(),
            [
                '@type' => 'WebSite',
                '@id' => $this->websiteId(),
                'name' => PageTitle::BRAND,
                'url' => $this->siteUrl(),
                'publisher' => ['@id' => $this->organizationId()],
                'inLanguage' => 'en',
                'about' => $this->valorantAbout(),
                'audience' => $this->audiences(['VALORANT players, GGWP-Boost customers, and booster applicants']),
            ],
        ];
    }

    protected function organizationNode(): array
    {
        $socials = collect(config('footer.socials', []))
            ->pluck('url')
            ->map(fn (mixed $url): string => trim((string) $url))
            ->filter()
            ->values()
            ->all();

        return [
            '@type' => 'Organization',
            '@id' => $this->organizationId(),
            'name' => PageTitle::BRAND,
            'legalName' => config('footer.company.legal_name'),
            'url' => $this->siteUrl(),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => asset('assets/logo.png'),
            ],
            'sameAs' => $socials,
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
                'url' => route('contact'),
                'email' => config('footer.support.email'),
                'availableLanguage' => ['en'],
            ],
            'description' => config('footer.brand_copy'),
            'knowsAbout' => [
                'VALORANT rank boosting',
                'VALORANT Duo / Self-Play boosting',
                'VALORANT placement matches',
                'VALORANT ranked wins',
                'VALORANT Radiant boost services',
                'Game boosting order support',
            ],
        ];
    }

    protected function webPageNode(string $canonical, array $seo, array $extra = []): array
    {
        $type = $extra['type'] ?? 'WebPage';
        unset($extra['type']);

        return array_merge([
            '@type' => $type,
            '@id' => $canonical.'#webpage',
            'name' => $extra['name'] ?? ($seo['title'] ?? null),
            'description' => $seo['description'] ?? null,
            'url' => $canonical,
            'isPartOf' => ['@id' => $this->websiteId()],
            'publisher' => ['@id' => $this->organizationId()],
            'inLanguage' => 'en',
            'breadcrumb' => ['@id' => $canonical.'#breadcrumb'],
        ], $extra);
    }

    protected function valorantServiceNode(): array
    {
        return [
            '@type' => 'Service',
            '@id' => $this->serviceId(),
            'name' => 'GGWP-Boost VALORANT rank boosting',
            'serviceType' => 'VALORANT rank boosting',
            'category' => [
                'Digital gaming service',
                'VALORANT rank boosting',
                'VALORANT placement matches',
                'VALORANT ranked wins',
                'VALORANT Radiant boost',
            ],
            'description' => 'GGWP-Boost provides VALORANT rank boosting, placement matches, ranked wins, and Radiant services with live pricing and order tracking.',
            'provider' => ['@id' => $this->organizationId()],
            'url' => route('home').'#services',
            'termsOfService' => route('terms-and-conditions'),
            'areaServed' => $this->serviceRegions(),
            'audience' => $this->audiences(['VALORANT players who want a configured boost, clear pricing, and support']),
            'about' => $this->valorantAbout(),
            'hasOfferCatalog' => [
                '@type' => 'OfferCatalog',
                'name' => 'VALORANT boost service options',
                'itemListElement' => [
                    $this->serviceOffer('VALORANT Rank Boosting', 'Rank Boosting', route('home').'#tab-boosting'),
                    $this->serviceOffer('VALORANT Placement Matches', 'Placement Matches', route('home').'#tab-placement'),
                    $this->serviceOffer('VALORANT Radiant Boost', 'Radiant Boost', route('home').'#tab-radiant'),
                    $this->serviceOffer('VALORANT Ranked Wins', 'Ranked Wins', route('home').'#tab-ranked'),
                ],
            ],
        ];
    }

    protected function gameServiceNode(array $game, string $homeRoute): array
    {
        $slug = (string) ($game['slug'] ?? 'valorant');
        $gameShortName = $this->gameShortName($game);
        $serviceNames = collect($game['services'] ?? [])
            ->pluck('name')
            ->filter()
            ->values();

        if ($serviceNames->isEmpty()) {
            $serviceNames = collect(['Rank Boosting', 'Placement Matches', 'Radiant Boost', 'Ranked Wins']);
        }

        return [
            '@type' => 'Service',
            '@id' => $this->serviceId($slug),
            'name' => "GGWP-Boost {$gameShortName} rank boosting",
            'serviceType' => "{$gameShortName} rank boosting",
            'category' => $serviceNames
                ->map(fn (string $service): string => "{$gameShortName} {$service}")
                ->prepend('Digital gaming service')
                ->values()
                ->all(),
            'description' => "GGWP-Boost provides {$gameShortName} boosting services with live pricing and order tracking.",
            'provider' => ['@id' => $this->organizationId()],
            'url' => $homeRoute.'#services',
            'termsOfService' => route('terms-and-conditions'),
            'areaServed' => $this->serviceRegions(),
            'audience' => $this->audiences(["{$gameShortName} players who want a configured boost, clear pricing, and support"]),
            'about' => $this->gameAbout($game),
            'hasOfferCatalog' => [
                '@type' => 'OfferCatalog',
                'name' => "{$gameShortName} boost service options",
                'itemListElement' => $serviceNames
                    ->map(fn (string $service): array => $this->serviceOffer("{$gameShortName} {$service}", $service, $homeRoute.'#servicesTab'))
                    ->values()
                    ->all(),
            ],
        ];
    }

    protected function serviceOffer(string $name, string $serviceType, string $url): array
    {
        return [
            '@type' => 'Offer',
            'url' => $url,
            'availability' => 'https://schema.org/InStock',
            'itemOffered' => [
                '@type' => 'Service',
                'name' => $name,
                'serviceType' => $serviceType,
                'provider' => ['@id' => $this->organizationId()],
                'areaServed' => $this->serviceRegions(),
            ],
        ];
    }

    protected function faqPageNode(string $canonical, mixed $faqs, string $name): ?array
    {
        $items = $this->items($faqs)
            ->map(function (mixed $faq): ?array {
                $question = $this->plain(data_get($faq, 'question'));
                $answer = $this->plain(data_get($faq, 'answer'));

                if ($question === '' || $answer === '') {
                    return null;
                }

                return [
                    '@type' => 'Question',
                    'name' => $question,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $answer,
                    ],
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($items === []) {
            return null;
        }

        return [
            '@type' => 'FAQPage',
            '@id' => $canonical.'#faq',
            'name' => $name,
            'url' => $canonical,
            'inLanguage' => 'en',
            'mainEntity' => $items,
        ];
    }

    protected function howToNode(string $canonical, array $howItWorks): ?array
    {
        $steps = collect(data_get($howItWorks, 'steps', []))
            ->map(function (mixed $step, int $index): ?array {
                $name = $this->plain(data_get($step, 'title'));
                $text = $this->plain(data_get($step, 'body'));

                if ($name === '' || $text === '') {
                    return null;
                }

                return [
                    '@type' => 'HowToStep',
                    'position' => $index + 1,
                    'name' => $name,
                    'text' => $text,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($steps === []) {
            return null;
        }

        return [
            '@type' => 'HowTo',
            '@id' => $canonical.'#how-it-works',
            'name' => data_get($howItWorks, 'title', 'How Your VALORANT Boost Works'),
            'description' => 'Step-by-step flow for configuring, checking out, and tracking a GGWP-Boost VALORANT order.',
            'url' => route('home').'#howItWorksHeading',
            'step' => $steps,
            'mainEntityOfPage' => ['@id' => $canonical.'#webpage'],
        ];
    }

    protected function articleItemListNode(string $canonical, mixed $articles, string $fragment, string $name): ?array
    {
        $items = $this->items($articles)
            ->map(function (mixed $article, int $index): ?array {
                if (! $article instanceof BlogArticle) {
                    return null;
                }

                $url = $article->effectiveCanonicalUrl();

                return [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'url' => $url,
                    'name' => $article->title,
                    'item' => [
                        '@type' => 'BlogPosting',
                        '@id' => $url.'#article',
                        'headline' => $article->title,
                        'description' => $article->effectiveMetaDescription(),
                        'url' => $url,
                        'datePublished' => $this->date($article->published_at),
                        'dateModified' => $this->date($article->updated_at),
                    ],
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($items === []) {
            return null;
        }

        return [
            '@type' => 'ItemList',
            '@id' => $canonical.'#'.$fragment,
            'name' => $name,
            'itemListElement' => $items,
        ];
    }

    protected function serviceItemListNode(string $canonical, mixed $services): ?array
    {
        $items = $this->items($services)
            ->map(function (mixed $service, int $index): ?array {
                $name = $this->plain(data_get($service, 'name'));
                $url = trim((string) data_get($service, 'url'));

                if ($name === '' || $url === '') {
                    return null;
                }

                return [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'url' => $url,
                    'name' => $name,
                    'item' => [
                        '@type' => 'Service',
                        '@id' => $url.'#service',
                        'name' => trim(data_get($service, 'gameShortName', '').' '.$name),
                        'url' => $url,
                        'provider' => ['@id' => $this->organizationId()],
                    ],
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($items === []) {
            return null;
        }

        return [
            '@type' => 'ItemList',
            '@id' => $canonical.'#related-services',
            'name' => 'Related boosting services',
            'itemListElement' => $items,
        ];
    }

    protected function reviewItemListNode(string $canonical, mixed $reviews): ?array
    {
        $items = $this->items($reviews)
            ->map(function (mixed $review, int $index): ?array {
                $quote = $this->plain(data_get($review, 'quote'));
                $author = $this->plain(data_get($review, 'author_name'));
                $service = $this->plain(data_get($review, 'service'));

                if ($quote === '' || $author === '') {
                    return null;
                }

                return [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'item' => [
                        '@type' => 'Review',
                        'name' => trim($service.' customer review'),
                        'reviewBody' => $quote,
                        'author' => [
                            '@type' => 'Person',
                            'name' => $author,
                        ],
                        'itemReviewed' => ['@id' => $this->serviceId()],
                    ],
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($items === []) {
            return null;
        }

        return [
            '@type' => 'ItemList',
            '@id' => $canonical.'#customer-reviews',
            'name' => 'VALORANT boosting customer reviews',
            'itemListElement' => $items,
        ];
    }

    protected function breadcrumbNode(string $canonical, array $crumbs): array
    {
        return [
            '@type' => 'BreadcrumbList',
            '@id' => $canonical.'#breadcrumb',
            'itemListElement' => collect($crumbs)
                ->map(fn (array $crumb, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $crumb['name'],
                    'item' => $crumb['url'],
                ])
                ->values()
                ->all(),
        ];
    }

    protected function graph(array $nodes): array
    {
        return $this->clean([
            '@context' => 'https://schema.org',
            '@graph' => collect($nodes)->filter()->values()->all(),
        ]);
    }

    protected function canonical(array $seo, string $fallback): string
    {
        $canonical = trim((string) ($seo['canonical'] ?? ''));

        return $canonical !== '' ? $canonical : $fallback;
    }

    protected function items(mixed $items): Collection
    {
        if ($items instanceof AbstractPaginator) {
            return collect($items->items());
        }

        if ($items instanceof Collection) {
            return $items->values();
        }

        if (is_array($items)) {
            return collect($items)->values();
        }

        if ($items instanceof \Traversable) {
            return collect(iterator_to_array($items, false));
        }

        return collect();
    }

    protected function references(array $nodes): array
    {
        return collect($nodes)
            ->filter(fn (mixed $node): bool => is_array($node) && isset($node['@id']))
            ->map(fn (array $node): array => ['@id' => $node['@id']])
            ->values()
            ->all();
    }

    protected function valorantAbout(): array
    {
        return [
            [
                '@type' => 'VideoGame',
                'name' => 'VALORANT',
                'sameAs' => 'https://playvalorant.com/',
            ],
            [
                '@type' => 'Thing',
                'name' => 'VALORANT rank boosting',
            ],
        ];
    }

    protected function gameAbout(?array $game): array
    {
        $game = $this->schemaGame($game);
        $name = (string) ($game['name'] ?? $game['shortName'] ?? 'VALORANT');
        $shortName = $this->gameShortName($game);
        $sameAs = data_get($game, 'metadata.same_as');

        return $this->clean([
            [
                '@type' => 'VideoGame',
                'name' => $shortName,
                'sameAs' => is_string($sameAs) && trim($sameAs) !== '' ? $sameAs : null,
            ],
            [
                '@type' => 'Thing',
                'name' => "{$name} rank boosting",
            ],
        ]);
    }

    protected function schemaGame(?array $game): array
    {
        return is_array($game) && $game !== []
            ? $game
            : [
                'slug' => 'valorant',
                'name' => 'Valorant',
                'shortName' => 'VALORANT',
            ];
    }

    protected function gameShortName(array $game): string
    {
        return (string) ($game['shortName'] ?? $game['name'] ?? 'VALORANT');
    }

    protected function boostingEntities(array $extra = []): array
    {
        return $this->things(array_merge([
            'Rank Boosting',
            'Placement Matches',
            'Ranked Wins',
            'Radiant Boost',
            'Duo / Self-Play',
            'Live order tracking',
            'Verified boosters',
        ], $extra));
    }

    protected function policyTopics(string $pageKey): array
    {
        return $this->things(match ($pageKey) {
            'privacy-policy' => ['Privacy Policy', 'Personal data', 'Order data', 'Support data'],
            'refund-policy' => ['Refund Policy', 'Refund eligibility', 'Partial refunds', 'Payment provider processing'],
            'code-of-ethics' => ['Code of Ethics', 'Customer conduct', 'Booster conduct', 'Privacy and confidentiality'],
            default => ['Terms and Conditions', 'Service scope', 'Account responsibility', 'Payment terms', 'Prohibited activities'],
        });
    }

    protected function policyAudiences(string $pageKey): array
    {
        return match ($pageKey) {
            'code-of-ethics' => ['GGWP-Boost customers, boosters, and staff'],
            default => ['Customers using GGWP-Boost services and website visitors'],
        };
    }

    protected function articleTopics(BlogArticle $article): array
    {
        $source = Str::lower($article->title.' '.$article->excerpt);
        $topics = ['VALORANT boosting guides'];

        foreach ([
            'safe' => 'VALORANT boosting safety',
            'risk' => 'VALORANT boosting risk',
            'duo' => 'Duo / Self-Play VALORANT boosting',
            'self-play' => 'Duo / Self-Play VALORANT boosting',
            'price' => 'VALORANT boosting price',
            'pricing' => 'VALORANT boosting price',
            'placement' => 'VALORANT placement matches',
            'radiant' => 'VALORANT Radiant boost',
            'rank up' => 'VALORANT rank improvement',
            'time' => 'VALORANT boosting timelines',
        ] as $needle => $topic) {
            if (Str::contains($source, $needle)) {
                $topics[] = $topic;
            }
        }

        return $this->things(array_values(array_unique($topics)));
    }

    protected function articleKeywords(BlogArticle $article): array
    {
        return collect($this->articleTopics($article))
            ->pluck('name')
            ->merge(['VALORANT', 'GGWP-Boost'])
            ->unique()
            ->values()
            ->all();
    }

    protected function things(array $names): array
    {
        return collect($names)
            ->map(fn (mixed $name): string => trim((string) $name))
            ->filter()
            ->unique()
            ->map(fn (string $name): array => [
                '@type' => 'Thing',
                'name' => $name,
            ])
            ->values()
            ->all();
    }

    protected function audiences(array $audiences): array
    {
        return collect($audiences)
            ->map(fn (mixed $audience): string => trim((string) $audience))
            ->filter()
            ->unique()
            ->map(fn (string $audience): array => [
                '@type' => 'Audience',
                'audienceType' => $audience,
            ])
            ->values()
            ->all();
    }

    protected function serviceRegions(): array
    {
        return collect(['NA', 'EU', 'AP', 'OCE', 'MENA', 'LATAM'])
            ->map(fn (string $name): array => [
                '@type' => 'AdministrativeArea',
                'name' => $name,
            ])
            ->values()
            ->all();
    }

    protected function plain(mixed $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)) ?? '');
    }

    protected function date(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        $date = trim((string) $value);

        return $date !== '' ? $date : null;
    }

    protected function siteUrl(): string
    {
        return rtrim(url('/'), '/');
    }

    protected function organizationId(): string
    {
        return $this->siteUrl().'#organization';
    }

    protected function websiteId(): string
    {
        return $this->siteUrl().'#website';
    }

    protected function serviceId(?string $gameSlug = null): string
    {
        $slug = Str::slug((string) ($gameSlug ?: 'valorant'));

        return $this->siteUrl()."#{$slug}-boosting-service";
    }

    protected function clean(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);
        $cleaned = [];

        foreach ($value as $key => $item) {
            $item = $this->clean($item);

            if ($item === null || $item === '' || $item === []) {
                continue;
            }

            if ($isList) {
                $cleaned[] = $item;
            } else {
                $cleaned[$key] = $item;
            }
        }

        return $cleaned;
    }
}
