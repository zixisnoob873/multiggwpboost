<?php

namespace App\Support\Cms;

use App\Rules\PublicUrl;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class PageRegistry
{
    public function definitions(): array
    {
        return [
            'home' => [
                'key' => 'home',
                'label' => 'Home',
                'route_name' => 'home',
                'seo' => [
                    'title' => 'Premium Game Boosting Services for Every Competitive Title',
                    'description' => 'Rank up faster with professional boosters across VALORANT, League of Legends, CS2, Apex Legends, Call of Duty, Overwatch 2, Rocket League, Diablo 4, and more.',
                    'canonical' => null,
                    'robots' => 'index,follow',
                    'type' => 'website',
                    'include_in_sitemap' => true,
                ],
                'content' => [
                    'hero' => [
                        'eyebrow' => 'Multi-game boosting marketplace',
                        'headline' => 'Premium Game Boosting Services for Every Competitive Title',
                        'description' => 'Rank up faster with professional boosters across VALORANT, League, CS2, Apex Legends, Call of Duty, Overwatch 2, Rocket League, Diablo 4, and more.',
                        'primary_cta_label' => 'Order Now',
                        'primary_cta_url' => '/checkout',
                        'secondary_cta_label' => 'Browse Games',
                        'secondary_cta_url' => '/#featured-games',
                        'trust_bullets' => [
                            ['text' => 'Professional Boosters'],
                            ['text' => 'VPN Protection'],
                            ['text' => 'Secure Payments'],
                            ['text' => '24/7 Support'],
                        ],
                    ],
                    'how_it_works' => [
                        'title' => 'How Your Boost Works',
                        'steps' => [
                            [
                                'title' => '1. Choose a game',
                                'body' => 'Pick the competitive title and service that matches your goal.',
                            ],
                            [
                                'title' => '2. Set your target',
                                'body' => 'Review rank, region, platform, delivery mode, and add-ons before checkout.',
                            ],
                            [
                                'title' => '3. Track delivery',
                                'body' => 'Follow progress, chat with support, and receive completion updates in your workspace.',
                            ],
                        ],
                    ],
                    'latest_blogs' => [
                        'title' => 'Game Boosting Guides',
                        'description' => 'Fresh guides on boosting safety, pricing, delivery modes, and smarter ways to reach your competitive goals.',
                        'button_label' => 'Read Guides',
                    ],
                ],
                'sections' => [
                    [
                        'title' => 'Hero',
                        'fields' => [
                            $this->textField('hero.eyebrow', 'Eyebrow', 120),
                            $this->textareaField('hero.headline', 'Headline', 3, 255),
                            $this->textareaField('hero.description', 'Supporting Text', 4, 600),
                            $this->textField('hero.primary_cta_label', 'Primary CTA Label', 120),
                            $this->urlField('hero.primary_cta_url', 'Primary CTA URL'),
                            $this->textField('hero.secondary_cta_label', 'Secondary CTA Label', 120),
                            $this->urlField('hero.secondary_cta_url', 'Secondary CTA URL'),
                            $this->repeaterField('hero.trust_bullets', 'Trust Bullets', 6, [
                                $this->textField('text', 'Bullet Text', 120),
                            ]),
                        ],
                    ],
                    [
                        'title' => 'How It Works',
                        'fields' => [
                            $this->textField('how_it_works.title', 'Section Title', 120),
                            $this->repeaterField('how_it_works.steps', 'Steps', 5, [
                                $this->textField('title', 'Step Title', 160),
                                $this->textareaField('body', 'Step Description', 3, 500),
                            ]),
                        ],
                    ],
                    [
                        'title' => 'Latest Blog Teaser',
                        'fields' => [
                            $this->textField('latest_blogs.title', 'Section Title', 120),
                            $this->textareaField('latest_blogs.description', 'Section Description', 3, 500),
                            $this->textField('latest_blogs.button_label', 'Button Label', 120),
                        ],
                    ],
                ],
            ],
            'blog-index' => [
                'key' => 'blog-index',
                'label' => 'Blog Index',
                'route_name' => 'blog.index',
                'seo' => [
                    'title' => 'VALORANT Boosting Blog | Rank Boosting Guides',
                    'description' => 'Read practical VALORANT rank boosting guides on Duo / Self-Play, pricing, safety, placements, and faster improvement paths.',
                    'canonical' => null,
                    'robots' => 'index,follow',
                    'type' => 'website',
                    'include_in_sitemap' => true,
                ],
                'content' => [
                    'hero' => [
                        'eyebrow' => 'VALORANT BOOSTING BLOG',
                        'headline' => 'VALORANT Boosting Guides, Safety Advice, and Rank-Up Strategy',
                        'description' => 'Clear articles on VALORANT rank boosting, Duo / Self-Play choices, placement strategy, pricing factors, and realistic ways to climb faster without wasting time.',
                        'aside_title' => 'Compare VALORANT boost options',
                        'aside_description' => 'Jump to the service hub to compare rank boosting, placements, ranked wins, Radiant paths, and Duo / Self-Play modes.',
                        'cta_label' => 'Explore VALORANT Boosts',
                        'cta_url' => '/game/valorant/rank-boosting',
                    ],
                    'listing' => [
                        'title' => 'Latest VALORANT Boosting Articles',
                        'description' => 'Practical reading for safer orders, clearer pricing, and better VALORANT boost decisions.',
                    ],
                ],
                'sections' => [
                    [
                        'title' => 'Hero',
                        'fields' => [
                            $this->textField('hero.eyebrow', 'Eyebrow', 120),
                            $this->textareaField('hero.headline', 'Headline', 3, 255),
                            $this->textareaField('hero.description', 'Description', 4, 600),
                            $this->textField('hero.aside_title', 'Aside Heading', 120),
                            $this->textareaField('hero.aside_description', 'Aside Description', 4, 500),
                            $this->textField('hero.cta_label', 'CTA Label', 120),
                            $this->urlField('hero.cta_url', 'CTA URL'),
                        ],
                    ],
                    [
                        'title' => 'Article Listing',
                        'fields' => [
                            $this->textField('listing.title', 'Section Title', 120),
                            $this->textareaField('listing.description', 'Section Note', 3, 255),
                        ],
                    ],
                ],
            ],
            'faq' => [
                'key' => 'faq',
                'label' => 'FAQ Page',
                'route_name' => 'faq',
                'seo' => [
                    'title' => 'VALORANT Boosting FAQ | Safety, Speed & Pricing',
                    'description' => 'Answers about VALORANT rank boosting, Duo / Self-Play, pricing, speed, account handling, refunds, and support.',
                    'canonical' => null,
                    'robots' => 'index,follow',
                    'type' => 'website',
                    'include_in_sitemap' => true,
                ],
                'content' => [
                    'hero' => [
                        'eyebrow' => 'Support Center',
                        'headline' => 'VALORANT Boosting FAQ',
                        'description' => 'Everything customers usually ask before ordering a VALORANT boost, from safety and speed to Duo / Self-Play, pricing, and support.',
                    ],
                    'sidebar' => [
                        'title' => 'Need a faster answer?',
                        'description' => 'Reach out for help with VALORANT boost pricing, account safety, Duo / Self-Play orders, or custom requests.',
                        'primary_cta_label' => 'Contact Support',
                        'primary_cta_url' => '/contact',
                        'secondary_cta_label' => 'Start VALORANT Boost',
                        'secondary_cta_url' => '/game/valorant/rank-boosting',
                    ],
                    'listing' => [
                        'title' => 'Common Questions',
                        'description' => 'Quick answers about safe VALORANT boosting, order flow, payment, and support.',
                    ],
                ],
                'sections' => [
                    [
                        'title' => 'Hero',
                        'fields' => [
                            $this->textField('hero.eyebrow', 'Eyebrow', 120),
                            $this->textField('hero.headline', 'Headline', 160),
                            $this->textareaField('hero.description', 'Description', 4, 500),
                        ],
                    ],
                    [
                        'title' => 'Sidebar CTA',
                        'fields' => [
                            $this->textField('sidebar.title', 'CTA Heading', 120),
                            $this->textareaField('sidebar.description', 'CTA Description', 4, 400),
                            $this->textField('sidebar.primary_cta_label', 'Primary Button Label', 120),
                            $this->urlField('sidebar.primary_cta_url', 'Primary Button URL'),
                            $this->textField('sidebar.secondary_cta_label', 'Secondary Button Label', 120),
                            $this->urlField('sidebar.secondary_cta_url', 'Secondary Button URL'),
                        ],
                    ],
                    [
                        'title' => 'FAQ Listing',
                        'fields' => [
                            $this->textField('listing.title', 'Section Title', 120),
                            $this->textareaField('listing.description', 'Section Description', 3, 255),
                        ],
                    ],
                ],
            ],
            'contact' => [
                'key' => 'contact',
                'label' => 'Contact',
                'route_name' => 'contact',
                'seo' => [
                    'title' => 'VALORANT Boosting Support & Contact',
                    'description' => 'Contact GGWP-Boost support for VALORANT boost orders, pricing, billing, custom requests, or Duo / Self-Play guidance.',
                    'canonical' => null,
                    'robots' => 'index,follow',
                    'type' => 'website',
                    'include_in_sitemap' => true,
                ],
                'content' => [
                    'notice' => [
                        'text' => 'We usually respond in 6-12 hours.',
                        'link_label' => 'Discord',
                        'link_url' => 'https://discord.gg/2FD3qq9U',
                        'suffix' => 'server for faster support.',
                    ],
                    'info' => [
                        'title' => 'Need VALORANT Boosting Help?',
                        'items' => [
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
                    'form' => [
                        'title' => 'Contact VALORANT Boosting Support',
                        'description' => 'Send your question and we\'ll help with your order, quote, Duo / Self-Play setup, or custom VALORANT boost request.',
                    ],
                ],
                'sections' => [
                    [
                        'title' => 'Top Notice',
                        'fields' => [
                            $this->textField('notice.text', 'Notice Text', 160),
                            $this->textField('notice.link_label', 'Link Label', 60),
                            $this->urlField('notice.link_url', 'Link URL'),
                            $this->textField('notice.suffix', 'Notice Suffix', 160),
                        ],
                    ],
                    [
                        'title' => 'Support Info Card',
                        'fields' => [
                            $this->textField('info.title', 'Card Title', 120),
                            $this->repeaterField('info.items', 'Info Sections', 6, [
                                $this->textField('title', 'Section Title', 120),
                                $this->textareaField('body', 'Section Description', 3, 400),
                            ]),
                        ],
                    ],
                    [
                        'title' => 'Contact Form Intro',
                        'fields' => [
                            $this->textField('form.title', 'Form Title', 120),
                            $this->textareaField('form.description', 'Form Description', 3, 300),
                        ],
                    ],
                ],
            ],
            'become-booster' => [
                'key' => 'become-booster',
                'label' => 'Become a Booster',
                'route_name' => 'become-booster',
                'seo' => [
                    'title' => 'Become a VALORANT Booster | Apply Today',
                    'description' => 'Apply to join GGWP-Boost as a VALORANT booster. Share your rank, experience, regions, and marketplace history for review.',
                    'canonical' => null,
                    'robots' => 'index,follow',
                    'type' => 'website',
                    'include_in_sitemap' => true,
                ],
                'content' => [
                    'header' => [
                        'title' => 'Become a VALORANT Booster',
                        'description' => 'Tell us about your VALORANT experience and we will review your application. If you are selected, our team will contact you directly. Please do not open support tickets for job requests.',
                        'back_label' => 'Back Home',
                    ],
                ],
                'sections' => [
                    [
                        'title' => 'Page Header',
                        'fields' => [
                            $this->textField('header.title', 'Headline', 160),
                            $this->textareaField('header.description', 'Supporting Text', 5, 700),
                            $this->textField('header.back_label', 'Back Button Label', 80),
                        ],
                    ],
                ],
            ],
            'reviews' => [
                'key' => 'reviews',
                'label' => 'Reviews',
                'route_name' => 'reviews',
                'seo' => [
                    'title' => 'VALORANT Boosting Reviews | Customer Proof',
                    'description' => 'Read customer reviews and proof from completed VALORANT boost orders, rank boosting, delivery, and support.',
                    'canonical' => null,
                    'robots' => 'index,follow',
                    'type' => 'website',
                    'include_in_sitemap' => true,
                ],
                'content' => [
                    'hero' => [
                        'title' => 'VALORANT Boosting Reviews',
                        'description' => 'Verified customer feedback, recent order highlights, and public proof from completed VALORANT boost orders.',
                    ],
                ],
                'sections' => [
                    [
                        'title' => 'Page Copy',
                        'fields' => [
                            $this->textField('hero.title', 'Headline', 120),
                            $this->textareaField('hero.description', 'Description', 4, 400),
                        ],
                    ],
                ],
            ],
            'code-of-ethics' => $this->legalDefinition(
                'code-of-ethics',
                'Code of Ethics',
                'code-of-ethics',
                'This Code of Ethics outlines the standards of conduct expected from customers, boosters, and staff. By using our services, you agree to follow these principles to help keep the experience professional, safe, and respectful.',
                [
                    [
                        'title' => 'Fair conduct',
                        'body' => '',
                        'bullets_text' => "Provide accurate information about your account, rank, and requirements.\nDo not attempt to manipulate results through fraudulent chargebacks, false disputes, or intentional sabotage.",
                    ],
                    [
                        'title' => 'Respectful behavior',
                        'body' => '',
                        'bullets_text' => "Communicate professionally and respectfully with boosters and staff.\nDo not use abusive language, threats, or intimidation.",
                    ],
                    [
                        'title' => 'No harassment or hate speech',
                        'body' => '',
                        'bullets_text' => "Harassment, hate speech, discrimination, or slurs are strictly prohibited.\nWe may suspend or terminate service immediately for violations.",
                    ],
                    [
                        'title' => 'Privacy and confidentiality',
                        'body' => '',
                        'bullets_text' => "Do not share private information (yours or others') without consent.\nAny credentials or sensitive data must be handled responsibly and only for order fulfillment.",
                    ],
                    [
                        'title' => 'No fraud or scams',
                        'body' => '',
                        'bullets_text' => "Do not attempt to scam boosters, staff, or other customers.\nDo not submit stolen payment methods, stolen accounts, or misleading identity information.",
                    ],
                    [
                        'title' => 'Responsible communication',
                        'body' => '',
                        'bullets_text' => "Use the built-in chat for order-related updates and keep requests clear and reasonable.\nDo not demand actions outside the purchased scope.",
                    ],
                    [
                        'title' => 'Consequences for violations',
                        'body' => '',
                        'bullets_text' => "Warnings, temporary suspension, or permanent account termination.\nOrder cancellation (with refunds handled per our Refund Policy).\nReporting abusive activity where appropriate.",
                    ],
                ],
                'If you have questions about these standards, contact us via the Support button or the Contact page.',
                'Review GGWP-Boost conduct standards for customers, boosters, and staff, including safety, privacy, and fair use.'
            ),
            'privacy-policy' => $this->legalDefinition(
                'privacy-policy',
                'Privacy Policy',
                'privacy-policy',
                'This Privacy Policy explains what information we collect, how we use it, and the choices available to you when you use GGWP-Boost. By using the site or placing an order, you agree to the practices described here.',
                [
                    [
                        'title' => 'Information we collect',
                        'body' => '',
                        'bullets_text' => "Account details such as your name, email address, nickname, and profile preferences.\nOrder information including service selections, rank goals, contact preferences, and payment status metadata.\nSupport and contact submissions you send through our forms, live chat, or other approved communication channels.",
                    ],
                    [
                        'title' => 'How we use information',
                        'body' => '',
                        'bullets_text' => "To process orders, coordinate boosters, and provide customer support.\nTo secure accounts, prevent fraud, investigate abuse, and maintain operational records.\nTo improve service quality, response times, and platform reliability.",
                    ],
                    [
                        'title' => 'Payments and third parties',
                        'body' => '',
                        'bullets_text' => "Payments are handled through approved payment providers and are subject to their processing and compliance rules.\nSupport chat and communication tools may process limited metadata needed to deliver those services.\nWe do not sell your personal information to third parties.",
                    ],
                    [
                        'title' => 'Retention and protection',
                        'body' => '',
                        'bullets_text' => "We retain information only as long as it is reasonably needed for fulfillment, support, security, and legal obligations.\nWe use appropriate access controls and operational safeguards to protect stored information.\nYou are responsible for providing accurate contact information and protecting your own account credentials.",
                    ],
                    [
                        'title' => 'Your choices',
                        'body' => '',
                        'bullets_text' => "You may contact support to correct inaccurate information or request account-related help.\nYou may stop using the service at any time, subject to any active order obligations and the applicable Refund Policy.",
                    ],
                ],
                'For privacy questions, data concerns, or support related to your account, use the Contact page or the Support links shown across the site.',
                'Learn what GGWP-Boost collects, how data is used, and how to contact us about privacy or account data.'
            ),
            'refund-policy' => $this->legalDefinition(
                'refund-policy',
                'Refund Policy',
                'refund-policy',
                'This Refund Policy explains when refunds may be available. By placing an order, you agree to this policy.',
                [
                    [
                        'title' => 'Refund eligibility',
                        'body' => '',
                        'bullets_text' => "Refunds may be considered if we are unable to start or deliver the purchased service as agreed.\nRefunds are assessed on a case-by-case basis based on order status and evidence.",
                    ],
                    [
                        'title' => 'Partial refunds (if work has started)',
                        'body' => '',
                        'bullets_text' => "If work has started, a partial refund may be issued based on the remaining uncompleted portion of the service.\nAny completed portion of the service is generally non-refundable.",
                    ],
                    [
                        'title' => 'Non-refundable situations',
                        'body' => '',
                        'bullets_text' => "Completed services.\nCustomer-provided incorrect information that prevents fulfillment.\nViolations of our Code of Ethics or Terms that lead to cancellation.",
                    ],
                    [
                        'title' => 'Refund request time limits',
                        'body' => '',
                        'bullets_text' => "Refund requests must be submitted within a reasonable time after the issue occurs.\nVery old orders may not be eligible for review.",
                    ],
                    [
                        'title' => 'Processing method',
                        'body' => '',
                        'bullets_text' => "Approved refunds are processed back to the original payment method where possible.\nProcessing times can vary based on payment provider and banking systems.",
                    ],
                ],
                'To request a refund or ask questions, use the Support button or contact us via the Contact page.',
                'Review when GGWP-Boost refunds may apply, how requests are assessed, and how approved refunds are processed.'
            ),
            'terms-and-conditions' => $this->legalDefinition(
                'terms-and-conditions',
                'Terms and Conditions',
                'terms-and-conditions',
                'These Terms and Conditions govern your use of our services and website. By placing an order or using the site, you agree to these terms.',
                [
                    [
                        'title' => 'Service overview',
                        'body' => '',
                        'bullets_text' => "We provide digital gaming-related services (e.g., rank boosting and related add-ons) as described on the order page.\nService scope, timelines, and outcomes may vary due to game matchmaking, player performance, and external factors.",
                    ],
                    [
                        'title' => 'Eligibility',
                        'body' => '',
                        'bullets_text' => "You must be legally able to form a binding contract in your jurisdiction.\nYou are responsible for ensuring the service is permitted for your account and region.",
                    ],
                    [
                        'title' => 'Account responsibility',
                        'body' => '',
                        'bullets_text' => "You are responsible for keeping your login credentials secure.\nIf account sharing is used, you authorize access solely for fulfilling your order.\nYou are responsible for any third-party penalties, bans, or restrictions resulting from platform policies.",
                    ],
                    [
                        'title' => 'Order fulfillment',
                        'body' => '',
                        'bullets_text' => "Orders are processed after required information is provided.\nDelays may occur due to availability, game updates, server issues, or incomplete information.",
                    ],
                    [
                        'title' => 'Payment terms',
                        'body' => '',
                        'bullets_text' => "Prices are shown during checkout and may include fees (where applicable).\nWe reserve the right to refuse service if payment is suspected to be fraudulent.",
                    ],
                    [
                        'title' => 'Prohibited activities',
                        'body' => '',
                        'bullets_text' => "Fraud, scams, chargeback abuse, harassment, hate speech, and attempts to exploit our systems are prohibited.\nViolation may result in cancellation and account suspension or termination.",
                    ],
                    [
                        'title' => 'Limitation of liability',
                        'body' => '',
                        'bullets_text' => "To the maximum extent permitted by law, we are not liable for indirect or consequential damages.\nOur total liability for any claim is limited to the amount paid for the order giving rise to the claim.",
                    ],
                    [
                        'title' => 'Modifications to terms',
                        'body' => '',
                        'bullets_text' => "We may update these terms from time to time.\nContinued use of the site after changes indicates acceptance of the updated terms.",
                    ],
                ],
                'For questions about these terms, reach out through our Contact page or via Support.',
                'Read the terms for using GGWP-Boost services, including orders, payments, account responsibility, and service limits.'
            ),
        ];
    }

    public function page(string $key): array
    {
        $definition = $this->definitions()[$key] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException("Unknown CMS page [{$key}].");
        }

        return $definition;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->definitions());
    }

    public function contentRules(string $key): array
    {
        $rules = [];

        foreach ($this->page($key)['sections'] as $section) {
            $this->collectFieldRules($section['fields'], 'content', $rules);
        }

        return $rules;
    }

    public function normalizeContent(string $key, mixed $content): array
    {
        $normalized = [];
        $content = is_array($content) ? $content : [];

        foreach ($this->page($key)['sections'] as $section) {
            $this->normalizeFields($section['fields'], $content, $normalized);
        }

        return $normalized;
    }

    protected function collectFieldRules(array $fields, string $prefix, array &$rules): void
    {
        foreach ($fields as $field) {
            $path = "{$prefix}.{$field['name']}";

            if ($field['type'] === 'repeater') {
                $rules[$path] = $field['rules'];
                $this->collectRepeaterChildRules($field['fields'], "{$path}.*", $rules);

                continue;
            }

            $rules[$path] = $field['rules'];
        }
    }

    protected function collectRepeaterChildRules(array $fields, string $prefix, array &$rules): void
    {
        foreach ($fields as $field) {
            $path = "{$prefix}.{$field['name']}";

            if ($field['type'] === 'repeater') {
                $rules[$path] = $field['rules'];
                $this->collectRepeaterChildRules($field['fields'], "{$path}.*", $rules);

                continue;
            }

            $rules[$path] = $field['rules'];
        }
    }

    protected function normalizeFields(array $fields, array $source, array &$target, string $prefix = ''): void
    {
        foreach ($fields as $field) {
            $path = ltrim($prefix.'.'.$field['name'], '.');

            if ($field['type'] === 'repeater') {
                $items = data_get($source, $path, []);
                $items = is_array($items) ? $items : [];

                $normalizedItems = collect($items)
                    ->map(function (mixed $item) use ($field): ?array {
                        if (! is_array($item)) {
                            return null;
                        }

                        $normalized = [];

                        foreach ($field['fields'] as $childField) {
                            if ($childField['type'] === 'repeater') {
                                continue;
                            }

                            $value = data_get($item, $childField['name']);
                            $trimmed = is_string($value) ? trim($value) : $value;

                            Arr::set($normalized, $childField['name'], $trimmed);
                        }

                        return collect($normalized)
                            ->flatten()
                            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
                            ->isEmpty()
                                ? null
                                : $normalized;
                    })
                    ->filter()
                    ->values()
                    ->all();

                Arr::set($target, $path, $normalizedItems);

                continue;
            }

            $value = data_get($source, $path);
            $trimmed = is_string($value) ? trim($value) : $value;
            Arr::set($target, $path, $trimmed);
        }
    }

    protected function textField(string $name, string $label, int $max, ?string $help = null): array
    {
        return [
            'type' => 'text',
            'name' => $name,
            'label' => $label,
            'help' => $help,
            'maxlength' => $max,
            'rules' => ['nullable', 'string', "max:{$max}"],
        ];
    }

    protected function textareaField(string $name, string $label, int $rows, int $max, ?string $help = null): array
    {
        return [
            'type' => 'textarea',
            'name' => $name,
            'label' => $label,
            'help' => $help,
            'rows' => $rows,
            'maxlength' => $max,
            'rules' => ['nullable', 'string', "max:{$max}"],
        ];
    }

    protected function urlField(string $name, string $label, ?string $help = null): array
    {
        return [
            'type' => 'url',
            'name' => $name,
            'label' => $label,
            'help' => $help,
            'maxlength' => 2048,
            'rules' => [
                'nullable',
                'string',
                'max:2048',
                new PublicUrl,
            ],
        ];
    }

    protected function repeaterField(string $name, string $label, int $max, array $fields, ?string $help = null): array
    {
        return [
            'type' => 'repeater',
            'name' => $name,
            'label' => $label,
            'help' => $help,
            'rules' => ['nullable', 'array', "max:{$max}"],
            'fields' => $fields,
        ];
    }

    protected function legalDefinition(
        string $key,
        string $label,
        string $routeName,
        string $intro,
        array $sections,
        string $closingNote,
        ?string $seoDescription = null
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'route_name' => $routeName,
            'seo' => [
                'title' => $label,
                'description' => $seoDescription ?? $intro,
                'canonical' => null,
                'robots' => 'index,follow',
                'type' => 'website',
                'include_in_sitemap' => true,
            ],
            'content' => [
                'hero' => [
                    'title' => $label,
                    'intro' => $intro,
                    'closing_note' => $closingNote,
                ],
                'sections' => $sections,
            ],
            'sections' => [
                [
                    'title' => 'Page Intro',
                    'fields' => [
                        $this->textField('hero.title', 'Headline', 160),
                        $this->textareaField('hero.intro', 'Intro Paragraph', 5, 1200),
                        $this->textareaField('hero.closing_note', 'Closing Note', 4, 500),
                    ],
                ],
                [
                    'title' => 'Content Sections',
                    'fields' => [
                        $this->repeaterField('sections', 'Sections', 12, [
                            $this->textField('title', 'Section Title', 160),
                            $this->textareaField('body', 'Section Intro', 3, 600),
                            $this->textareaField('bullets_text', 'Bullets', 4, 1600),
                        ]),
                    ],
                ],
            ],
        ];
    }
}
