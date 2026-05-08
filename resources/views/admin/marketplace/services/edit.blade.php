@extends('layouts.admin')

@section('title', 'GGWP Boost | Edit Marketplace Service')

@section('admin_content')
<main class="ggwp-page-shell">
    @include('admin.partials.page-header', [
        'title' => 'Edit Service',
        'subtitle' => 'Update catalog, pricing, homepage, SEO, and addon assignment.',
        'actions' => [
            ['label' => 'Back to Services', 'href' => route('admin-marketplace.services.index'), 'class' => 'btn btn-outline-light btn-sm'],
            ['label' => 'View Public Page', 'href' => $service->game ? route('game.services.show', ['game' => $service->game->slug, 'service' => $service->slug]) : route('admin-marketplace.services.index'), 'class' => 'btn btn-outline-light btn-sm', 'target' => '_blank', 'rel' => 'noopener'],
        ],
    ])

    <section class="card app-card admin-section-card">
        <div class="card-body">
            @include('admin.marketplace.services._form', [
                'service' => $service,
                'action' => route('admin-marketplace.services.update', $service),
                'method' => 'PATCH',
                'submitLabel' => 'Save Service',
            ])
        </div>
    </section>
</main>
@endsection

