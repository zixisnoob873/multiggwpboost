@php
    $formContext = $formContext ?? 'addon-tooltip-'.$addon['slug'];
    $modalId = $modalId ?? $formContext;
    $useOldInput = old('addon_tooltip_context') === $formContext;
    $addonTooltipInput = $useOldInput ? (array) old('addon_tooltip', []) : [];
@endphp

<form
    method="POST"
    action="{{ route('admin-addon-tooltips.update', ['addonSlug' => $addon['slug']]) }}"
    class="d-grid gap-2"
    data-loading-form
    data-validate-form
    data-modal-reset-form
    novalidate
>
    @csrf
    @method('PATCH')

    <input type="hidden" name="modal_id" value="{{ $modalId }}">
    <input type="hidden" name="addon_tooltip_context" value="{{ $formContext }}">

    <div>
        <label class="form-label" for="addonTooltipDescription{{ $formContext }}">Tooltip</label>
        <textarea
            id="addonTooltipDescription{{ $formContext }}"
            class="form-control {{ $useOldInput && $errors->has('description') ? 'is-invalid' : '' }}"
            name="addon_tooltip[description]"
            rows="6"
            maxlength="2000"
        >{{ data_get($addonTooltipInput, 'description', $addon['description'] ?? '') }}</textarea>
        @if($useOldInput)
            @error('description')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        @endif
    </div>

    <div class="d-flex justify-content-end gap-2">
        <button class="btn btn-danger btn-sm" type="submit" data-busy-label="Saving...">Save</button>
    </div>
</form>
