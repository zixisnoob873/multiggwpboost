@extends('layouts.admin')

@section('title', 'GGWP Boost | Edit Customer')



@php
    $displayName = $customer->fullIdentity('Customer');
    $nickname = $customer->publicIdentity('Customer');
@endphp

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide">
    @include('admin.partials.page-header', [
        'title' => 'Edit Customer',
        'subtitle' => "Update account details for {$displayName} ({$nickname}).",
        'meta' => [
            'Status: '.ucfirst($customer->account_status ?? 'active'),
            'Orders: '.number_format($customer->orders_count ?? 0),
        ],
        'actions' => [
            ['label' => 'View Profile', 'href' => route('admin-customers.show', $customer)],
            ['label' => 'Back to Customers', 'href' => route('admin-customers.index')],
        ],
    ])

    @if(session('status'))
        <div class="alert alert-success mb-3" role="alert">{{ session('status') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-xl-8">
            <section class="card app-card admin-section-card h-100">
                <div class="card-body">
                    <form action="{{ route('admin-customers.update', $customer) }}" method="POST" class="row g-3" data-loading-form data-dirty-form>
                        @csrf
                        @method('PATCH')
                        @include('admin.customers.partials.form-fields', [
                            'customer' => $customer,
                            'submitLabel' => 'Save Changes',
                            'passwordLabel' => 'New password',
                            'passwordRequired' => false,
                            'passwordHelp' => 'Leave blank to keep the current password.',
                            'cancelUrl' => route('admin-customers.show', $customer),
                        ])
                    </form>
                </div>
            </section>
        </div>

        <div class="col-xl-4">
            @include('admin.customers.partials.sidebar', ['customer' => $customer])
        </div>
    </div>
</main>
@endsection
