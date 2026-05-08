@php
    $game = $game ?? null;
    $method = $method ?? 'POST';
    $featured = old('featured', data_get($game?->metadata ?? [], 'featured', false));
@endphp

<form method="POST" action="{{ $action }}" class="row g-3" data-validate-form novalidate>
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="col-md-7">
        <label class="form-label" for="gameName">Name</label>
        <input id="gameName" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $game?->name) }}" maxlength="160" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-5">
        <label class="form-label" for="gameSlug">Slug</label>
        <input id="gameSlug" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $game?->slug) }}" maxlength="180" aria-describedby="gameSlugHelp">
        <div id="gameSlugHelp" class="form-text">Leave blank to generate from the name.</div>
        @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label" for="gameShortName">Short Name</label>
        <input id="gameShortName" name="short_name" class="form-control @error('short_name') is-invalid @enderror" value="{{ old('short_name', $game?->short_name) }}" maxlength="80">
        @error('short_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label" for="gameCategory">Category</label>
        <select id="gameCategory" name="game_category_id" class="form-select @error('game_category_id') is-invalid @enderror">
            <option value="">No category</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((string) old('game_category_id', $game?->game_category_id) === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        @error('game_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-2">
        <label class="form-label" for="gameStatus">Status</label>
        <select id="gameStatus" name="status" class="form-select @error('status') is-invalid @enderror" required>
            @foreach($statuses as $status)
                <option value="{{ $status }}" @selected(old('status', $game?->status ?? \App\Models\Game::STATUS_DRAFT) === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-2">
        <label class="form-label" for="gameSortOrder">Sort</label>
        <input id="gameSortOrder" type="number" min="0" max="9999" name="sort_order" class="form-control @error('sort_order') is-invalid @enderror" value="{{ old('sort_order', $game?->sort_order ?? 0) }}" required>
        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label" for="gameDescription">Description</label>
        <textarea id="gameDescription" name="description" class="form-control @error('description') is-invalid @enderror" rows="4" maxlength="2000">{{ old('description', $game?->description) }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <div class="form-check">
            <input id="gameFeatured" class="form-check-input @error('featured') is-invalid @enderror" type="checkbox" name="featured" value="1" @checked($featured)>
            <label class="form-check-label" for="gameFeatured">Feature this game on the homepage</label>
            @error('featured')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-12">
        <h2 class="h6 mb-0">SEO</h2>
    </div>

    <div class="col-md-6">
        <label class="form-label" for="gameMetaTitle">Meta Title</label>
        <input id="gameMetaTitle" name="meta_title" class="form-control @error('meta_title') is-invalid @enderror" value="{{ old('meta_title', $game?->seoMetadata?->meta_title) }}" maxlength="255">
        @error('meta_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="gameMetaDescription">Meta Description</label>
        <textarea id="gameMetaDescription" name="meta_description" class="form-control @error('meta_description') is-invalid @enderror" rows="2" maxlength="130">{{ old('meta_description', $game?->seoMetadata?->meta_description) }}</textarea>
        @error('meta_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 d-flex justify-content-end">
        <button class="btn btn-danger" type="submit">{{ $submitLabel }}</button>
    </div>
</form>

