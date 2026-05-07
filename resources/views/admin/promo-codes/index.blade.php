@extends('layouts.admin')

@section('title', 'GGWP Boost | Promo Codes')


@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense">
    @include('admin.partials.page-header', [
        'title' => 'Promo Codes',
        'actions' => [
            ['label' => 'Dashboard', 'href' => route('admin-dashboard'), 'class' => 'btn btn-outline-light btn-sm'],
        ],
    ])

    <div class="row g-3 align-items-start">
        <div class="col-xl-4">
            <section class="card app-card admin-section-card">
                <div class="card-body">
                    <h2 class="h6 mb-3">Create Promo Code</h2>
                    @include('admin.promo-codes._form')
                </div>
            </section>
        </div>

        <div class="col-xl-8">
            <section class="card app-card admin-section-card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div class="d-flex flex-wrap gap-2">
                            <span class="admin-chip">{{ $promoCodes->total() }} total</span>
                            @if($promoCodeSearch !== '')
                                <span class="admin-chip">Filtered: {{ $promoCodeSearch }}</span>
                            @endif
                        </div>

                        <form method="GET" action="{{ route('admin-promo-codes.index') }}" class="d-flex flex-wrap align-items-center gap-2">
                            <label class="visually-hidden" for="promoCodeSearch">Search promo codes</label>
                            <input
                                id="promoCodeSearch"
                                name="search"
                                type="search"
                                value="{{ $promoCodeSearch }}"
                                class="form-control form-control-sm ggwp-toolbar-search-input"
                                placeholder="Search code or type"
                            >
                            <button class="btn btn-outline-light btn-sm" type="submit">Search</button>
                            @if($promoCodeSearch !== '')
                                <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin-promo-codes.index') }}">Reset</a>
                            @endif
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0 ggwp-data-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Type</th>
                                    <th>Value</th>
                                    <th>Status</th>
                                    <th>Usage</th>
                                    <th>Window</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($promoCodes as $promoCode)
                                    <tr>
                                        <td class="fw-semibold">{{ $promoCode->code }}</td>
                                        <td>{{ $promoCode->typeLabel() }}</td>
                                        <td>{{ $promoCode->displayValue() }}</td>
                                        <td>
                                            <span class="badge {{ $promoCode->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                                {{ $promoCode->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td>{{ $promoCode->used_count }} / {{ $promoCode->max_uses ?? 'Unlimited' }}</td>
                                        <td class="small">
                                            @if($promoCode->start_at === null && $promoCode->end_at === null)
                                                Unlimited
                                            @else
                                                <div>{{ $promoCode->start_at?->format('M j, Y H:i') ?? 'Immediate' }}</div>
                                                <div class="text-secondary">{{ $promoCode->end_at?->format('M j, Y H:i') ?? 'No expiry' }}</div>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <div class="ggwp-table-actions justify-content-end">
                                                <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin-promo-codes.details', $promoCode) }}">Details</a>
                                                <a class="btn btn-outline-light btn-sm" href="{{ route('admin-promo-codes.edit', $promoCode) }}">Edit</a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-secondary py-4">No promo codes created yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $promoCodes->withQueryString()->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>
@endsection
