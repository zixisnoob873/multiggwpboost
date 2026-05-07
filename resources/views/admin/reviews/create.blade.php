@extends('layouts.admin')

@section('title', 'GGWP Boost | Add Review')


@section('admin_content')
<main class="ggwp-page-shell">
    <div class="ggwp-page-header mb-2">
        <div class="ggwp-page-header__copy">
            <h1 class="mb-0">Add Review</h1>
            <p class="text-secondary mb-0">Create a new public-facing customer review.</p>
        </div>
        <div class="ggwp-page-actions">
            <a class="btn btn-outline-light" href="{{ route('admin-reviews.index') }}">Back to Reviews</a>
        </div>
    </div>

    <section class="card app-card ggwp-panel-card">
        <div class="card-body">
            @include('admin.reviews._form')
        </div>
    </section>
</main>
@endsection
