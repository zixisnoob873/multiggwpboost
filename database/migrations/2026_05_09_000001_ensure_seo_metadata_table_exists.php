<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seo_metadata')) {
            return;
        }

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
    }

    public function down(): void
    {
        // This repair migration intentionally leaves existing SEO metadata intact.
    }
};
