@extends('layouts.admin')

@section('title', 'GGWP Boost | Blog Articles')

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense">
    @include('admin.partials.page-header', [
        'title' => 'Blog Articles',
        'actions' => [
            ['label' => 'Content Hub', 'href' => route('admin-content.index'), 'class' => 'btn btn-outline-light btn-sm'],
            ['label' => 'Create Article', 'href' => route('admin-blog-articles.create'), 'class' => 'btn btn-danger btn-sm'],
        ],
    ])

    <div class="row g-3">
        <div class="col-md-4">
            <section class="card app-card admin-section-card h-100">
                <div class="card-body">
                    <div class="text-secondary small mb-1">Total</div>
                    <div class="h4 mb-0">{{ $blogArticleStats['total'] ?? 0 }}</div>
                </div>
            </section>
        </div>
        <div class="col-md-4">
            <section class="card app-card admin-section-card h-100">
                <div class="card-body">
                    <div class="text-secondary small mb-1">Published</div>
                    <div class="h4 mb-0">{{ $blogArticleStats['published'] ?? 0 }}</div>
                </div>
            </section>
        </div>
        <div class="col-md-4">
            <section class="card app-card admin-section-card h-100">
                <div class="card-body">
                    <div class="text-secondary small mb-1">Drafts</div>
                    <div class="h4 mb-0">{{ $blogArticleStats['drafts'] ?? 0 }}</div>
                </div>
            </section>
        </div>
    </div>

    <section class="card app-card admin-section-card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div class="d-flex flex-wrap gap-2">
                    <span class="admin-chip">{{ $blogArticles->total() }} results</span>
                    @if($blogArticleStatus !== '')
                        <span class="admin-chip">Status: {{ ucfirst($blogArticleStatus) }}</span>
                    @endif
                    @if($blogArticleSearch !== '')
                        <span class="admin-chip">Filtered: {{ $blogArticleSearch }}</span>
                    @endif
                </div>

                <form method="GET" action="{{ route('admin-blog-articles.index') }}" class="d-flex flex-wrap align-items-center gap-2">
                    <select name="status" class="form-select form-select-sm ggwp-toolbar-search-input">
                        <option value="">All statuses</option>
                        <option value="draft" @selected($blogArticleStatus === 'draft')>Draft</option>
                        <option value="published" @selected($blogArticleStatus === 'published')>Published</option>
                    </select>
                    <input
                        name="search"
                        type="search"
                        value="{{ $blogArticleSearch }}"
                        class="form-control form-control-sm ggwp-toolbar-search-input"
                        placeholder="Search title or slug"
                    >
                    <button class="btn btn-outline-light btn-sm" type="submit">Filter</button>
                    @if($blogArticleSearch !== '' || $blogArticleStatus !== '')
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin-blog-articles.index') }}">Reset</a>
                    @endif
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 ggwp-data-table">
                    <thead>
                        <tr>
                            <th>Article</th>
                            <th>Status</th>
                            <th>Published</th>
                            <th>Sitemap</th>
                            <th>Updated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($blogArticles as $blogArticle)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $blogArticle->title }}</div>
                                    <div class="small text-secondary">/blog/{{ $blogArticle->slug }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $blogArticle->publicationStateBadgeClass() }}">{{ $blogArticle->publicationStateLabel() }}</span>
                                </td>
                                <td>{{ $blogArticle->published_at?->format('M j, Y H:i') ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $blogArticle->include_in_sitemap ? 'text-bg-success' : 'text-bg-secondary' }}">
                                        {{ $blogArticle->include_in_sitemap ? 'Included' : 'Excluded' }}
                                    </span>
                                </td>
                                <td>{{ $blogArticle->updated_at?->format('M j, Y H:i') ?? '-' }}</td>
                                <td class="text-end">
                                    <div class="ggwp-table-actions justify-content-end">
                                        <a class="btn btn-outline-light btn-sm" href="{{ route('admin-blog-articles.edit', $blogArticle) }}">Edit</a>
                                        @if($blogArticle->isPublished())
                                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('blog.show', ['slug' => $blogArticle->slug]) }}" target="_blank" rel="noopener noreferrer">View</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-secondary py-4">No blog articles found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $blogArticles->withQueryString()->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </section>
</main>
@endsection
