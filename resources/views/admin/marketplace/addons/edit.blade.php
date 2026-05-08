@extends('layouts.admin')

@section('title', 'GGWP Boost | Edit Marketplace Addon')

@section('admin_content')
<main class="ggwp-page-shell">
    @include('admin.partials.page-header', [
        'title' => 'Edit Addon',
        'subtitle' => 'Update add-on copy, pricing, and service assignments.',
        'actions' => [
            ['label' => 'Back to Addons', 'href' => route('admin-marketplace.addons.index'), 'class' => 'btn btn-outline-light btn-sm'],
        ],
    ])

    <section class="card app-card admin-section-card">
        <div class="card-body">
            @include('admin.marketplace.addons._form', [
                'addon' => $addon,
                'action' => route('admin-marketplace.addons.update', $addon),
                'method' => 'PATCH',
                'submitLabel' => 'Save Addon',
            ])
        </div>
    </section>
</main>
@endsection

