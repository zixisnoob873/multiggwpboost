<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureGameCategoriesTable();
        $this->ensureGamesTable();
        $this->ensureGameServicesTable();
        $this->ensureGameRanksTable();
        $this->ensureGameAddonsTable();
        $this->ensureGameServiceAddonsTable();
        $this->ensureServicePricingRulesTable();
    }

    public function down(): void
    {
        // Forward-only schema repair for databases that ran an older catalog migration.
    }

    private function ensureGameCategoriesTable(): void
    {
        if (Schema::hasTable('game_categories')) {
            return;
        }

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
    }

    private function ensureGamesTable(): void
    {
        if (! Schema::hasTable('games')) {
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

            return;
        }

        Schema::table('games', function (Blueprint $table): void {
            if (! Schema::hasColumn('games', 'game_category_id')) {
                $table->foreignId('game_category_id')->nullable()->constrained('game_categories')->nullOnDelete();
            }

            if (! Schema::hasColumn('games', 'description')) {
                $table->text('description')->nullable();
            }

            if (! Schema::hasColumn('games', 'assets')) {
                $table->json('assets')->nullable();
            }

            if (! Schema::hasColumn('games', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });
    }

    private function ensureGameServicesTable(): void
    {
        if (! Schema::hasTable('game_services')) {
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

            return;
        }

        Schema::table('game_services', function (Blueprint $table): void {
            if (! Schema::hasColumn('game_services', 'description')) {
                $table->text('description')->nullable();
            }

            if (! Schema::hasColumn('game_services', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });
    }

    private function ensureGameRanksTable(): void
    {
        if (Schema::hasTable('game_ranks')) {
            return;
        }

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
    }

    private function ensureGameAddonsTable(): void
    {
        if (! Schema::hasTable('game_addons')) {
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

            return;
        }

        Schema::table('game_addons', function (Blueprint $table): void {
            if (! Schema::hasColumn('game_addons', 'pricing_type')) {
                $table->string('pricing_type')->default('free')->index();
            }

            if (! Schema::hasColumn('game_addons', 'pricing_value')) {
                $table->decimal('pricing_value', 12, 4)->nullable();
            }
        });
    }

    private function ensureGameServiceAddonsTable(): void
    {
        if (Schema::hasTable('game_service_addons')) {
            return;
        }

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
    }

    private function ensureServicePricingRulesTable(): void
    {
        if (Schema::hasTable('service_pricing_rules')) {
            return;
        }

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
    }
};
