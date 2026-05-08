@extends('layouts.admin')

@section('title', 'GGWP Boost | Edit Marketplace Game')

@section('admin_content')
<main class="ggwp-page-shell">
    @include('admin.partials.page-header', [
        'title' => 'Edit Game',
        'subtitle' => 'Update catalog, homepage, and SEO details.',
        'actions' => [
            ['label' => 'Back to Games', 'href' => route('admin-marketplace.games.index'), 'class' => 'btn btn-outline-light btn-sm'],
            ['label' => 'View Public Page', 'href' => route('game.show', ['game' => $game->slug]), 'class' => 'btn btn-outline-light btn-sm', 'target' => '_blank', 'rel' => 'noopener'],
        ],
    ])

    <section class="card app-card admin-section-card">
        <div class="card-body">
            @include('admin.marketplace.games._form', [
                'game' => $game,
                'action' => route('admin-marketplace.games.update', $game),
                'method' => 'PATCH',
                'submitLabel' => 'Save Game',
            ])
        </div>
    </section>
</main>
@endsection

