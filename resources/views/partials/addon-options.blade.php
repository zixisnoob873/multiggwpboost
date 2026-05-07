@php
    $addons = $addons ?? [];
    $context = $context ?? 'global';
    $selected = collect($selected ?? [])->map(fn ($value) => (string) $value)->all();
    $inputName = $inputName ?? null;
    $columnClass = $columnClass ?? 'col-md-6';
    $selectedSpecificAgents = \App\Support\ValorantAgentCatalog::normalizeSelection($selectedSpecificAgents ?? []);
    $selectedOneTrickAgent = \App\Support\ValorantAgentCatalog::normalizeSelection($selectedOneTrickAgent ?? []);
    $specificAgentsInputName = $specificAgentsInputName ?? null;
    $oneTrickAgentInputName = $oneTrickAgentInputName ?? null;
    $specificAgentsErrorKey = $specificAgentsErrorKey ?? null;
    $oneTrickAgentErrorKey = $oneTrickAgentErrorKey ?? null;
    $serviceType = $serviceType ?? null;
    $serviceInputId = $serviceInputId ?? null;
    $boostModeInputId = $boostModeInputId ?? null;
    $currentRankInputId = $currentRankInputId ?? null;
    $targetRankInputId = $targetRankInputId ?? null;
    $messageId = $messageId ?? null;
    $allowAdminOverride = $allowAdminOverride ?? false;
    $showPricingLabel = $showPricingLabel ?? in_array($context, ['boost', 'placement', 'radiant', 'ranked', 'upgrade-order'], true);
    $agentSelectionValues = [
        'specificAgents' => $selectedSpecificAgents,
        'oneTrickAgent' => $selectedOneTrickAgent,
    ];
    $agentSelectionInputNames = [
        'specificAgents' => $specificAgentsInputName,
        'oneTrickAgent' => $oneTrickAgentInputName,
    ];
    $agentSelectionErrorKeys = [
        'specificAgents' => $specificAgentsErrorKey,
        'oneTrickAgent' => $oneTrickAgentErrorKey,
    ];
@endphp

<div
    class="row g-3 ggwp-addon-grid"
    data-addon-grid="{{ $context }}"
    data-addon-rule-context="{{ $context }}"
    @if($serviceType)
        data-addon-rule-service-type="{{ $serviceType }}"
    @endif
    @if($serviceInputId)
        data-addon-rule-service-input-id="{{ $serviceInputId }}"
    @endif
    @if($boostModeInputId)
        data-addon-rule-boost-mode-input-id="{{ $boostModeInputId }}"
    @endif
    @if($currentRankInputId)
        data-addon-rule-current-rank-input-id="{{ $currentRankInputId }}"
    @endif
    @if($targetRankInputId)
        data-addon-rule-target-rank-input-id="{{ $targetRankInputId }}"
    @endif
    @if($messageId)
        data-addon-rule-message-id="{{ $messageId }}"
    @endif
    @if($allowAdminOverride)
        data-addon-rule-allow-admin-override="true"
    @endif
>
    @foreach($addons as $addon)
        @php
            $inputId = "{$context}-addon-{$addon['slug']}";
            $isChecked = in_array($addon['label'], $selected, true);
        @endphp
        <div class="{{ $columnClass }}">
            <div class="ggwp-addon-option">
                <label class="form-check ggwp-addon-check" for="{{ $inputId }}">
                    <input
                        class="form-check-input ggwp-addon-check__input"
                        type="checkbox"
                        id="{{ $inputId }}"
                        @if($inputName)
                            name="{{ $inputName }}[]"
                        @endif
                        value="{{ $addon['label'] }}"
                        data-addon-label="{{ $addon['label'] }}"
                        data-addon-slug="{{ $addon['slug'] }}"
                        @checked($isChecked)
                    >
                    <span class="ggwp-addon-check__content">
                        <span class="ggwp-addon-check__title">{{ \App\Support\BoostingCatalog::addonDisplayLabel($addon['label'], $showPricingLabel) }}</span>
                        @if(!empty($addon['icon']))
                            <span class="ggwp-addon-check__media" aria-hidden="true">
                                <img
                                    src="{{ asset($addon['icon']) }}"
                                    alt=""
                                    class="ggwp-addon-check__icon"
                                    loading="lazy"
                                    decoding="async"
                                >
                            </span>
                        @endif
                    </span>
                </label>
                <button
                    class="ggwp-addon-info"
                    type="button"
                    data-bs-toggle="tooltip"
                    data-bs-placement="top"
                    data-bs-title="{{ $addon['description'] }}"
                    aria-label="More information about {{ $addon['label'] }}"
                >
                    <img
                        src="{{ asset('assets/info_button.png') }}"
                        alt=""
                        class="ggwp-addon-info__icon"
                        aria-hidden="true"
                        loading="lazy"
                        decoding="async"
                    >
                </button>
            </div>
        </div>
    @endforeach
</div>

@if($messageId)
    <div id="{{ $messageId }}" class="alert alert-warning small py-2 px-3 mt-3 d-none" data-addon-rule-message="{{ $context }}" role="alert"></div>
@endif

@foreach(\App\Support\BoostingCatalog::agentSelectionAddons() as $selectionKey => $definition)
    @php
        $agentSelectionAddon = collect($addons)->firstWhere('slug', $definition['slug']);
        $agentSelectionAddonInputId = $agentSelectionAddon ? "{$context}-addon-{$definition['slug']}" : null;
        $agentSelectionEnabled = $agentSelectionAddon
            ? in_array($definition['label'], $selected, true)
            : false;
    @endphp

    @if($agentSelectionAddonInputId)
        <div class="mt-3">
            <x-agent-selectors.field
                :field-id="'agent-selector-field-'.$definition['slug'].'-'.$context"
                :selector-key="$selectionKey"
                :context="$context"
                :eyebrow="$definition['label']"
                :title="$definition['title']"
                :description="$definition['description']"
                :summary-empty="$definition['summary_empty']"
                :button-label="$definition['button_label']"
                :input-name="$agentSelectionInputNames[$selectionKey] ?? null"
                :addon-input-id="$agentSelectionAddonInputId"
                :selected-uuids="$agentSelectionValues[$selectionKey] ?? []"
                :error-key="$agentSelectionErrorKeys[$selectionKey] ?? null"
                :visible="$agentSelectionEnabled"
                :required-message="$definition['required_message']"
                :selection-min="$definition['min']"
                :selection-max="$definition['max']"
                :single-select="$definition['single_select']"
            />
        </div>
    @endif
@endforeach
