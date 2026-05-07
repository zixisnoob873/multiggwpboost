<?php

namespace App\Http\Controllers;

use App\Support\Pricing\ValorantPricingConfigRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingConfigController extends Controller
{
    public function __invoke(Request $request, ValorantPricingConfigRepository $pricingConfigRepository): JsonResponse
    {
        $gameSlug = $request->route('game') ?? $request->query('game');

        return response()
            ->json($pricingConfigRepository->publicPayload(is_string($gameSlug) ? $gameSlug : null))
            ->setPublic()
            ->setMaxAge(60);
    }
}
