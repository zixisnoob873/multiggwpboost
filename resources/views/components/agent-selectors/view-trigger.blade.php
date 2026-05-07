@props([
    'selectorKey' => 'specificAgents',
    'selectedUuids' => [],
    'title' => null,
    'description' => null,
    'label' => 'See Agents',
])

@php
    $definition = \App\Support\BoostingCatalog::agentSelectionAddon($selectorKey) ?? [];
    $resolvedTitle = $title ?? ($definition['view_title'] ?? 'Selected agent selection');
    $resolvedDescription = $description ?? ($definition['view_description'] ?? 'Review the agents linked to this order.');
    $resolvedLabel = $definition['label'] ?? 'Agent Selection';
    $normalizedSelection = \App\Support\ValorantAgentCatalog::normalizeSelection($selectedUuids);
    $resolvedAgents = \App\Support\ValorantAgentCatalog::resolveMany($normalizedSelection);
    $agentNames = implode(', ', array_column($resolvedAgents, 'displayName'));
@endphp

@if(count($normalizedSelection))
    <button
        type="button"
        {{ $attributes->class(['btn', 'btn-outline-light', 'btn-sm']) }}
        data-agent-selector-view-trigger
        data-agent-selector-key="{{ $selectorKey }}"
        data-agent-selector-label="{{ $resolvedLabel }}"
        data-agent-selector-title="{{ $resolvedTitle }}"
        data-agent-selector-description="{{ $resolvedDescription }}"
        data-agent-selector-selection='@json($normalizedSelection)'
        aria-label="{{ $agentNames !== '' ? "{$label}: {$agentNames}" : $label }}"
    >
        {{ $label }}
    </button>
@endif
