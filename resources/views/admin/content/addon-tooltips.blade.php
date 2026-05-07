@extends('layouts.admin')

@section('title', 'GGWP Boost | Addon Tooltips')

@section('admin_content')
<main
    class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense"
    @if(old('modal_id'))
        data-open-admin-modal="{{ old('modal_id') }}"
    @endif
>
    @include('admin.partials.page-header', [
        'title' => 'Addon Tooltips',
        'actions' => [
            ['label' => 'Content Home', 'href' => route('admin-content.index'), 'class' => 'btn btn-outline-light btn-sm'],
            ['label' => 'Featured Boosters', 'href' => route('admin-content.featured-boosters.index'), 'class' => 'btn btn-outline-light btn-sm'],
        ],
    ])

    <section class="card app-card admin-section-card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="h6 mb-0">Addon Library</h2>
                <span class="admin-chip">{{ $addonSettings->count() }} addons</span>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 ggwp-data-table">
                    <thead>
                        <tr>
                            <th>Addon</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($addonSettings as $addon)
                            <tr>
                                <td class="fw-semibold">{{ $addon['label'] }}</td>
                                <td class="text-end">
                                    <button
                                        class="btn btn-outline-light btn-sm"
                                        type="button"
                                        data-bs-toggle="modal"
                                        data-bs-target="#addonTooltipModal{{ $addon['slug'] }}"
                                    >
                                        Edit
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    @foreach($addonSettings as $addon)
        <div class="modal fade" id="addonTooltipModal{{ $addon['slug'] }}" tabindex="-1" aria-labelledby="addonTooltipModal{{ $addon['slug'] }}Label" aria-hidden="true" data-admin-modal>
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5 mb-0" id="addonTooltipModal{{ $addon['slug'] }}Label">Edit {{ $addon['label'] }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @include('admin.content.partials.addon-tooltip-form', [
                            'addon' => $addon,
                            'formContext' => 'addon-tooltip-'.$addon['slug'],
                            'modalId' => 'addonTooltipModal'.$addon['slug'],
                        ])
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</main>
@endsection
