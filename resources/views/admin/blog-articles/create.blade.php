@extends('layouts.admin')

@section('title', 'GGWP Boost | Create Blog Article')

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense">
    @include('admin.partials.page-header', [
        'title' => 'Create Blog Article',
        'actions' => [
            ['label' => 'Back to Articles', 'href' => route('admin-blog-articles.index'), 'class' => 'btn btn-outline-light btn-sm'],
        ],
    ])

    <form action="{{ route('admin-blog-articles.store') }}" method="POST" data-loading-form data-dirty-form data-validate-form novalidate>
        @csrf
        @include('admin.blog-articles._form', [
            'submitLabel' => 'Create Article',
        ])
    </form>
</main>
@endsection
