@extends('layouts.admin')

@section('title', 'GGWP Boost | Edit Blog Article')

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense">
    <div class="admin-page-header">
        <div class="admin-page-header__copy">
            <h1 class="admin-page-title">Edit Blog Article</h1>
            <div class="admin-page-meta">
                <span class="admin-page-meta__item">{{ $blogArticle->publicationStateLabel() }}</span>
                <span class="admin-page-meta__item">{{ $blogArticle->include_in_sitemap ? 'In Sitemap' : 'Out of Sitemap' }}</span>
                <span class="admin-page-meta__item">Updated {{ $blogArticle->updated_at?->format('M j, Y H:i') ?? '-' }}</span>
            </div>
        </div>

        <div class="admin-page-actions">
            <a class="btn btn-outline-light btn-sm" href="{{ route('admin-blog-articles.index') }}">Back to Articles</a>
            @if($blogArticle->isPublished())
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('blog.show', ['slug' => $blogArticle->slug]) }}" target="_blank" rel="noopener noreferrer">Open Public URL</a>
            @endif
            @if($blogArticle->status === 'published')
                <form method="POST" action="{{ route('admin-blog-articles.unpublish', $blogArticle) }}" data-loading-form data-confirm-submit="Move this article back to draft?">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-outline-warning btn-sm" type="submit" data-busy-label="Updating...">Unpublish</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin-blog-articles.publish', $blogArticle) }}" data-loading-form data-confirm-submit="Publish this article now?">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-outline-success btn-sm" type="submit" data-busy-label="Publishing...">Publish</button>
                </form>
            @endif
        </div>
    </div>

    <form action="{{ route('admin-blog-articles.update', $blogArticle) }}" method="POST" data-loading-form data-dirty-form data-validate-form novalidate>
        @csrf
        @method('PATCH')
        @include('admin.blog-articles._form', [
            'submitLabel' => 'Save Changes',
        ])
    </form>
</main>
@endsection
