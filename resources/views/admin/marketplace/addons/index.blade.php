@extends('layouts.admin')

@section('title', 'GGWP Boost | Marketplace Addons')

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense">
    @include('admin.partials.page-header', [
        'title' => 'Marketplace Addons',
        'subtitle' => 'Manage optional order add-ons and assign them to services.',
        'actions' => [
            ['label' => 'Add Addon', 'href' => route('admin-marketplace.addons.create'), 'class' => 'btn btn-danger btn-sm'],
            ['label' => 'Games', 'href' => route('admin-marketplace.games.index'), 'class' => 'btn btn-outline-light btn-sm'],
            ['label' => 'Services', 'href' => route('admin-marketplace.services.index'), 'class' => 'btn btn-outline-light btn-sm'],
        ],
    ])

    <section class="card app-card admin-section-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 ggwp-data-table">
                    <thead>
                        <tr>
                            <th>Addon</th>
                            <th>Game</th>
                            <th>Slug</th>
                            <th>Status</th>
                            <th>Pricing</th>
                            <th>Services</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($addons as $addon)
                            <tr>
                                <td class="fw-semibold">{{ $addon->label }}</td>
                                <td>{{ $addon->game?->name ?? 'Missing game' }}</td>
                                <td><code>{{ $addon->slug }}</code></td>
                                <td>{{ ucfirst($addon->status) }}</td>
                                <td>{{ $addon->pricing_type }} {{ (float) $addon->pricing_value > 0 ? number_format((float) $addon->pricing_value, 2) : '' }}</td>
                                <td>{{ $addon->services_count }}</td>
                                <td class="text-end">
                                    <div class="ggwp-table-actions justify-content-end">
                                        <a class="btn btn-outline-light btn-sm" href="{{ route('admin-marketplace.addons.edit', $addon) }}">Edit</a>
                                        @if($addon->status === \App\Models\GameAddon::STATUS_ARCHIVED)
                                            <form method="POST" action="{{ route('admin-marketplace.addons.publish', $addon) }}" data-loading-form>
                                                @csrf
                                                @method('PATCH')
                                                <button class="btn btn-outline-light btn-sm" type="submit">Publish</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin-marketplace.addons.archive', $addon) }}" data-confirm-submit="Archive this addon?" data-loading-form>
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
                                <td colspan="7" class="text-center text-secondary py-4">No marketplace addons yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $addons->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </section>
</main>
@endsection

