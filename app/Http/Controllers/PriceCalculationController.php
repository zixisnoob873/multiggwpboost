<?php

namespace App\Http\Controllers;

use App\Data\Pricing\PriceCalculationDto;
use App\Http\Requests\StorePriceCalculationRequest;
use App\Support\Pricing\PricingEngineManager;
use Illuminate\Http\JsonResponse;

class PriceCalculationController extends Controller
{
    public function __invoke(StorePriceCalculationRequest $request, PricingEngineManager $pricingEngine): JsonResponse
    {
        $result = $pricingEngine->calculate(
            PriceCalculationDto::fromArray($request->validatedPayload())
        );
        $hasValidationErrors = $result['validationErrors'] !== [];

        if ($hasValidationErrors) {
            $result['success'] = false;
            $result['message'] = 'Please review the selected boost options.';
            $result['error_code'] = 'validation_failed';
        }

        return response()->json(
            $result,
            $hasValidationErrors ? 422 : 200
        );
    }
}
