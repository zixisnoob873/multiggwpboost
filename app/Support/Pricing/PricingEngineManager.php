<?php

namespace App\Support\Pricing;

use App\Data\Pricing\PriceCalculationDto;
use App\Support\GameCatalog;
use Illuminate\Validation\ValidationException;

class PricingEngineManager
{
    public function __construct(
        protected ValorantPricingEngine $rankPricingEngine,
        protected GameCatalog $gameCatalog,
    ) {}

    public function calculate(array|PriceCalculationDto $input, array $options = []): array
    {
        $payload = $this->payload($input);
        $gameSlug = $this->gameCatalog->resolveSlugFromPayload([
            ...$payload,
            'gameSlug' => $options['gameSlug'] ?? $payload['gameSlug'] ?? null,
        ]);

        return $this->rankPricingEngine->calculate($payload, [
            ...$options,
            'gameSlug' => $gameSlug,
        ]);
    }

    public function calculateOrFail(array|PriceCalculationDto $input, array $options = []): array
    {
        $result = $this->calculate($input, $options);

        if (($result['validationErrors'] ?? []) !== []) {
            throw ValidationException::withMessages($result['validationErrors']);
        }

        return $result;
    }

    public function gameSlugFor(array|PriceCalculationDto $input, array $options = []): string
    {
        $payload = $this->payload($input);

        return $this->gameCatalog->resolveSlugFromPayload([
            ...$payload,
            'gameSlug' => $options['gameSlug'] ?? $payload['gameSlug'] ?? null,
        ]);
    }

    protected function payload(array|PriceCalculationDto $input): array
    {
        return $input instanceof PriceCalculationDto ? $input->toArray() : $input;
    }
}
