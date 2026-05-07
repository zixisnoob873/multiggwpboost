<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\GameService;
use App\Models\Review;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $valorant = Game::query()->where('slug', 'valorant')->first();
        $services = $valorant instanceof Game
            ? GameService::query()->where('game_id', $valorant->id)->get()->keyBy('name')
            : collect();

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

        foreach ($entries as $entry) {
            $service = $services->get($entry['service']);

            Review::query()->updateOrCreate(
                ['sort_order' => $entry['sort_order']],
                [
                    ...$entry,
                    'game_id' => $valorant?->id,
                    'service_id' => $service instanceof GameService ? $service->id : null,
                ],
            );
        }
    }
}
