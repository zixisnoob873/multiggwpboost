<?php

namespace App\Http\Requests;

use App\Data\Pricing\PricingRequest;
use Illuminate\Foundation\Http\FormRequest;

class StorePriceCalculationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'selectedAddons' => $this->normalizeArray('selectedAddons'),
            'addons' => $this->normalizeArray('addons'),
            'specificAgents' => $this->normalizeArray('specificAgents'),
            'specific_agents' => $this->normalizeArray('specific_agents'),
            'oneTrickAgent' => $this->normalizeArray('oneTrickAgent'),
            'one_trick_agent' => $this->normalizeArray('one_trick_agent'),
        ]);
    }

    public function rules(): array
    {
        return [
            'gameSlug' => ['nullable', 'string', 'max:80'],
            'game_slug' => ['nullable', 'string', 'max:80'],
            'game' => ['nullable', 'string', 'max:120'],
            'serviceSlug' => ['nullable', 'string', 'max:80'],
            'service_slug' => ['nullable', 'string', 'max:80'],
            'serviceType' => ['nullable', 'string', 'max:50'],
            'orderType' => ['nullable', 'string', 'max:50'],
            'currentRank' => ['nullable', 'string', 'max:50'],
            'current_rank' => ['nullable', 'string', 'max:50'],
            'currentDivision' => ['nullable', 'string', 'max:50'],
            'current_division' => ['nullable', 'string', 'max:50'],
            'desiredDivision' => ['nullable', 'string', 'max:50'],
            'desired_division' => ['nullable', 'string', 'max:50'],
            'desiredRank' => ['nullable', 'string', 'max:50'],
            'desired_rank' => ['nullable', 'string', 'max:50'],
            'targetDivision' => ['nullable', 'string', 'max:50'],
            'target_division' => ['nullable', 'string', 'max:50'],
            'targetRank' => ['nullable', 'string', 'max:50'],
            'target_rank' => ['nullable', 'string', 'max:50'],
            'currentRR' => ['nullable', 'integer', 'min:0', 'max:100'],
            'current_rr' => ['nullable', 'integer', 'min:0', 'max:100'],
            'avgRRPerWin' => ['nullable', 'string', 'max:20'],
            'averageRR' => ['nullable', 'string', 'max:20'],
            'average_rr' => ['nullable', 'string', 'max:20'],
            'region' => ['nullable', 'string', 'max:20'],
            'platform' => ['nullable', 'string', 'max:20'],
            'boostMode' => ['nullable', 'string', 'max:40'],
            'queueType' => ['nullable', 'string', 'max:40'],
            'queue_type' => ['nullable', 'string', 'max:40'],
            'accountType' => ['nullable', 'string', 'max:40'],
            'account_type' => ['nullable', 'string', 'max:40'],
            'playType' => ['nullable', 'string', 'max:40'],
            'currentLevel' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'current_level' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'desiredLevel' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'desired_level' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'selectedOptions' => ['nullable', 'array', 'max:40'],
            'selected_options' => ['nullable', 'array', 'max:40'],
            'duoQueue' => ['nullable', 'boolean'],
            'duo_queue' => ['nullable', 'boolean'],
            'streamGames' => ['nullable', 'boolean'],
            'stream_games' => ['nullable', 'boolean'],
            'expressDelivery' => ['nullable', 'boolean'],
            'express_delivery' => ['nullable', 'boolean'],
            'clientTotal' => ['nullable', 'numeric', 'min:0'],
            'client_total' => ['nullable', 'numeric', 'min:0'],
            'finalPrice' => ['nullable', 'numeric', 'min:0'],
            'pricing' => ['nullable', 'array', 'max:20'],
            'pricing.total' => ['nullable', 'numeric', 'min:0'],
            'selectedAddons' => ['nullable', 'array', 'max:20'],
            'selectedAddons.*' => ['string', 'max:80'],
            'addons' => ['nullable', 'array', 'max:20'],
            'addons.*' => ['string', 'max:80'],
            'specificAgents' => ['nullable', 'array', 'max:10'],
            'specificAgents.*' => ['string', 'max:64'],
            'specific_agents' => ['nullable', 'array', 'max:10'],
            'specific_agents.*' => ['string', 'max:64'],
            'oneTrickAgent' => ['nullable', 'array', 'max:5'],
            'oneTrickAgent.*' => ['string', 'max:64'],
            'one_trick_agent' => ['nullable', 'array', 'max:5'],
            'one_trick_agent.*' => ['string', 'max:64'],
            'numberOfWins' => ['nullable', 'integer', 'min:1', 'max:25'],
            'number_of_wins' => ['nullable', 'integer', 'min:1', 'max:25'],
            'wins' => ['nullable', 'integer', 'min:1', 'max:25'],
            'numberOfPlacementGames' => ['nullable', 'integer', 'min:1', 'max:10'],
            'number_of_placement_games' => ['nullable', 'integer', 'min:1', 'max:10'],
            'placementGames' => ['nullable', 'integer', 'min:1', 'max:10'],
            'games' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function validatedPayload(): array
    {
        return $this->safe()->only([
            'serviceType',
            'gameSlug',
            'game_slug',
            'game',
            'serviceSlug',
            'service_slug',
            'orderType',
            'currentRank',
            'current_rank',
            'currentDivision',
            'current_division',
            'desiredRank',
            'desired_rank',
            'desiredDivision',
            'desired_division',
            'targetDivision',
            'target_division',
            'targetRank',
            'target_rank',
            'currentRR',
            'current_rr',
            'avgRRPerWin',
            'averageRR',
            'average_rr',
            'region',
            'platform',
            'boostMode',
            'queueType',
            'queue_type',
            'accountType',
            'account_type',
            'playType',
            'currentLevel',
            'current_level',
            'desiredLevel',
            'desired_level',
            'selectedOptions',
            'selected_options',
            'duoQueue',
            'duo_queue',
            'streamGames',
            'stream_games',
            'expressDelivery',
            'express_delivery',
            'clientTotal',
            'client_total',
            'finalPrice',
            'pricing',
            'selectedAddons',
            'addons',
            'specificAgents',
            'specific_agents',
            'oneTrickAgent',
            'one_trick_agent',
            'numberOfWins',
            'number_of_wins',
            'wins',
            'numberOfPlacementGames',
            'number_of_placement_games',
            'placementGames',
            'games',
        ]);
    }

    public function toPricingRequest(): PricingRequest
    {
        return PricingRequest::fromArray($this->validatedPayload());
    }

    protected function normalizeArray(string $key): array
    {
        $value = $this->input($key, []);

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decoded : [$value];
        }

        return collect(is_array($value) ? $value : [])
            ->map(fn (mixed $item): string => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }
}
