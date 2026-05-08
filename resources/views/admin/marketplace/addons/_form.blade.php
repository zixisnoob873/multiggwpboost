@php
    $addon = $addon ?? null;
    $method = $method ?? 'POST';
    $selectedServiceIds = collect(old('service_ids', $selectedServiceIds ?? []))->map(fn ($id) => (string) $id)->all();
@endphp

<form method="POST" action="{{ $action }}" class="row g-3" data-validate-form novalidate>
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="col-md-6">
        <label class="form-label" for="addonGame">Game</label>
        <select id="addonGame" name="game_id" class="form-select @error('game_id') is-invalid @enderror" required>
            <option value="">Select game</option>
            @foreach($games as $game)
                <option value="{{ $game->id }}" @selected((string) old('game_id', $addon?->game_id) === (string) $game->id)>{{ $game->name }}</option>
            @endforeach
        </select>
        @error('game_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="addonLabel">Label</label>
        <input id="addonLabel" name="label" class="form-control @error('label') is-invalid @enderror" value="{{ old('label', $addon?->label) }}" maxlength="160" required>
        @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="addonSlug">Slug</label>
        <input id="addonSlug" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $addon?->slug) }}" maxlength="180" aria-describedby="addonSlugHelp">
        <div id="addonSlugHelp" class="form-text">Leave blank to generate from the label.</div>
        @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label" for="addonStatus">Status</label>
        <select id="addonStatus" name="status" class="form-select @error('status') is-invalid @enderror" required>
            @foreach($statuses as $status)
                <option value="{{ $status }}" @selected(old('status', $addon?->status ?? \App\Models\GameAddon::STATUS_DRAFT) === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label" for="addonSortOrder">Sort</label>
        <input id="addonSortOrder" type="number" min="0" max="9999" name="sort_order" class="form-control @error('sort_order') is-invalid @enderror" value="{{ old('sort_order', $addon?->sort_order ?? 0) }}" required>
        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label" for="addonPricingType">Pricing Type</label>
        <select id="addonPricingType" name="pricing_type" class="form-select @error('pricing_type') is-invalid @enderror" required>
            @foreach($pricingTypes as $value => $label)
                <option value="{{ $value }}" @selected(old('pricing_type', $addon?->pricing_type ?? 'free') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('pricing_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label" for="addonPricingValue">Pricing Value</label>
        <input id="addonPricingValue" type="number" min="0" step="0.01" name="pricing_value" class="form-control @error('pricing_value') is-invalid @enderror" value="{{ old('pricing_value', $addon?->pricing_value ?? 0) }}" inputmode="decimal">
        @error('pricing_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label" for="addonIcon">Icon</label>
        <input id="addonIcon" name="icon" class="form-control @error('icon') is-invalid @enderror" value="{{ old('icon', $addon?->icon) }}" maxlength="2048">
        @error('icon')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label" for="addonDescription">Description</label>
        <textarea id="addonDescription" name="description" class="form-control @error('description') is-invalid @enderror" rows="4" maxlength="2000">{{ old('description', $addon?->description) }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <h2 class="h6 mb-0">Assigned Services</h2>
        <p class="small text-secondary mb-2">Select services from the same game. Save after changing the game to refresh this list.</p>
        @error('service_ids')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        <div class="row g-2">
            @forelse($services as $service)
                <div class="col-md-6">
                    <div class="form-check">
                        <input id="addonService{{ $service->id }}" class="form-check-input" type="checkbox" name="service_ids[]" value="{{ $service->id }}" @checked(in_array((string) $service->id, $selectedServiceIds, true))>
                        <label class="form-check-label" for="addonService{{ $service->id }}">{{ $service->name }} <span class="text-secondary">({{ $service->status }})</span></label>
                    </div>
                </div>
            @empty
                <div class="col-12 text-secondary">No services are available for the selected game yet.</div>
            @endforelse
        </div>
    </div>

    <div class="col-12 d-flex justify-content-end">
        <button class="btn btn-danger" type="submit">{{ $submitLabel }}</button>
    </div>
</form>

