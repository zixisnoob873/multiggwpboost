<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('published')->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('games', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_category_id')->nullable()->constrained('game_categories')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('published')->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->json('assets')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['game_category_id', 'status', 'sort_order']);
        });

        Schema::create('game_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->string('kind');
            $table->text('description')->nullable();
            $table->string('status')->default('published')->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->json('config')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'slug']);
            $table->index(['game_id', 'status', 'sort_order']);
            $table->index(['kind', 'status']);
        });

        Schema::create('game_ranks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->string('slug');
            $table->string('label');
            $table->string('division')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('icon_url', 2048)->nullable();
            $table->string('icon_path', 2048)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'slug']);
            $table->index(['game_id', 'sort_order']);
        });

        Schema::create('game_addons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->string('slug');
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('icon', 2048)->nullable();
            $table->string('status')->default('published')->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('pricing_type')->default('free')->index();
            $table->decimal('pricing_value', 12, 4)->nullable();
            $table->json('pricing_rule')->nullable();
            $table->json('availability_rule')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'slug']);
            $table->index(['game_id', 'status', 'sort_order']);
        });

        Schema::create('game_service_addons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_service_id')->constrained('game_services')->cascadeOnDelete();
            $table->foreignId('game_addon_id')->constrained('game_addons')->cascadeOnDelete();
            $table->string('status')->default('published')->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->json('availability_rule')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['game_service_id', 'game_addon_id']);
            $table->index(['game_addon_id', 'status']);
        });

        Schema::create('service_pricing_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('game_services')->cascadeOnDelete();
            $table->foreignId('addon_id')->nullable()->constrained('game_addons')->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->string('scope')->default('base')->index();
            $table->string('calculator_key')->nullable()->index();
            $table->string('pricing_type')->default('fixed')->index();
            $table->decimal('amount', 12, 4)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('min_quantity')->nullable();
            $table->unsignedInteger('max_quantity')->nullable();
            $table->string('status')->default('published')->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->json('conditions')->nullable();
            $table->json('tiers')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'slug']);
            $table->index(['game_id', 'service_id', 'status', 'sort_order']);
            $table->index(['game_id', 'addon_id', 'status']);
        });

        Schema::create('seo_metadata', function (Blueprint $table): void {
            $table->id();
            $table->morphs('seoable');
            $table->string('context')->default('default');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('canonical_url', 2048)->nullable();
            $table->string('robots')->nullable();
            $table->string('schema_type')->nullable();
            $table->string('open_graph_image', 2048)->nullable();
            $table->boolean('include_in_sitemap')->default(true);
            $table->string('changefreq')->nullable();
            $table->decimal('priority', 2, 1)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id', 'context']);
            $table->index(['include_in_sitemap', 'robots']);
        });

        $now = now();

        DB::table('game_categories')->updateOrInsert(
            ['slug' => 'tactical-shooter'],
            [
                'name' => 'Tactical Shooter',
                'description' => 'Round-based competitive shooters with ranked ladders and team coordination.',
                'status' => 'published',
                'sort_order' => 1,
                'metadata' => json_encode([], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $tacticalShooterId = DB::table('game_categories')->where('slug', 'tactical-shooter')->value('id');

        DB::table('games')->updateOrInsert(
            ['slug' => 'valorant'],
            [
                'game_category_id' => $tacticalShooterId,
                'name' => 'Valorant',
                'short_name' => 'VALORANT',
                'description' => 'Competitive VALORANT boosting, placements, ranked wins, and Radiant push services.',
                'status' => 'published',
                'sort_order' => 1,
                'assets' => json_encode([], JSON_THROW_ON_ERROR),
                'metadata' => json_encode([
                    'default_current_rank' => 'Gold III',
                    'default_desired_rank' => 'Platinum III',
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $this->addNullableGameColumns();
        $this->backfillValorantGame();
    }

    public function down(): void
    {
        $this->dropNullableGameColumns();

        Schema::dropIfExists('seo_metadata');
        Schema::dropIfExists('service_pricing_rules');
        Schema::dropIfExists('game_service_addons');
        Schema::dropIfExists('game_addons');
        Schema::dropIfExists('game_ranks');
        Schema::dropIfExists('game_services');
        Schema::dropIfExists('games');
        Schema::dropIfExists('game_categories');
    }

    protected function addNullableGameColumns(): void
    {
        $this->addGameAndServiceColumns('orders');
        $this->addGameAndServiceColumns('pending_checkouts');
        $this->addGameAndServiceColumns('faqs');
        $this->addGameAndServiceColumns('testimonials');
        $this->addGameAndServiceColumns('pages');
        $this->addGameAndServiceColumns('blog_articles');
        $this->addGameAndServiceColumns('promo_codes');

        $this->addGameColumn('promo_code_addons');
        $this->addGameColumn('addon_settings');
        $this->addGameColumn('pricing_settings');
        $this->addGameColumn('pricing_setting_revisions');
    }

    protected function addGameAndServiceColumns(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (! Schema::hasColumn($tableName, 'game_id')) {
                $table->foreignId('game_id')->nullable()->constrained('games')->nullOnDelete();
            }

            if (! Schema::hasColumn($tableName, 'service_id')) {
                $table->foreignId('service_id')->nullable()->constrained('game_services')->nullOnDelete();
            }
        });
    }

    protected function addGameColumn(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (! Schema::hasColumn($tableName, 'game_id')) {
                $table->foreignId('game_id')->nullable()->constrained('games')->nullOnDelete();
            }
        });
    }

    protected function backfillValorantGame(): void
    {
        $gameId = DB::table('games')->where('slug', 'valorant')->value('id');

        if (! $gameId) {
            return;
        }

        foreach ([
            'orders',
            'pending_checkouts',
            'faqs',
            'testimonials',
            'blog_articles',
            'promo_codes',
            'promo_code_addons',
            'addon_settings',
        ] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'game_id')) {
                DB::table($tableName)->whereNull('game_id')->update(['game_id' => $gameId]);
            }
        }

        if (Schema::hasTable('pricing_settings') && Schema::hasColumn('pricing_settings', 'game_id')) {
            DB::table('pricing_settings')
                ->where('key', 'valorant')
                ->whereNull('game_id')
                ->update(['game_id' => $gameId]);
        }

        if (Schema::hasTable('pricing_setting_revisions') && Schema::hasColumn('pricing_setting_revisions', 'game_id')) {
            DB::table('pricing_setting_revisions')
                ->where('key', 'valorant')
                ->whereNull('game_id')
                ->update(['game_id' => $gameId]);
        }

        if (Schema::hasTable('pages') && Schema::hasColumn('pages', 'game_id')) {
            DB::table('pages')
                ->whereIn('key', ['home', 'faq', 'contact', 'reviews', 'become-booster', 'blog-index'])
                ->whereNull('game_id')
                ->update(['game_id' => $gameId]);
        }
    }

    protected function dropNullableGameColumns(): void
    {
        foreach ([
            'orders',
            'pending_checkouts',
            'faqs',
            'testimonials',
            'pages',
            'blog_articles',
            'promo_codes',
        ] as $tableName) {
            $this->dropGameAndServiceColumns($tableName);
        }

        foreach ([
            'promo_code_addons',
            'addon_settings',
            'pricing_settings',
            'pricing_setting_revisions',
        ] as $tableName) {
            $this->dropGameColumn($tableName);
        }
    }

    protected function dropGameAndServiceColumns(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (Schema::hasColumn($tableName, 'service_id')) {
                $table->dropConstrainedForeignId('service_id');
            }

            if (Schema::hasColumn($tableName, 'game_id')) {
                $table->dropConstrainedForeignId('game_id');
            }
        });
    }

    protected function dropGameColumn(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (Schema::hasColumn($tableName, 'game_id')) {
                $table->dropConstrainedForeignId('game_id');
            }
        });
    }
};
