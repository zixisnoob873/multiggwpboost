@php
    $formContext = $formContext ?? ($booster?->exists ? 'featured-booster-'.$booster->id : 'featured-booster-create');
    $modalId = $modalId ?? $formContext;
    $useOldInput = old('featured_booster_context') === $formContext;
    $featuredBoosterInput = $useOldInput ? (array) old('featured_booster', []) : [];
@endphp

<form
    action="{{ $action }}"
    method="POST"
    class="row g-2{{ empty($wrapperClass) ? '' : ' '.$wrapperClass }}"
    data-loading-form
    data-validate-form
    data-modal-reset-form
    novalidate
>
    @csrf
    @if (! empty($method) && strtoupper($method) !== 'POST')
        @method($method)
    @endif

    <input type="hidden" name="modal_id" value="{{ $modalId }}">
    <input type="hidden" name="featured_booster_context" value="{{ $formContext }}">

    <div class="col-md-6">
        <label class="form-label" for="featuredBoosterName{{ $formContext }}">Name</label>
        <input
            id="featuredBoosterName{{ $formContext }}"
            class="form-control {{ $useOldInput && $errors->has('name') ? 'is-invalid' : '' }}"
            name="featured_booster[name]"
            value="{{ data_get($featuredBoosterInput, 'name', $booster->name ?? '') }}"
            maxlength="255"
            required
        >
        @if($useOldInput)
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        @endif
    </div>
    <div class="col-md-3">
        <label class="form-label" for="featuredBoosterRegion{{ $formContext }}">Region</label>
        <input
            id="featuredBoosterRegion{{ $formContext }}"
            class="form-control {{ $useOldInput && $errors->has('region') ? 'is-invalid' : '' }}"
            name="featured_booster[region]"
            value="{{ data_get($featuredBoosterInput, 'region', $booster->region ?? '') }}"
            maxlength="255"
            required
        >
        @if($useOldInput)
            @error('region')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        @endif
    </div>
    <div class="col-md-3">
        <label class="form-label" for="featuredBoosterPlatform{{ $formContext }}">Platform</label>
        <input
            id="featuredBoosterPlatform{{ $formContext }}"
            class="form-control {{ $useOldInput && $errors->has('platform') ? 'is-invalid' : '' }}"
            name="featured_booster[platform]"
            value="{{ data_get($featuredBoosterInput, 'platform', $booster->platform ?? 'PC') }}"
            maxlength="255"
            required
        >
        @if($useOldInput)
            @error('platform')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        @endif
    </div>
    <div class="col-md-4">
        <label class="form-label" for="featuredBoosterSuccessRate{{ $formContext }}">Success Rate</label>
        <input
            id="featuredBoosterSuccessRate{{ $formContext }}"
            class="form-control {{ $useOldInput && $errors->has('success_rate') ? 'is-invalid' : '' }}"
            type="number"
            step="0.1"
            min="0"
            max="100"
            name="featured_booster[success_rate]"
            value="{{ data_get($featuredBoosterInput, 'success_rate', $booster->success_rate ?? '') }}"
            required
        >
        @if($useOldInput)
            @error('success_rate')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        @endif
    </div>
    <div class="col-md-4">
        <label class="form-label" for="featuredBoosterActiveOrders{{ $formContext }}">Active Orders</label>
        <input
            id="featuredBoosterActiveOrders{{ $formContext }}"
            class="form-control {{ $useOldInput && $errors->has('active_orders') ? 'is-invalid' : '' }}"
            type="number"
            min="0"
            name="featured_booster[active_orders]"
            value="{{ data_get($featuredBoosterInput, 'active_orders', $booster->active_orders ?? '') }}"
            required
        >
        @if($useOldInput)
            @error('active_orders')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        @endif
    </div>
    <div class="col-md-4">
        <label class="form-label" for="featuredBoosterSortOrder{{ $formContext }}">Order</label>
        <input
            id="featuredBoosterSortOrder{{ $formContext }}"
            class="form-control {{ $useOldInput && $errors->has('sort_order') ? 'is-invalid' : '' }}"
            type="number"
            min="0"
            max="9999"
            name="featured_booster[sort_order]"
            value="{{ data_get($featuredBoosterInput, 'sort_order', $booster->sort_order ?? '') }}"
            required
        >
        @if($useOldInput)
            @error('sort_order')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        @endif
    </div>
    <div class="col-12">
        <label class="form-check">
            <input
                class="form-check-input"
                type="checkbox"
                name="featured_booster[is_verified]"
                value="1"
                {{ data_get($featuredBoosterInput, 'is_verified', $booster->is_verified ?? true) ? 'checked' : '' }}
            >
            <span class="form-check-label">Verified</span>
        </label>
    </div>
    <div class="col-12 d-flex justify-content-end gap-2">
        <button class="btn {{ $submitClass ?? 'btn-outline-light btn-sm' }}" type="submit" data-busy-label="Saving...">{{ $submitLabel }}</button>
    </div>
</form>
