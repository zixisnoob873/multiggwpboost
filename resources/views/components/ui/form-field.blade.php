@props([
    'label',
    'for',
    'help' => null,
    'error' => null,
])

<div {{ $attributes->class('ggwp-form-field') }}>
    <label class="form-label" for="{{ $for }}">{{ $label }}</label>
    {{ $slot }}
    @if($help)
        <div class="form-text">{{ $help }}</div>
    @endif
    @if($error)
        <div class="invalid-feedback d-block">{{ $error }}</div>
    @endif
</div>
