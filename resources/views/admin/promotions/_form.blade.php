@php
    $promotion ??= null;
    $formAction = $promotion
        ? route('admin-promotions.update', $promotion)
        : route('admin-promotions.store');
    $submitLabel = $promotion ? 'Update Promotion' : 'Create Promotion';
    $sortOrderValue = old('sort_order', $promotion?->sort_order ?? \App\Models\Promotion::nextSortOrder());
@endphp

<form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" class="d-grid gap-3" data-loading-form data-validate-form novalidate>
    @csrf
    @if($promotion)
        @method('PATCH')
    @endif

    <div class="row g-2">
        <div class="col-12">
            <label class="form-label" for="promotionTitle">Title</label>
            <input
                id="promotionTitle"
                name="title"
                type="text"
                class="form-control @error('title') is-invalid @enderror"
                value="{{ old('title', $promotion?->title) }}"
                maxlength="255"
                placeholder="Weekend Rank Rush"
                required
            >
            @error('title')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-12">
            <label class="form-label" for="promotionDescription">Description</label>
            <textarea
                id="promotionDescription"
                name="description"
                rows="4"
                class="form-control @error('description') is-invalid @enderror"
                maxlength="1000"
                placeholder="Limited slots for faster turnaround this week."
                required
            >{{ old('description', $promotion?->description) }}</textarea>
            @error('description')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label" for="promotionButtonText">Button Text</label>
            <input
                id="promotionButtonText"
                name="button_text"
                type="text"
                class="form-control @error('button_text') is-invalid @enderror"
                value="{{ old('button_text', $promotion?->button_text) }}"
                maxlength="80"
                placeholder="Start Boost"
            >
            @error('button_text')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label" for="promotionButtonLink">Button Link</label>
            <input
                id="promotionButtonLink"
                name="button_link"
                type="text"
                class="form-control @error('button_link') is-invalid @enderror"
                value="{{ old('button_link', $promotion?->button_link) }}"
                maxlength="2048"
                placeholder="/#servicesTab or https://example.com"
            >
            @error('button_link')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label" for="promotionSortOrder">Order</label>
            <input
                id="promotionSortOrder"
                name="sort_order"
                type="number"
                min="0"
                max="9999"
                class="form-control @error('sort_order') is-invalid @enderror"
                value="{{ $sortOrderValue }}"
                required
            >
            @error('sort_order')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label" for="promotionImage">Image</label>
            <input
                id="promotionImage"
                name="image"
                type="file"
                class="form-control @error('image') is-invalid @enderror"
                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                {{ $promotion ? '' : 'required' }}
            >
            @error('image')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        @if($promotion?->image_path)
            <div class="col-12">
                <div class="d-flex flex-wrap align-items-center gap-3 border rounded p-3 ggwp-inline-editor">
                    <img
                        src="{{ $promotion->imageUrl() }}"
                        alt="{{ $promotion->title }}"
                        class="rounded"
                        style="width: 120px; height: 80px; object-fit: cover;"
                        loading="lazy"
                        decoding="async"
                    >
                    <div class="small text-secondary">Current image</div>
                </div>
            </div>
        @endif
    </div>

    <div class="row g-2">
        <div class="col-md-6">
            <label class="form-check">
                <input
                    class="form-check-input"
                    type="checkbox"
                    name="is_active"
                    value="1"
                    {{ old('is_active', $promotion?->is_active ?? true) ? 'checked' : '' }}
                >
                <span class="form-check-label">Promotion is active</span>
            </label>
        </div>

        <div class="col-md-6">
            <label class="form-check">
                <input
                    class="form-check-input"
                    type="checkbox"
                    name="show_on_homepage"
                    value="1"
                    {{ old('show_on_homepage', $promotion?->show_on_homepage ?? true) ? 'checked' : '' }}
                >
                <span class="form-check-label">Show on homepage</span>
            </label>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-danger" type="submit" data-busy-label="{{ $promotion ? 'Saving...' : 'Creating...' }}">{{ $submitLabel }}</button>
    </div>
</form>
