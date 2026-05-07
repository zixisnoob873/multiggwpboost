<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->syncPages($this->pageDescriptions());
        $this->syncBlogArticles($this->blogArticleDescriptions());
    }

    public function down(): void
    {
        // Intentionally keep the shorter SEO descriptions.
    }

    protected function syncPages(array $descriptions): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        foreach ($descriptions as $key => $description) {
            $page = DB::table('pages')->where('key', $key)->first();

            if (! $page || ! $this->shouldReplace($page->meta_description ?? null)) {
                continue;
            }

            DB::table('pages')
                ->where('key', $key)
                ->update([
                    'meta_description' => $description,
                    'updated_at' => now(),
                ]);
        }
    }

    protected function syncBlogArticles(array $descriptions): void
    {
        if (! Schema::hasTable('blog_articles')) {
            return;
        }

        foreach ($descriptions as $slug => $description) {
            $article = DB::table('blog_articles')->where('slug', $slug)->first();

            if (! $article || ! $this->shouldReplace($article->meta_description ?? null)) {
                continue;
            }

            DB::table('blog_articles')
                ->where('slug', $slug)
                ->update([
                    'meta_description' => $description,
                    'updated_at' => now(),
                ]);
        }
    }

    protected function shouldReplace(mixed $description): bool
    {
        $description = trim((string) $description);

        return $description !== '' && Str::length($description) > 130;
    }

    protected function pageDescriptions(): array
    {
        return [
            'home' => 'Get fast, safe VALORANT rank boosting with Solo and Duo / Self-Play options, live tracking, and clear pricing.',
            'blog-index' => 'Read practical VALORANT rank boosting guides on Duo / Self-Play, pricing, safety, placements, and faster improvement paths.',
            'faq' => 'Answers about VALORANT rank boosting, Duo / Self-Play, pricing, speed, account handling, refunds, and support.',
            'contact' => 'Contact GGWP-Boost support for VALORANT boost orders, pricing, billing, custom requests, or Duo / Self-Play guidance.',
            'reviews' => 'Read customer reviews and proof from completed VALORANT boost orders, rank boosting, delivery, and support.',
            'code-of-ethics' => 'Review GGWP-Boost conduct standards for customers, boosters, and staff, including safety, privacy, and fair use.',
            'privacy-policy' => 'Learn what GGWP-Boost collects, how data is used, and how to contact us about privacy or account data.',
            'refund-policy' => 'Review when GGWP-Boost refunds may apply, how requests are assessed, and how approved refunds are processed.',
            'terms-and-conditions' => 'Read the terms for using GGWP-Boost services, including orders, payments, account responsibility, and service limits.',
        ];
    }

    protected function blogArticleDescriptions(): array
    {
        return [
            'is-valorant-boosting-safe' => 'Learn the real risks of VALORANT boosting, how account-shared and Duo / Self-Play modes differ, and what to check first.',
            'how-long-does-valorant-rank-boosting-take' => 'See what affects VALORANT boosting timelines, from RR gains and rank gap to service type, queue mode, and add-on limits.',
            'duo-vs-self-play-valorant-boosting' => 'Learn how Duo / Self-Play VALORANT boosting works and how it compares with account-shared boosting.',
            'best-valorant-boosting-services' => 'Compare VALORANT boosting services by process quality, support, boost mode, pricing, and realistic delivery claims.',
            'how-to-rank-up-in-valorant-fast' => 'Improve your VALORANT climb with queue discipline, smarter review habits, tighter role selection, and fewer wasted sessions.',
            'valorant-placement-matches-explained' => 'Understand how VALORANT placement matches work, what previous MMR affects, and how to approach placements consistently.',
            'what-affects-valorant-boosting-price' => 'Understand what changes VALORANT boosting prices, including service type, RR, rank gap, mode, urgency, and restrictions.',
            'valorant-radiant-boost-explained' => 'Understand why Radiant boosting differs from standard rank climbs, including readiness, pricing, and delivery expectations.',
        ];
    }
};
