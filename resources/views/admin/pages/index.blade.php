@extends('layouts.admin')

@section('title', 'GGWP Boost | Pages')

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide">
    <div class="ggwp-page-header mb-3">
        <div class="ggwp-page-header__copy">
            <h1 class="mb-0">Pages</h1>
            <p class="text-secondary mb-0">Edit page copy and SEO fields in structured forms without touching raw JSON or route logic.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 ggwp-page-actions">
            <a class="btn btn-outline-light" href="{{ route('admin-content.index') }}">Content Hub</a>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif

    <div class="card app-card ggwp-panel-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 ggwp-data-table">
                    <thead>
                        <tr>
                            <th>Page</th>
                            <th>Route</th>
                            <th>Sitemap</th>
                            <th>Updated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pages as $page)
                            <tr>
                                <td class="fw-semibold">{{ $page['label'] }}</td>
                                <td><code>{{ $page['path'] }}</code></td>
                                <td>
                                    <span class="badge {{ $page['include_in_sitemap'] ? 'text-bg-success' : 'text-bg-secondary' }}">
                                        {{ $page['include_in_sitemap'] ? 'Included' : 'Excluded' }}
                                    </span>
                                </td>
                                <td>{{ $page['updated_at']?->format('M j, Y H:i') ?? '-' }}</td>
                                <td class="text-end">
                                    <a class="btn btn-outline-light btn-sm" href="{{ route('admin-pages.edit', ['pageKey' => $page['key']]) }}">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
@endsection
