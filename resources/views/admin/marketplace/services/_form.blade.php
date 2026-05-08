@php
    $service = $service ?? null;
    $method = $method ?? 'POST';
    $selectedAddonIds = collect(old('addon_ids', $selectedAddonIds ?? []))->map(fn ($id) => (string) $id)->all();
    $homepageFeatured = old('homepage_featured', data_get($service?->metadata ?? [], 'homepage_featured', false));
@endphp

<form method="POST" action="{{ $action }}" class="row g-3" data-validate-form novalidate>
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="col-md-6">
        <label class="form-label" for="serviceGame">Game</label>
        <select id="serviceGame" name="game_id" class="form-select @error('game_id') is-invalid @enderror" required>
            <option value="">Select game</option>
            @foreach($games as $game)
                <option value="{{ $game->id }}" @selected((string) old('game_id', $service?->game_id) === (string) $game->id)>{{ $game->name }}</option>
            @endforeach
        </select>
        @error('game_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="serviceName">Name</label>
        <input id="serviceName" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $service?->name) }}" maxlength="160" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="serviceSlug">Slug</label>
        <input id="serviceSlug" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $service?->slug) }}" maxlength="180" aria-describedby="serviceSlugHelp">
        <div id="serviceSlugHelp" class="form-text">Leave blank to generate from the name.</div>
        @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="serviceKind">Kind</label>
        <input id="serviceKind" name="kind" class="form-control @error('kind') is-invalid @enderror" value="{{ old('kind', $service?->kind) }}" maxlength="120" aria-describedby="serviceKindHelp">
        <div id="serviceKindHelp" class="form-text">Used by pricing and page behavior, for example rank_boost.</div>
        @error('kind')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label" for="serviceStatus">Status</label>
        <select id="serviceStatus" name="status" class="form-select @error('status') is-invalid @enderror" required>
            @foreach($statuses as $status)
                <option value="{{ $status }}" @selected(old('status', $service?->status ?? \App\Models\GameService::STATUS_DRAFT) === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label" for="serviceSortOrder">Sort</label>
        <input id="serviceSortOrder" type="number" min="0" max="9999" name="sort_order" class="form-control @error('sort_order') is-invalid @enderror" value="{{ old('sort_order', $service?->sort_order ?? 0) }}" required>
        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label" for="serviceBasePrice">Base Price</label>
        <input id="serviceBasePrice" type="number" min="0" step="0.01" name="base_price" class="form-control @error('base_price') is-invalid @enderror" value="{{ old('base_price', $basePrice) }}" inputmode="decimal">
        @error('base_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label" for="serviceDescription">Description</label>
        <textarea id="serviceDescription" name="description" class="form-control @error('description') is-invalid @enderror" rows="4" maxlength="2000">{{ old('description', $service?->description) }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <div class="form-check">
            <input id="serviceHomepageFeatured" class="form-check-input @error('homepage_featured') is-invalid @enderror" type="checkbox" name="homepage_featured" value="1" @checked($homepageFeatured)>
            <label class="form-check-label" for="serviceHomepageFeatured">Highlight this service on the homepage</label>
            @error('homepage_featured')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-12">
        <h2 class="h6 mb-0">Assigned Addons</h2>
        <p class="small text-secondary mb-2">Select addons from the same game. Save after changing the game to refresh this list.</p>
        @error('addon_ids')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        <div class="row g-2">
            @forelse($addons as $addon)
                <div class="col-md-6">
                    <div class="form-check">
                        <input id="serviceAddon{{ $addon->id }}" class="form-check-input" type="checkbox" name="addon_ids[]" value="{{ $addon->id }}" @checked(in_array((string) $addon->id, $selectedAddonIds, true))>
                        <label class="form-check-label" for="serviceAddon{{ $addon->id }}">{{ $addon->label }} <span class="text-secondary">({{ $addon->status }})</span></label>
                    </div>
                </div>
            @empty
                <div class="col-12 text-secondary">No addons are available for the selected game yet.</div>
            @endforelse
        </div>
    </div>

    <div class="col-12">
        <h2 class="h6 mb-0">SEO</h2>
    </div>

    <div class="col-md-6">
        <label class="form-label" for="serviceMetaTitle">Meta Title</label>
        <input id="serviceMetaTitle" name="meta_title" class="form-control @error('meta_title') is-invalid @enderror" value="{{ old('meta_title', $service?->seoMetadata?->meta_title) }}" maxlength="255">
        @error('meta_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="serviceMetaDescription">Meta Description</label>
        <textarea id="serviceMetaDescription" name="meta_description" class="form-control @error('meta_description') is-invalid @enderror" rows="2" maxlength="130">{{ old('meta_description', $service?->seoMetadata?->meta_description) }}</textarea>
        @error('meta_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 d-flex justify-content-end">
        <button class="btn btn-danger" type="submit">{{ $submitLabel }}</button>
    </div>
</form>

