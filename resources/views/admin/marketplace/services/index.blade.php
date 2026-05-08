@extends('layouts.admin')

@section('title', 'GGWP Boost | Marketplace Services')

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense">
    @include('admin.partials.page-header', [
        'title' => 'Marketplace Services',
        'subtitle' => 'Manage service pages, homepage highlights, base prices, and addon assignments.',
        'actions' => [
            ['label' => 'Add Service', 'href' => route('admin-marketplace.services.create'), 'class' => 'btn btn-danger btn-sm'],
            ['label' => 'Games', 'href' => route('admin-marketplace.games.index'), 'class' => 'btn btn-outline-light btn-sm'],
            ['label' => 'Addons', 'href' => route('admin-marketplace.addons.index'), 'class' => 'btn btn-outline-light btn-sm'],
        ],
    ])

    <section class="card app-card admin-section-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 ggwp-data-table">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Game</th>
                            <th>Slug</th>
                            <th>Status</th>
                            <th>Highlight</th>
                            <th>Base</th>
                            <th>Addons</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($services as $service)
                            @php
                                $baseRule = $service->pricingRules->first(fn ($rule) => $rule->scope === \App\Models\ServicePricingRule::SCOPE_BASE);
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $service->name }}</div>
                                    <div class="small text-secondary">{{ $service->kind }}</div>
                                </td>
                                <td>{{ $service->game?->name ?? 'Missing game' }}</td>
                                <td><code>{{ $service->slug }}</code></td>
                                <td>{{ ucfirst($service->status) }}</td>
                                <td>{{ data_get($service->metadata, 'homepage_featured') ? 'Yes' : 'No' }}</td>
                                <td>{{ $baseRule?->amount !== null ? '$'.number_format((float) $baseRule->amount, 2) : 'Fallback' }}</td>
                                <td>{{ $service->addons_count }}</td>
                                <td class="text-end">
                                    <div class="ggwp-table-actions justify-content-end">
                                        @if($service->status === \App\Models\GameService::STATUS_PUBLISHED && $service->game)
                                            <a class="btn btn-outline-light btn-sm" href="{{ route('game.services.show', ['game' => $service->game->slug, 'service' => $service->slug]) }}" target="_blank" rel="noopener">View</a>
                                        @endif
                                        <a class="btn btn-outline-light btn-sm" href="{{ route('admin-marketplace.services.edit', $service) }}">Edit</a>
                                        @if($service->status === \App\Models\GameService::STATUS_ARCHIVED)
                                            <form method="POST" action="{{ route('admin-marketplace.services.publish', $service) }}" data-loading-form>
                                                @csrf
                                                @method('PATCH')
                                                <button class="btn btn-outline-light btn-sm" type="submit">Publish</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin-marketplace.services.archive', $service) }}" data-confirm-submit="Archive this service?" data-loading-form>
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
                                <td colspan="8" class="text-center text-secondary py-4">No marketplace services yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $services->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </section>
</main>
@endsection

