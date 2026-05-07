<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $seededSlugs = [
        'is-valorant-boosting-safe',
        'how-long-does-valorant-rank-boosting-take',
        'duo-vs-self-play-valorant-boosting',
        'best-valorant-boosting-services',
        'how-to-rank-up-in-valorant-fast',
        'valorant-placement-matches-explained',
        'what-affects-valorant-boosting-price',
        'valorant-radiant-boost-explained',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('blog_articles')) {
            return;
        }

        $articles = DB::table('blog_articles')
            ->whereIn('slug', $this->seededSlugs)
            ->get(['id', 'body']);

        foreach ($articles as $article) {
            DB::table('blog_articles')
                ->where('id', $article->id)
                ->update([
                    'body' => $this->normalizeBody((string) $article->body),
                    'cta_label' => 'Explore Services',
                    'cta_url' => '/#servicesTab',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        //
    }

    private function normalizeBody(string $body): string
    {
        return str_replace(
            [
                '[checkout](/checkout)',
                '[Checkout](/checkout)',
                '](/checkout)',
            ],
            [
                '[services](/#servicesTab)',
                '[services](/#servicesTab)',
                '](/#servicesTab)',
            ],
            $body
        );
    }
};
