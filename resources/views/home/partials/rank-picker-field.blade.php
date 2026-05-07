@php
    $options = array_values($options ?? []);
    $selected = (string) ($selected ?? ($options[0] ?? ''));
    $triggerId = "{$id}Trigger";
    $modalTitle = $modalTitle ?? "Choose {$label}";
    $placeholder = $placeholder ?? "Choose {$label}";
    $locked = (bool) ($locked ?? false);
    $lockedTitle = $lockedTitle ?? "{$label} is locked.";
@endphp

<div class="ggwp-rank-picker-field" data-rank-picker-field>
    <label class="form-label" for="{{ $triggerId }}">{{ $label }}</label>

    <select
        id="{{ $id }}"
        class="form-select ggwp-rank-picker-native"
        data-rank-picker-select
        data-rank-picker-label="{{ $label }}"
        data-rank-picker-modal-title="{{ $modalTitle }}"
        data-rank-picker-placeholder="{{ $placeholder }}"
        tabindex="-1"
        aria-hidden="true"
    >
        @include('home.partials.rank-options', ['options' => $options, 'selected' => $selected])
    </select>

    <button
        id="{{ $triggerId }}"
        class="ggwp-rank-picker-trigger{{ $locked ? ' is-locked' : '' }}"
        type="button"
        data-rank-picker-trigger
        data-rank-picker-target="{{ $id }}"
        @if($locked)
            data-rank-picker-locked="true"
            aria-disabled="true"
            title="{{ $lockedTitle }}"
        @else
            aria-haspopup="dialog"
            aria-controls="homepageRankPickerModal"
            aria-expanded="false"
        @endif
    >
        <span class="ggwp-rank-picker-trigger__art" data-rank-picker-trigger-art aria-hidden="true"></span>

        <span class="ggwp-rank-picker-trigger__copy">
            <span class="ggwp-rank-picker-trigger__eyebrow">{{ $label }}</span>
            <span class="ggwp-rank-picker-trigger__value" data-rank-picker-trigger-value>{{ $selected ?: $placeholder }}</span>
        </span>

        <span class="ggwp-rank-picker-trigger__meta" aria-hidden="true">
            <span class="ggwp-rank-picker-trigger__badge">
                <svg viewBox="0 0 20 20" focusable="false">
                    <path d="M10 3.5 12 7.5l4.5.65-3.25 3.15.8 4.45L10 13.7l-4.05 2.05.8-4.45L3.5 8.15 8 7.5 10 3.5Z" fill="currentColor" />
                </svg>
            </span>
        </span>
    </button>
</div>
