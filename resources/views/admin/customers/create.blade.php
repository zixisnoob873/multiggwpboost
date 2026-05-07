@extends('layouts.admin')

@section('title', 'GGWP Boost | Add Customer')



@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide">
    @include('admin.partials.page-header', [
        'title' => 'Add Customer',
        'subtitle' => 'Create a new customer account with the same validated profile rules used everywhere else in the people module.',
        'actions' => [
            ['label' => 'Back to Customers', 'href' => route('admin-customers.index')],
            ['label' => 'Dashboard', 'href' => route('admin-dashboard')],
        ],
    ])

    <section class="card app-card admin-section-card">
        <div class="card-body">
            <form action="{{ route('admin-customers.store') }}" method="POST" class="row g-3" data-loading-form data-dirty-form>
                @csrf
                @include('admin.customers.partials.form-fields', [
                    'submitLabel' => 'Create Customer',
                    'passwordLabel' => 'Password',
                    'passwordRequired' => true,
                    'passwordHelp' => null,
                    'cancelUrl' => route('admin-customers.index'),
                ])
            </form>
        </div>
    </section>
</main>
@endsection
