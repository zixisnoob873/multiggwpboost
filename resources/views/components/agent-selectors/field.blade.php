@props([
    'fieldId' => 'agent-selector-field',
    'selectorKey' => 'specificAgents',
    'context' => 'global',
    'eyebrow' => 'Agent Selection',
    'title' => 'Choose agents',
    'description' => 'Select the agents that should be tied to this order.',
    'summaryEmpty' => 'No agents selected yet.',
    'buttonLabel' => 'Manage Agents',
    'inputName' => null,
    'addonInputId' => null,
    'selectedUuids' => [],
    'errorKey' => null,
    'visible' => true,
    'requiredMessage' => 'Select the required agents before saving.',
    'selectionMin' => 1,
    'selectionMax' => null,
    'singleSelect' => false,
])

@php
    $normalizedSelection = \App\Support\ValorantAgentCatalog::normalizeSelection($selectedUuids);
    $errorMessage = $errorKey ? $errors->first($errorKey) : null;
@endphp

<div
    class="ggwp-agents-field"
    data-agent-selector-field
    data-agent-selector-field-id="{{ $fieldId }}"
    data-agent-selector-context="{{ $context }}"
    data-agent-selector-key="{{ $selectorKey }}"
    data-agent-selector-label="{{ $eyebrow }}"
    data-agent-selector-title="{{ $title }}"
    data-agent-selector-description="{{ $description }}"
    data-agent-selector-summary-empty="{{ $summaryEmpty }}"
    data-agent-selector-required-message="{{ $requiredMessage }}"
    data-agent-selector-min-selections="{{ (int) $selectionMin }}"
    @if($selectionMax !== null)
        data-agent-selector-max-selections="{{ (int) $selectionMax }}"
    @endif
    data-agent-selector-single-select="{{ $singleSelect ? 'true' : 'false' }}"
    data-agent-selector-selection='@json($normalizedSelection)'
    @if($inputName)
        data-agent-selector-input-name="{{ $inputName }}"
    @endif
    @if($addonInputId)
        data-agent-selector-addon-input-id="{{ $addonInputId }}"
    @endif
>
    <div class="alert alert-danger small py-2 px-3 mb-3{{ $errorMessage ? '' : ' d-none' }}" data-agent-selector-field-error role="alert">
        {{ $errorMessage ?: $requiredMessage }}
    </div>

    <section class="ggwp-agents-field__panel card app-card{{ $visible ? '' : ' d-none' }}" data-agent-selector-field-panel>
        <div class="card-body">
            <div class="ggwp-agents-field__header">
                <div>
                    <div class="ggwp-agents-field__eyebrow">{{ $eyebrow }}</div>
                    <p class="text-secondary small mb-0">{{ $description }}</p>
                </div>

                <button type="button" class="btn btn-outline-light btn-sm" data-agent-selector-field-open>
                    {{ $buttonLabel }}
                </button>
            </div>

            <div class="ggwp-agents-field__status text-secondary small mt-3" data-agent-selector-field-status aria-live="polite">
                {{ $summaryEmpty }}
            </div>

            <div class="ggwp-agents-field__preview mt-3" data-agent-selector-field-preview></div>
        </div>
    </section>

    <div data-agent-selector-field-inputs></div>
</div>
