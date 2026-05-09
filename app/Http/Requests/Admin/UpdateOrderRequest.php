<?php

namespace App\Http\Requests\Admin;

use App\Http\Controllers\Admin\AdminController;
use App\Services\Orders\OrderPricingPayloadService;
use App\Support\AdminManualOrderData;
use App\Support\AgentSelectionValidator;
use App\Support\BoostingCatalog;
use App\Support\OrderAddonRules;
use App\Support\OrderStatus;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class UpdateOrderRequest extends AdminRequest
{
    protected array $rawDetails = [];

    public function authorize(): bool
    {
        return $this->authorizeAdminModule('operations');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(OrderStatus::values())],
            'payment_status' => ['sometimes', Rule::in(array_keys(AdminController::PAYMENT_STATUS_OPTIONS))],
            'user_id' => [
                'sometimes',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'customer')),
            ],
            'booster_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'booster')),
            ],
            'product' => ['sometimes', 'string', 'max:255', Rule::in($this->allowedProducts())],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'details' => ['sometimes', 'array'],
            'metadata' => ['sometimes', 'array'],
            'status_reason' => ['nullable', 'string', 'max:500'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'refund_method' => ['nullable', 'string', 'max:120'],
            'refund_reference' => ['nullable', 'string', 'max:120'],
            'refund_arrival_estimate' => ['nullable', 'string', 'max:160'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $details = $this->input('details');

        if (! is_array($details)) {
            return;
        }

        $this->rawDetails = $details;

        if (array_key_exists('addons', $details)) {
            $details['addons'] = BoostingCatalog::normalizeAddons($details['addons']);
        }

        if (array_key_exists('specificAgents', $details) || array_key_exists('specific_agents', $details)) {
            $details['specificAgents'] = BoostingCatalog::normalizeSpecificAgents(
                $details['specificAgents'] ?? $details['specific_agents'] ?? []
            );
        }

        if (array_key_exists('oneTrickAgent', $details) || array_key_exists('one_trick_agent', $details)) {
            $details['oneTrickAgent'] = BoostingCatalog::normalizeOneTrickAgent(
                $details['oneTrickAgent'] ?? $details['one_trick_agent'] ?? []
            );
        }

        if (array_key_exists('order.addons', $details)) {
            $details['order.addons'] = BoostingCatalog::normalizeAddons($details['order.addons']);
        }

        if (array_key_exists('order.specificAgents', $details) || array_key_exists('order.specific_agents', $details)) {
            $details['order.specificAgents'] = BoostingCatalog::normalizeSpecificAgents(
                $details['order.specificAgents'] ?? $details['order.specific_agents'] ?? []
            );
        }

        if (array_key_exists('order.oneTrickAgent', $details) || array_key_exists('order.one_trick_agent', $details)) {
            $details['order.oneTrickAgent'] = BoostingCatalog::normalizeOneTrickAgent(
                $details['order.oneTrickAgent'] ?? $details['order.one_trick_agent'] ?? []
            );
        }

        $this->merge([
            'details' => $details,
        ]);
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $details = $this->input('details');

                if (! is_array($details)) {
                    return;
                }

                $existingDetails = $this->currentOrderDetails();
                $mergedDetails = $this->mergeStructuredValues($existingDetails, Arr::undot($details));
                $product = (string) ($this->input('product') ?? $this->route('order')?->product);

                if ($this->isAdminOverrideOrder()) {
                    $overridePayload = AdminManualOrderData::payloadFromDetails($mergedDetails, $product);

                    foreach (AdminManualOrderData::selectionValidationErrors([
                        'specificAgents' => $overridePayload['specificAgents'] ?? [],
                        'oneTrickAgent' => $overridePayload['oneTrickAgent'] ?? [],
                    ], $overridePayload['addons'] ?? []) as $messages) {
                        foreach ($messages as $message) {
                            $validator->errors()->add('details', $message);
                        }
                    }

                    return;
                }

                $selectedAddons = $this->detailValue($details, $existingDetails, [
                    'addons',
                    'order.addons',
                ], []);
                $accountType = $this->detailValue($details, $existingDetails, [
                    'accountType',
                    'order.accountType',
                ], '');
                $selectionPayload = [];
                $addonRuleEvaluation = OrderAddonRules::evaluate([
                    'serviceType' => $product,
                    'boostMode' => $accountType,
                    'currentDivision' => $this->detailValue($details, $existingDetails, [
                        'currentDivision',
                        'current_division',
                        'from',
                        'order.currentDivision',
                        'order.currentRank',
                    ]),
                    'targetDivision' => $this->detailValue($details, $existingDetails, [
                        'desiredDivision',
                        'desired_division',
                        'to',
                        'order.desiredDivision',
                        'order.targetDivision',
                        'order.targetRank',
                    ]),
                    'addons' => $selectedAddons,
                ]);

                foreach (($addonRuleEvaluation['validationErrors'] ?? []) as $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add('details', $message);
                    }
                }

                foreach (BoostingCatalog::agentSelectionAddons() as $selectionKey => $definition) {
                    $selectionPayload[$selectionKey] = $this->detailValue($this->rawDetails, $existingDetails, [
                        $selectionKey,
                        $definition['input_name'],
                        'order.'.$selectionKey,
                        'order.'.$definition['input_name'],
                    ], []);
                }

                foreach (AgentSelectionValidator::validateSelections(
                    AgentSelectionValidator::inspectPayload($selectionPayload),
                    $selectedAddons,
                    $addonRuleEvaluation['disabledAddons'] ?? []
                ) as $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add('details', $message);
                    }
                }

                $payloadService = app(OrderPricingPayloadService::class);
                $pricingPayload = $payloadService->payloadFromDetails($mergedDetails, $product);

                if (! $payloadService->canAuthoritativelyPrice($pricingPayload)) {
                    return;
                }

                try {
                    $payloadService->calculate($pricingPayload);
                } catch (ValidationException $exception) {
                    foreach ($exception->errors() as $messages) {
                        foreach ($messages as $message) {
                            $validator->errors()->add('details', $message);
                        }
                    }
                }
            },
        ];
    }

    protected function allowedProducts(): array
    {
        $currentProduct = trim((string) ($this->route('order')?->product ?? ''));

        return array_values(array_unique(array_filter([
            ...BoostingCatalog::serviceOptions(),
            $currentProduct !== '' ? $currentProduct : null,
        ])));
    }

    protected function currentOrderDetails(): array
    {
        $details = $this->route('order')?->details;

        return is_array($details)
            ? $details
            : (json_decode((string) ($details ?? ''), true) ?: []);
    }

    protected function isAdminOverrideOrder(): bool
    {
        $order = $this->route('order');
        $metadata = $order?->metadata;
        $metadata = is_array($metadata)
            ? $metadata
            : (json_decode((string) ($metadata ?? ''), true) ?: []);

        return (bool) (($order?->is_custom ?? false) || data_get($metadata, 'adminOverride.enabled'));
    }

    protected function mergeStructuredValues(array $existing, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            if (
                array_key_exists($key, $existing)
                && is_array($existing[$key])
                && is_array($value)
                && Arr::isAssoc($existing[$key])
                && Arr::isAssoc($value)
            ) {
                $existing[$key] = $this->mergeStructuredValues($existing[$key], $value);

                continue;
            }

            $existing[$key] = $value;
        }

        return $existing;
    }

    protected function detailValue(array $details, array $existingDetails, array $keys, mixed $fallback = null): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $details)) {
                return $details[$key];
            }

            $sentinel = new \stdClass;
            $existing = data_get($existingDetails, $key, $sentinel);

            if (! $existing instanceof \stdClass) {
                return $existing;
            }
        }

        return $fallback;
    }
}
