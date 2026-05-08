@extends('layouts.admin')

@section('title', 'GGWP Boost | Add Marketplace Game')

@section('admin_content')
<main class="ggwp-page-shell">
    @include('admin.partials.page-header', [
        'title' => 'Add Game',
        'subtitle' => 'Create a game landing page without editing code.',
        'actions' => [
            ['label' => 'Back to Games', 'href' => route('admin-marketplace.games.index'), 'class' => 'btn btn-outline-light btn-sm'],
        ],
    ])

    <section class="card app-card admin-section-card">
        <div class="card-body">
            @include('admin.marketplace.games._form', [
                'action' => route('admin-marketplace.games.store'),
                'submitLabel' => 'Create Game',
            ])
        </div>
    </section>
</main>
@endsection

