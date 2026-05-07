@extends('layouts.admin')

@section('title', 'GGWP Boost | Featured Boosters')

@section('admin_content')
<main
    class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense"
    @if(old('modal_id'))
        data-open-admin-modal="{{ old('modal_id') }}"
    @endif
>
    @include('admin.partials.page-header', [
        'title' => 'Featured Boosters',
        'actions' => [
            ['label' => 'Content Home', 'href' => route('admin-content.index'), 'class' => 'btn btn-outline-light btn-sm'],
            ['label' => 'Addon Tooltips', 'href' => route('admin-content.addon-tooltips.index'), 'class' => 'btn btn-outline-light btn-sm'],
            ['label' => 'Add Featured Booster', 'type' => 'button', 'toggle' => 'modal', 'target' => '#featuredBoosterCreateModal', 'class' => 'btn btn-danger btn-sm'],
        ],
    ])

    <section class="card app-card admin-section-card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="h6 mb-0">Featured Booster Library</h2>
                <span class="admin-chip">{{ $featuredBoosters->total() }} total</span>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 ggwp-data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Region</th>
                            <th>Platform</th>
                            <th>Success</th>
                            <th>Active Orders</th>
                            <th>Verified</th>
                            <th>Order</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($featuredBoosters as $booster)
                            <tr>
                                <td class="fw-semibold">{{ $booster->name }}</td>
                                <td>{{ $booster->region }}</td>
                                <td>{{ $booster->platform }}</td>
                                <td>{{ number_format((float) $booster->success_rate, 1) }}%</td>
                                <td>{{ $booster->active_orders }}</td>
                                <td>{{ $booster->is_verified ? 'Yes' : 'No' }}</td>
                                <td>{{ $booster->sort_order }}</td>
                                <td class="text-end">
                                    <div class="ggwp-table-actions justify-content-end">
                                        <button
                                            class="btn btn-outline-light btn-sm"
                                            type="button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#featuredBoosterEditModal{{ $booster->id }}"
                                        >
                                            Edit
                                        </button>
                                        <form
                                            method="POST"
                                            action="{{ route('admin-featured-boosters.destroy', $booster) }}"
                                            data-loading-form
                                            data-confirm-submit="Delete this featured booster entry?"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm" type="submit" data-busy-label="Deleting...">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-secondary py-4">No featured boosters added yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $featuredBoosters->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </section>

    <div class="modal fade" id="featuredBoosterCreateModal" tabindex="-1" aria-labelledby="featuredBoosterCreateModalLabel" aria-hidden="true" data-admin-modal>
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5 mb-0" id="featuredBoosterCreateModalLabel">Add Featured Booster</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @include('admin.content.partials.featured-booster-form', [
                        'action' => route('admin-featured-boosters.store'),
                        'booster' => null,
                        'submitLabel' => 'Create Entry',
                        'submitClass' => 'btn-danger',
                        'formContext' => 'featured-booster-create',
                        'modalId' => 'featuredBoosterCreateModal',
                    ])
                </div>
            </div>
        </div>
    </div>

    @foreach($featuredBoosters as $booster)
        <div class="modal fade" id="featuredBoosterEditModal{{ $booster->id }}" tabindex="-1" aria-labelledby="featuredBoosterEditModal{{ $booster->id }}Label" aria-hidden="true" data-admin-modal>
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5 mb-0" id="featuredBoosterEditModal{{ $booster->id }}Label">Edit Featured Booster</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @include('admin.content.partials.featured-booster-form', [
                            'action' => route('admin-featured-boosters.update', $booster),
                            'method' => 'PATCH',
                            'booster' => $booster,
                            'submitLabel' => 'Save Entry',
                            'submitClass' => 'btn-danger',
                            'formContext' => 'featured-booster-'.$booster->id,
                            'modalId' => 'featuredBoosterEditModal'.$booster->id,
                        ])
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</main>
@endsection
