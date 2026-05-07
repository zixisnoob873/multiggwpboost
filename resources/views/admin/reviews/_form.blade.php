@php
    $review = $review ?? null;
    $action = $action ?? route('admin-reviews.store');
    $method = $method ?? 'POST';
    $submitLabel = $submitLabel ?? 'Save Review';
@endphp

<form method="POST" action="{{ $action }}" class="row g-3" data-validate-form novalidate>
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="col-md-6">
        <label class="form-label" for="reviewAuthorName">Customer Name</label>
        <input
            id="reviewAuthorName"
            class="form-control @error('author_name') is-invalid @enderror"
            name="author_name"
            maxlength="120"
            value="{{ old('author_name', $review?->author_name) }}"
            required
        >
        @error('author_name')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="reviewService">Service</label>
        <input
            id="reviewService"
            class="form-control @error('service') is-invalid @enderror"
            name="service"
            maxlength="120"
            value="{{ old('service', $review?->service) }}"
            required
        >
        @error('service')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label class="form-label" for="reviewQuote">Review</label>
        <textarea
            id="reviewQuote"
            class="form-control @error('quote') is-invalid @enderror"
            name="quote"
            rows="5"
            maxlength="1200"
            minlength="20"
            required
        >{{ old('quote', $review?->quote) }}</textarea>
        @error('quote')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label" for="reviewSortOrder">Sort Order</label>
        <input
            id="reviewSortOrder"
            type="number"
            class="form-control @error('sort_order') is-invalid @enderror"
            name="sort_order"
            min="0"
            max="9999"
            value="{{ old('sort_order', $review?->sort_order ?? 0) }}"
            required
        >
        @error('sort_order')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 d-flex justify-content-end">
        <button class="btn btn-danger" type="submit">{{ $submitLabel }}</button>
    </div>
</form>
