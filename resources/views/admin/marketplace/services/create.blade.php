@extends('layouts.admin')

@section('title', 'GGWP Boost | Add Marketplace Service')

@section('admin_content')
<main class="ggwp-page-shell">
    @include('admin.partials.page-header', [
        'title' => 'Add Service',
        'subtitle' => 'Create a service page and optional base price.',
        'actions' => [
            ['label' => 'Back to Services', 'href' => route('admin-marketplace.services.index'), 'class' => 'btn btn-outline-light btn-sm'],
        ],
    ])

    <section class="card app-card admin-section-card">
        <div class="card-body">
            @include('admin.marketplace.services._form', [
                'action' => route('admin-marketplace.services.store'),
                'submitLabel' => 'Create Service',
            ])
        </div>
    </section>
</main>
@endsection

