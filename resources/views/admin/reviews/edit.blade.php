@extends('layouts.admin')

@section('title', 'GGWP Boost | Edit Review')


@section('admin_content')
<main class="ggwp-page-shell">
    <div class="ggwp-page-header mb-2">
        <div class="ggwp-page-header__copy">
            <h1 class="mb-0">Edit Review</h1>
            <p class="text-secondary mb-0">Update the public review copy and ordering.</p>
        </div>
        <div class="ggwp-page-actions">
            <a class="btn btn-outline-light" href="{{ route('admin-reviews.index') }}">Back to Reviews</a>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <section class="card app-card ggwp-panel-card">
        <div class="card-body">
            @include('admin.reviews._form', [
                'review' => $review,
                'action' => route('admin-reviews.update', ['review' => $review]),
                'method' => 'PATCH',
                'submitLabel' => 'Update Review',
            ])
        </div>
    </section>
</main>
@endsection
