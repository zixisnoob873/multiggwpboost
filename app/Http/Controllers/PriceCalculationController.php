<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePriceCalculationRequest;
use App\Services\Pricing\PricingCalculator;
use Illuminate\Http\JsonResponse;

class PriceCalculationController extends Controller
{
    public function __invoke(StorePriceCalculationRequest $request, PricingCalculator $pricingCalculator): JsonResponse
    {
        $result = $pricingCalculator->calculate($request->toPricingRequest())->toArray();
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
