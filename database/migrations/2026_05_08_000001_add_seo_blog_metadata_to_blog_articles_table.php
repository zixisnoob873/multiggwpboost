<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blog_articles')) {
            return;
        }

        Schema::table('blog_articles', function (Blueprint $table): void {
            if (! Schema::hasColumn('blog_articles', 'category_name')) {
                $table->string('category_name')->nullable()->after('service_id');
            }

            if (! Schema::hasColumn('blog_articles', 'category_slug')) {
                $table->string('category_slug')->nullable()->after('category_name')->index();
            }

            if (! Schema::hasColumn('blog_articles', 'tags')) {
                $table->json('tags')->nullable()->after('category_slug');
            }

            if (! Schema::hasColumn('blog_articles', 'author_name')) {
                $table->string('author_name')->nullable()->after('tags');
            }

            if (! Schema::hasColumn('blog_articles', 'featured_image_url')) {
                $table->string('featured_image_url', 2048)->nullable()->after('author_name');
            }

            if (! Schema::hasColumn('blog_articles', 'featured_image_alt')) {
                $table->string('featured_image_alt')->nullable()->after('featured_image_url');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('blog_articles')) {
            return;
        }

        Schema::table('blog_articles', function (Blueprint $table): void {
            foreach ([
                'featured_image_alt',
                'featured_image_url',
                'author_name',
                'tags',
                'category_slug',
                'category_name',
            ] as $column) {
                if (Schema::hasColumn('blog_articles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
