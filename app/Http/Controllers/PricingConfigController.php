<?php

namespace App\Http\Controllers;

use App\Support\Pricing\ValorantPricingConfigRepository;
use Illuminate\Http\JsonResponse;

class PricingConfigController extends Controller
{
    public function __invoke(ValorantPricingConfigRepository $pricingConfigRepository): JsonResponse
    {
        return response()
            ->json($pricingConfigRepository->publicPayload())
            ->setPublic()
            ->setMaxAge(60);
    }
}
