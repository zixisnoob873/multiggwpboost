@extends('layouts.admin')

@section('title', 'GGWP Boost | Marketplace Games')

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense">
    @include('admin.partials.page-header', [
        'title' => 'Marketplace Games',
        'subtitle' => 'Create, publish, feature, and archive game landing pages.',
        'actions' => [
            ['label' => 'Add Game', 'href' => route('admin-marketplace.games.create'), 'class' => 'btn btn-danger btn-sm'],
            ['label' => 'Services', 'href' => route('admin-marketplace.services.index'), 'class' => 'btn btn-outline-light btn-sm'],
            ['label' => 'Addons', 'href' => route('admin-marketplace.addons.index'), 'class' => 'btn btn-outline-light btn-sm'],
        ],
    ])

    <section class="card app-card admin-section-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 ggwp-data-table">
                    <thead>
                        <tr>
                            <th>Game</th>
                            <th>Slug</th>
                            <th>Status</th>
                            <th>Featured</th>
                            <th>Services</th>
                            <th>Addons</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($games as $game)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $game->name }}</div>
                                    <div class="small text-secondary">{{ $game->category?->name ?? 'No category' }}</div>
                                </td>
                                <td><code>{{ $game->slug }}</code></td>
                                <td>{{ ucfirst($game->status) }}</td>
                                <td>{{ data_get($game->metadata, 'featured') ? 'Yes' : 'No' }}</td>
                                <td>{{ $game->services_count }}</td>
                                <td>{{ $game->addons_count }}</td>
                                <td class="text-end">
                                    <div class="ggwp-table-actions justify-content-end">
                                        @if($game->status === \App\Models\Game::STATUS_PUBLISHED)
                                            <a class="btn btn-outline-light btn-sm" href="{{ route('game.show', ['game' => $game->slug]) }}" target="_blank" rel="noopener">View</a>
                                        @endif
                                        <a class="btn btn-outline-light btn-sm" href="{{ route('admin-marketplace.games.edit', $game) }}">Edit</a>
                                        @if($game->status === \App\Models\Game::STATUS_ARCHIVED)
                                            <form method="POST" action="{{ route('admin-marketplace.games.publish', $game) }}" data-loading-form>
                                                @csrf
                                                @method('PATCH')
                                                <button class="btn btn-outline-light btn-sm" type="submit">Publish</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin-marketplace.games.archive', $game) }}" data-confirm-submit="Archive this game?" data-loading-form>
                                                @csrf
                                                @method('PATCH')
                                                <button class="btn btn-outline-danger btn-sm" type="submit">Archive</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-secondary py-4">No marketplace games yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $games->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </section>
</main>
@endsection

