<?php

namespace App\Http\Controllers;

use App\Http\Resources\FaqResource;
use App\Models\Faq;
use App\Queries\Marketplace\GameRepository;
use App\Support\GameCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class FaqController extends Controller
{
    /**
     * Return FAQs ordered for the frontend accordion.
     */
    public function index(Request $request, GameCatalog $gameCatalog, GameRepository $games): JsonResponse
    {
        $query = Faq::query()->orderBy('order');
        $gameSlug = $gameCatalog->normalizeSlug($request->query('game'));

        if (Schema::hasColumn('faqs', 'game_id')) {
            $gameId = $games->findBySlug($gameSlug)?->id;

            if ($gameId) {
                $query->where(function ($builder) use ($gameId): void {
                    $builder->whereNull('game_id')->orWhere('game_id', $gameId);
                });
            }
        }

        $faqs = $query->get(['question', 'answer']);

        return response()->json([
            'faqs' => FaqResource::collection($faqs)->resolve(),
        ]);
    }
}
