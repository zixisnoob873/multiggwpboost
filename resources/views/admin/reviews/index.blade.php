@extends('layouts.admin')

@section('title', 'GGWP Boost | Reviews')


@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide">
    <div class="ggwp-page-header mb-2">
        <div class="ggwp-page-header__copy">
            <h1 class="mb-0">Reviews</h1>
            <p class="text-secondary mb-0">Manage the customer reviews shown on the public reviews page.</p>
        </div>
        <div class="ggwp-page-actions">
            <a class="btn btn-outline-light" href="{{ route('admin-dashboard') }}">Admin Dashboard</a>
            <a class="btn btn-outline-light" href="{{ route('admin-content.index') }}">Content</a>
            <a class="btn btn-danger" href="{{ route('admin-reviews.create') }}">Add Review</a>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <section class="card app-card ggwp-panel-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 ggwp-data-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Review</th>
                            <th>Sort</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reviews as $review)
                            <tr>
                                <td class="fw-semibold">{{ $review->author_name }}</td>
                                <td>{{ $review->service }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($review->quote, 120) }}</td>
                                <td>{{ $review->sort_order }}</td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2">
                                        <a class="btn btn-outline-light btn-sm" href="{{ route('admin-reviews.edit', ['review' => $review]) }}">Edit</a>
                                        <form method="POST" action="{{ route('admin-reviews.destroy', ['review' => $review]) }}" data-confirm-submit="Delete this review?">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-secondary py-4">No reviews have been added yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $reviews->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </section>
</main>
@endsection
