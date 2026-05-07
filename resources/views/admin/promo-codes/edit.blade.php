@extends('layouts.admin')

@section('title', 'GGWP Boost | Edit Promo Code')


@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense">
    <div class="admin-page-header">
        <div class="admin-page-header__copy">
            <h1 class="admin-page-title">Edit Promo Code</h1>
            <div class="admin-page-meta">
                <span class="admin-page-meta__item">{{ $promoCode->code }}</span>
                <span class="admin-page-meta__item">{{ $promoCode->typeLabel() }}</span>
                <span class="admin-page-meta__item">{{ $promoCode->displayValue() }}</span>
                <span class="admin-page-meta__item">{{ $promoCode->is_active ? 'Active' : 'Inactive' }}</span>
            </div>
        </div>

        <div class="admin-page-actions">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin-promo-codes.details', $promoCode) }}">Details</a>
            @if($promoCode->is_active)
                <form method="POST" action="{{ route('admin-promo-codes.deactivate', $promoCode) }}" data-loading-form data-confirm-submit="Deactivate {{ $promoCode->code }}?">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-outline-warning btn-sm" type="submit" data-busy-label="Deactivating...">Deactivate</button>
                </form>
            @endif
            @if($promoCode->canBeDeleted())
                <form method="POST" action="{{ route('admin-promo-codes.destroy', $promoCode) }}" data-loading-form data-confirm-submit="Delete {{ $promoCode->code }} permanently?">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm" type="submit" data-busy-label="Deleting...">Delete</button>
                </form>
            @endif
            <a class="btn btn-outline-light btn-sm" href="{{ route('admin-promo-codes.index') }}">Back to Promo Codes</a>
        </div>
    </div>

    <section class="card app-card admin-section-card">
        <div class="card-body">
            @include('admin.promo-codes._form', ['promoCode' => $promoCode])
        </div>
    </section>
</main>
@endsection
