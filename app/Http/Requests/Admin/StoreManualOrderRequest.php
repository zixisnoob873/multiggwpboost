<?php

namespace App\Http\Requests\Admin;

use App\Http\Controllers\Admin\AdminController;
use App\Services\Orders\OrderPricingPayloadService;
use App\Support\AdminManualOrderData;
use App\Support\BoostingCatalog;
use App\Support\GameCatalog;
use App\Support\Pricing\PricingEngineManager;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreManualOrderRequest extends AdminRequest
{
    protected array $rawAgentSelections = [
        'specificAgents' => [],
        'oneTrickAgent' => [],
    ];

    public function authorize(): bool
    {
        return $this->authorizeAdminModule('operations');
    }

    protected function prepareForValidation(): void
    {
        $addons = $this->input('addons', '');

        if (is_string($addons)) {
            $addons = collect(explode(',', $addons))
                ->map(fn ($value) => trim($value))
                ->filter()
                ->values()
                ->all();
        }

        $specificAgents = $this->input('specific_agents', []);

        if (is_string($specificAgents)) {
            $specificAgents = collect(explode(',', $specificAgents))
                ->map(fn ($value) => trim($value))
                ->filter()
                ->values()
                ->all();
        }

        $oneTrickAgent = $this->input('one_trick_agent', []);

        if (is_string($oneTrickAgent)) {
            $oneTrickAgent = collect(explode(',', $oneTrickAgent))
                ->map(fn ($value) => trim($value))
                ->filter()
                ->values()
                ->all();
        }

        $this->rawAgentSelections = [
            'specificAgents' => $specificAgents,
            'oneTrickAgent' => $oneTrickAgent,
        ];

        $this->merge([
            'currency' => strtoupper((string) $this->input('currency', 'USD')),
            'addons' => BoostingCatalog::normalizeAddons($addons),
            'specific_agents' => BoostingCatalog::normalizeSpecificAgents($specificAgents),
            'one_trick_agent' => BoostingCatalog::normalizeOneTrickAgent($oneTrickAgent),
            'notes' => $this->normalizeNullableString('notes', 2000),
        ]);
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'customer')),
            ],
            'booster_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'booster')),
            ],
            'product' => ['required', 'string', 'max:255', Rule::in($this->serviceOptions())],
            'game' => ['nullable', 'string', 'max:255'],
            'payment_status' => ['required', Rule::in(array_keys(AdminController::PAYMENT_STATUS_OPTIONS))],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'contact_method' => ['nullable', Rule::in(['email', 'whatsapp', 'discord'])],
            'whatsapp' => ['nullable', 'string', 'max:255'],
            'discord' => ['nullable', 'string', 'max:255'],
            'current_division' => ['nullable', 'string', 'max:255'],
            'desired_division' => ['nullable', 'string', 'max:255'],
            'current_rr' => ['nullable', 'integer', 'min:0', 'max:100'],
            'average_rr' => ['nullable', 'string', 'max:255'],
            'number_of_wins' => ['nullable', 'integer', 'min:1', 'max:5'],
            'number_of_placement_games' => ['nullable', 'integer', 'min:1', 'max:5'],
            'region' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:255'],
            'account_type' => ['nullable', 'string', 'max:255'],
            'addons' => ['nullable', 'array'],
            'addons.*' => ['string', Rule::in(BoostingCatalog::allowedAddonLabels())],
            'specific_agents' => ['nullable', 'array'],
            'specific_agents.*' => ['string', 'max:64'],
            'one_trick_agent' => ['nullable', 'array'],
            'one_trick_agent.*' => ['string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function serviceOptions(): array
    {
        /** @var GameCatalog $gameCatalog */
        $gameCatalog = app(GameCatalog::class);
        $selectedGameSlug = $gameCatalog->normalizeSlug($this->input('game', GameCatalog::DEFAULT_GAME_SLUG));
        $selectedGame = $gameCatalog->game($selectedGameSlug);

        return collect($selectedGame['serviceOptions'] ?? [])
            ->merge(BoostingCatalog::serviceOptions())
            ->merge(collect($gameCatalog->all(includeDrafts: true))->flatMap(fn (array $game): array => $game['serviceOptions'] ?? []))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (AdminManualOrderData::selectionValidationErrors(
                    $this->rawAgentSelections,
                    $this->input('addons', [])
                ) as $inputName => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($inputName, $message);
                    }
                }

                if (AdminManualOrderData::manualPriceProvided($this->input('price'))) {
                    return;
                }

                $payloadService = app(OrderPricingPayloadService::class);
                $pricingEngine = app(PricingEngineManager::class);
                $pricingPayload = $payloadService->payloadFromAdminInput($this->safe()->only([
                    'game',
                    'product',
                    'current_division',
                    'desired_division',
                    'current_rr',
                    'average_rr',
                    'region',
                    'platform',
                    'account_type',
                    'addons',
                    'specific_agents',
                    'one_trick_agent',
                    'number_of_wins',
                    'number_of_placement_games',
                ]));

                if (! $payloadService->canAuthoritativelyPrice($pricingPayload)) {
                    $validator->errors()->add('price', 'Enter a custom price when the standard customer-flow preview cannot calculate this order.');

                    return;
                }

                $pricingResult = $pricingEngine->calculate($pricingPayload);

                if (($pricingResult['validationErrors'] ?? []) !== []) {
                    $validator->errors()->add('price', 'Enter a custom price when this order intentionally bypasses customer-flow pricing restrictions.');
                }
            },
        ];
    }
}
