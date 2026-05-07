<?php

namespace Database\Seeders;

use App\Models\Review;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            [
                'author_name' => 'Mason T.',
                'service' => 'Rank Boosting',
                'quote' => 'Fast communication, smooth progress updates, and a clean finish from start to finish.',
                'sort_order' => 1,
            ],
            [
                'author_name' => 'Alina R.',
                'service' => 'Placement Matches',
                'quote' => 'The order flow was simple, and the status tracking made every placement match easy to follow.',
                'sort_order' => 2,
            ],
            [
                'author_name' => 'Jordan K.',
                'service' => 'Radiant Boost',
                'quote' => 'Solid experience overall with clear support, quick updates, and careful handling for a high-rank push.',
                'sort_order' => 3,
            ],
            [
                'author_name' => 'Dylan M.',
                'service' => 'Rank Boosting',
                'quote' => 'Clean delivery, fast queue handling, and the order felt premium from checkout to finish.',
                'sort_order' => 4,
            ],
        ];

        Review::query()->whereNotIn('sort_order', array_column($entries, 'sort_order'))->delete();

        foreach ($entries as $entry) {
            Review::query()->updateOrCreate(
                ['sort_order' => $entry['sort_order']],
                $entry
            );
        }
    }
}
