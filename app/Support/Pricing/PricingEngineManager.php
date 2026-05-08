<?php

namespace App\Support\Pricing;

use App\Data\Pricing\PriceCalculationDto;
use App\Data\Pricing\PricingRequest;
use App\Services\Pricing\PricingCalculator;
use Illuminate\Validation\ValidationException;

class PricingEngineManager
{
    public function __construct(
        protected PricingCalculator $pricingCalculator,
    ) {}

    public function calculate(array|PriceCalculationDto|PricingRequest $input, array $options = []): array
    {
        return $this->pricingCalculator->calculatePayload($input, $options);
    }

    public function calculateOrFail(array|PriceCalculationDto|PricingRequest $input, array $options = []): array
    {
        $result = $this->calculate($input, $options);

        if (($result['validationErrors'] ?? []) !== []) {
            throw ValidationException::withMessages($result['validationErrors']);
        }

        return $result;
    }

    public function gameSlugFor(array|PriceCalculationDto|PricingRequest $input, array $options = []): string
    {
        return $this->pricingCalculator->gameSlugFor($input, $options);
    }
}
