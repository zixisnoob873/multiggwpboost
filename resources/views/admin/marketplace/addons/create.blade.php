@extends('layouts.admin')

@section('title', 'GGWP Boost | Add Marketplace Addon')

@section('admin_content')
<main class="ggwp-page-shell">
    @include('admin.partials.page-header', [
        'title' => 'Add Addon',
        'subtitle' => 'Create an add-on and optionally attach it to services.',
        'actions' => [
            ['label' => 'Back to Addons', 'href' => route('admin-marketplace.addons.index'), 'class' => 'btn btn-outline-light btn-sm'],
        ],
    ])

    <section class="card app-card admin-section-card">
        <div class="card-body">
            @include('admin.marketplace.addons._form', [
                'action' => route('admin-marketplace.addons.store'),
                'submitLabel' => 'Create Addon',
            ])
        </div>
    </section>
</main>
@endsection

