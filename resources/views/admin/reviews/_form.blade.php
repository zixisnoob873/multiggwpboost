@php
    $review = $review ?? null;
    $action = $action ?? route('admin-reviews.store');
    $method = $method ?? 'POST';
    $submitLabel = $submitLabel ?? 'Save Review';
    $selectedGameId = (string) old('game_id', $review?->game_id);
    $selectedServiceId = (string) old('service_id', $review?->service_id);
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

    <div class="col-md-4">
        <label class="form-label" for="reviewGame">Game</label>
        <select id="reviewGame" name="game_id" class="form-select @error('game_id') is-invalid @enderror">
            <option value="">Global review</option>
            @foreach($games ?? [] as $game)
                <option value="{{ $game->id }}" @selected($selectedGameId === (string) $game->id)>{{ $game->name }}</option>
            @endforeach
        </select>
        @error('game_id')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label" for="reviewServiceRecord">Service Page</label>
        <select id="reviewServiceRecord" name="service_id" class="form-select @error('service_id') is-invalid @enderror">
            <option value="">All services</option>
            @foreach($services ?? [] as $serviceRecord)
                <option value="{{ $serviceRecord->id }}" @selected($selectedServiceId === (string) $serviceRecord->id)>{{ $serviceRecord->game?->name }} - {{ $serviceRecord->name }}</option>
            @endforeach
        </select>
        @error('service_id')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 d-flex justify-content-end">
        <button class="btn btn-danger" type="submit">{{ $submitLabel }}</button>
    </div>
</form>
