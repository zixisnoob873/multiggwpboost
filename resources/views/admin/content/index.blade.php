@extends('layouts.admin')

@section('title', 'GGWP Boost | Content')

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide">
    @include('admin.partials.page-header', [
        'title' => 'Content Home',
        'subtitle' => 'Hub for managed pages, FAQ coverage, featured boosters, and addon tooltip publishing quality.',
        'actions' => [
            ['label' => 'Pages', 'href' => route('admin-pages.index'), 'class' => 'btn btn-danger btn-sm'],
            ['label' => 'FAQs', 'href' => route('admin-content.faqs.index')],
            ['label' => 'Featured Boosters', 'href' => route('admin-content.featured-boosters.index')],
            ['label' => 'Addon Tooltips', 'href' => route('admin-content.addon-tooltips.index')],
        ],
    ])

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card app-card admin-stat-card h-100">
                <div class="card-body">
                    <div class="admin-stat-card__label">Pages</div>
                    <div class="admin-stat-card__value">{{ number_format($contentCounts['pages'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card app-card admin-stat-card h-100">
                <div class="card-body">
                    <div class="admin-stat-card__label">FAQs</div>
                    <div class="admin-stat-card__value">{{ number_format($contentCounts['faqs'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card app-card admin-stat-card h-100">
                <div class="card-body">
                    <div class="admin-stat-card__label">Featured Boosters</div>
                    <div class="admin-stat-card__value">{{ number_format($contentCounts['featured_boosters'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card app-card admin-stat-card h-100">
                <div class="card-body">
                    <div class="admin-stat-card__label">Addon Tooltips</div>
                    <div class="admin-stat-card__value">{{ number_format($contentCounts['addon_tooltips'] ?? 0) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-7">
            <section class="card app-card admin-section-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 class="h5 mb-1">Recent Changes</h2>
                            <p class="text-secondary mb-0">Latest managed content updates across the module.</p>
                        </div>
                    </div>

                    @if($recentChanges->isEmpty())
                        @include('admin.partials.empty-state', [
                            'title' => 'No recent content changes',
                            'copy' => 'Updates to pages, FAQs, featured boosters, and addon tooltips will surface here once editors start publishing.',
                        ])
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Type</th>
                                        <th>Updated</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentChanges as $item)
                                        <tr>
                                            <td class="fw-semibold">{{ $item['label'] }}</td>
                                            <td>{{ $item['type'] }}</td>
                                            <td>{{ $item['updated_at']?->format('M j, Y g:i A') ?? '-' }}</td>
                                            <td class="text-end">
                                                <a class="btn btn-outline-light btn-sm" href="{{ $item['route'] }}">Open</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>
        </div>

        <div class="col-xl-5">
            <section class="card app-card admin-section-card mb-3">
                <div class="card-body">
                    <h2 class="h5 mb-3">Publishing Warnings</h2>
                    @if($publishingWarnings->isEmpty())
                        <div class="alert alert-success mb-0">No obvious publishing gaps were detected in managed content.</div>
                    @else
                        @foreach($publishingWarnings as $warning)
                            <div class="alert alert-warning mb-2">{{ $warning }}</div>
                        @endforeach
                    @endif
                </div>
            </section>

            <section class="card app-card admin-section-card">
                <div class="card-body">
                    <h2 class="h5 mb-3">Module Shortcuts</h2>
                    <div class="d-grid gap-2">
                        <a class="btn btn-outline-light text-start" href="{{ route('admin-pages.index') }}">Manage Pages</a>
                        <a class="btn btn-outline-light text-start" href="{{ route('admin-content.faqs.index') }}">Manage FAQs</a>
                        <a class="btn btn-outline-light text-start" href="{{ route('admin-content.featured-boosters.index') }}">Manage Featured Boosters</a>
                        <a class="btn btn-outline-light text-start" href="{{ route('admin-content.addon-tooltips.index') }}">Manage Addon Tooltips</a>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>
@endsection
