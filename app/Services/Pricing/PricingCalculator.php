<?php

namespace App\Services\Pricing;

use App\Data\Pricing\PriceCalculationDto;
use App\Data\Pricing\PricingRequest;
use App\Data\Pricing\PricingResult;
use App\Services\Checkout\CheckoutSelectionResolver;
use App\Support\GameCatalog;
use App\Support\Logging\AppEventLogger;
use App\Support\Pricing\CatalogPricingEngine;
use App\Support\Pricing\ValorantPricingEngine;

class PricingCalculator
{
    public function __construct(
        protected ValorantPricingEngine $valorantPricingEngine,
        protected CatalogPricingEngine $catalogPricingEngine,
        protected GameCatalog $gameCatalog,
        protected AppEventLogger $eventLogger,
        protected CheckoutSelectionResolver $selectionResolver,
    ) {}

    public function calculate(array|PriceCalculationDto|PricingRequest $input, array $options = []): PricingResult
    {
        $request = $this->request($input);
        $selection = $this->selectionResolver->canonicalizePayload([
            ...$request->rawPayload,
            ...$request->toArray(),
            'gameSlug' => $options['gameSlug'] ?? $request->gameSlug,
            'serviceSlug' => $options['serviceSlug'] ?? $request->serviceSlug,
        ]);
        $payload = $selection['payload'];
        $request = PricingRequest::fromArray($payload);

        if (($selection['errors'] ?? []) !== []) {
            return PricingResult::fromPayload($request, $this->selectionResolver->failurePayload($selection));
        }

        $gameSlug = $this->gameCatalog->resolveSlugFromPayload([
            ...$payload,
            'gameSlug' => $options['gameSlug'] ?? $payload['gameSlug'] ?? null,
        ]);
        $options = [
            ...$options,
            'gameSlug' => $gameSlug,
        ];
        $enginePayload = $this->shouldUseValorantEngine($gameSlug, $payload)
            ? $this->valorantPricingEngine->calculate($payload, $options)
            : $this->catalogPricingEngine->calculate($payload, $options);
        $result = PricingResult::fromPayload($request, $enginePayload);

        $this->logClientTotalMismatches($request, $result);

        return $result;
    }

    public function calculateOrFail(array|PriceCalculationDto|PricingRequest $input, array $options = []): PricingResult
    {
        return $this->calculate($input, $options)->throwIfInvalid();
    }

    public function calculatePayload(array|PriceCalculationDto|PricingRequest $input, array $options = []): array
    {
        return $this->calculate($input, $options)->toArray();
    }

    public function calculatePayloadOrFail(array|PriceCalculationDto|PricingRequest $input, array $options = []): array
    {
        return $this->calculateOrFail($input, $options)->toArray();
    }

    public function gameSlugFor(array|PriceCalculationDto|PricingRequest $input, array $options = []): string
    {
        $payload = $this->request($input)->toArray();

        return $this->gameCatalog->resolveSlugFromPayload([
            ...$payload,
            'gameSlug' => $options['gameSlug'] ?? $payload['gameSlug'] ?? null,
        ]);
    }

    protected function request(array|PriceCalculationDto|PricingRequest $input): PricingRequest
    {
        if ($input instanceof PricingRequest) {
            return $input;
        }

        if ($input instanceof PriceCalculationDto) {
            return PricingRequest::fromDto($input);
        }

        return PricingRequest::fromArray($input);
    }

    protected function shouldUseValorantEngine(string $gameSlug, array $payload): bool
    {
        if ($gameSlug !== GameCatalog::DEFAULT_GAME_SLUG) {
            return false;
        }

        $service = $payload['serviceType'] ?? $payload['orderType'] ?? null;

        if (! is_string($service) || trim($service) === '') {
            return true;
        }

        return $this->valorantPricingServiceName($service) !== null;
    }

    protected function valorantPricingServiceName(string $service): ?string
    {
        $needle = str(trim($service))->lower()->replace(['-', '_'], ' ')->squish()->value();
        $aliases = [
            'rank boost' => 'Rank Boosting',
            'rank boosting' => 'Rank Boosting',
            'placements' => 'Placement Matches',
            'placement matches' => 'Placement Matches',
            'placement games' => 'Placement Matches',
            'ranked wins' => 'Ranked Wins',
            'radiant boost' => 'Radiant Boost',
        ];

        return $aliases[$needle]
            ?? collect(array_keys((array) config('pricing.services', [])))
                ->first(fn (string $supported): bool => strcasecmp($supported, trim($service)) === 0);
    }

    protected function logClientTotalMismatches(PricingRequest $request, PricingResult $result): void
    {
        if ($request->clientSubmittedTotals === [] || $result->hasValidationErrors()) {
            return;
        }

        $mismatches = [];

        foreach ($request->clientSubmittedTotals as $field => $amount) {
            $submittedCents = max(0, (int) round(((float) $amount) * 100));

            if ($submittedCents !== $result->finalPriceCents) {
                $mismatches[$field] = [
                    'submitted' => round((float) $amount, 2),
                    'submitted_cents' => $submittedCents,
                ];
            }
        }

        if ($mismatches === []) {
            return;
        }

        $this->eventLogger->payment('pricing.client_total_mismatch', [
            'game_slug' => $result->payload['gameSlug'] ?? $request->gameSlug,
            'service_slug' => $result->payload['serviceSlug'] ?? $request->serviceSlug,
            'service_type' => $result->payload['serviceType'] ?? $request->serviceType ?? $request->orderType,
            'calculated_total' => $result->finalPrice,
            'calculated_cents' => $result->finalPriceCents,
            'submitted_totals' => $mismatches,
            'pricing_config' => $result->payload['pricingConfig'] ?? [],
        ], 'warning');
    }
}
