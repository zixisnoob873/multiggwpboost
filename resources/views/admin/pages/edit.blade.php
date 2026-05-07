@extends('layouts.admin')

@section('title', 'GGWP Boost | Edit Page')

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide">
    <div class="ggwp-page-header mb-3">
        <div class="ggwp-page-header__copy">
            <h1 class="mb-0">Edit {{ $pageDefinition['label'] }}</h1>
            <p class="text-secondary mb-0">Update human-readable page content and SEO while keeping the public route stable at <code>{{ $pagePath }}</code>.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 ggwp-page-actions">
            <a class="btn btn-outline-light" href="{{ route('admin-pages.index') }}">Back to Pages</a>
            <a class="btn btn-outline-secondary" href="{{ $pagePath }}" target="_blank" rel="noopener noreferrer">Open Public URL</a>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif

    <form action="{{ route('admin-pages.update', ['pageKey' => $pageDefinition['key']]) }}" method="POST" data-validate-form novalidate>
        @csrf
        @method('PATCH')
        @include('admin.pages._form')
    </form>
</main>
@endsection
