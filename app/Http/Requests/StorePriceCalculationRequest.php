<?php

namespace App\Http\Requests;

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
            'serviceType' => ['nullable', 'string', 'max:50'],
            'orderType' => ['nullable', 'string', 'max:50'],
            'currentRank' => ['nullable', 'string', 'max:50'],
            'current_rank' => ['nullable', 'string', 'max:50'],
            'currentDivision' => ['nullable', 'string', 'max:50'],
            'current_division' => ['nullable', 'string', 'max:50'],
            'desiredDivision' => ['nullable', 'string', 'max:50'],
            'desired_division' => ['nullable', 'string', 'max:50'],
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
            'accountType' => ['nullable', 'string', 'max:40'],
            'account_type' => ['nullable', 'string', 'max:40'],
            'playType' => ['nullable', 'string', 'max:40'],
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
            'orderType',
            'currentRank',
            'current_rank',
            'currentDivision',
            'current_division',
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
            'accountType',
            'account_type',
            'playType',
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
