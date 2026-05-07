@extends('layouts.admin')

@section('title', 'GGWP Boost | Promotions')


@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense">
    @include('admin.partials.page-header', [
        'title' => 'Promotions',
        'meta' => array_values(array_filter([
            number_format($promotionCount).' total',
            number_format($homepagePromotionCount).' on homepage',
            $promotionSearch !== '' ? 'Filtered: '.$promotionSearch : null,
        ])),
        'actions' => [
            ['label' => 'Dashboard', 'href' => route('admin-dashboard')],
            ['label' => 'Content Home', 'href' => route('admin-content.index')],
        ],
    ])

    <div class="row g-2">
        <div class="col-xl-4">
            <section class="card app-card admin-section-card">
                <div class="card-body">
                    <h2 class="h4 mb-3">Add Promotion</h2>
                    @include('admin.promotions._form')
                </div>
            </section>
        </div>

        <div class="col-xl-8">
            <section class="card app-card admin-section-card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <h2 class="h4 mb-0">Promotion List</h2>

                        <form method="GET" action="{{ route('admin-promotions.index') }}" class="d-flex flex-wrap gap-2">
                            <div class="input-group input-group-sm">
                                <input
                                    id="promotionSearch"
                                    name="search"
                                    type="search"
                                    value="{{ $promotionSearch }}"
                                    class="form-control ggwp-toolbar-search-input"
                                    placeholder="Search promotions"
                                >
                                <button class="btn btn-outline-light" type="submit">Search</button>
                            </div>
                            @if($promotionSearch !== '')
                                <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin-promotions.index') }}">Clear</a>
                            @endif
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0 ggwp-data-table">
                            <thead>
                                <tr>
                                    <th>Promotion</th>
                                    <th>Status</th>
                                    <th>Homepage</th>
                                    <th>Order</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($promotions as $promotion)
                                    <tr>
                                        <td>
                                            <div class="ggwp-promotion-summary">
                                                <img
                                                    src="{{ $promotion->imageUrl() }}"
                                                    alt="{{ $promotion->title }}"
                                                    class="rounded ggwp-promotion-summary__image"
                                                    loading="lazy"
                                                    decoding="async"
                                                >
                                                <div class="min-w-0">
                                                    <div class="fw-semibold ggwp-promotion-summary__title">{{ $promotion->title }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $promotion->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                                {{ $promotion->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $promotion->show_on_homepage ? 'text-bg-primary' : 'text-bg-secondary' }}">
                                                {{ $promotion->show_on_homepage ? 'Visible' : 'Hidden' }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-semibold">{{ $promotion->sort_order }}</span>
                                        </td>
                                        <td class="text-end">
                                            <div class="ggwp-table-actions justify-content-end">
                                                <a class="btn btn-outline-light btn-sm" href="{{ route('admin-promotions.edit', $promotion) }}">Edit</a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-secondary py-4">No promotions created yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $promotions->withQueryString()->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>
@endsection
