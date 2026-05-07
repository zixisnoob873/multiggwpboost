@extends('layouts.admin')

@section('title', 'GGWP Boost | FAQs')

@section('admin_content')
<main
    class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense"
    @if(old('modal_id'))
        data-open-admin-modal="{{ old('modal_id') }}"
    @endif
>
    @include('admin.partials.page-header', [
        'title' => 'FAQs',
        'actions' => [
            ['label' => 'Content Home', 'href' => route('admin-content.index'), 'class' => 'btn btn-outline-light btn-sm'],
            ['label' => 'Pages', 'href' => route('admin-pages.index'), 'class' => 'btn btn-outline-light btn-sm'],
            ['label' => 'Add FAQ', 'type' => 'button', 'toggle' => 'modal', 'target' => '#faqCreateModal', 'class' => 'btn btn-danger btn-sm'],
        ],
    ])

    <section class="card app-card admin-section-card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="h6 mb-0">FAQ Library</h2>
                <span class="admin-chip">{{ $faqs->total() }} total</span>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 ggwp-data-table">
                    <thead>
                        <tr>
                            <th>Question</th>
                            <th>Order</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($faqs as $faq)
                            <tr>
                                <td class="fw-semibold">{{ $faq->question }}</td>
                                <td>{{ $faq->order }}</td>
                                <td class="text-end">
                                    <div class="ggwp-table-actions justify-content-end">
                                        <button
                                            class="btn btn-outline-light btn-sm"
                                            type="button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#faqEditModal{{ $faq->id }}"
                                        >
                                            Edit
                                        </button>
                                        <form
                                            method="POST"
                                            action="{{ route('admin-faqs.destroy', $faq) }}"
                                            data-loading-form
                                            data-confirm-submit="Delete this FAQ?"
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
                                <td colspan="3" class="text-center text-secondary py-4">No FAQs added yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $faqs->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </section>

    <div class="modal fade" id="faqCreateModal" tabindex="-1" aria-labelledby="faqCreateModalLabel" aria-hidden="true" data-admin-modal>
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5 mb-0" id="faqCreateModalLabel">Add FAQ</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @include('admin.content.partials.faq-form', [
                        'action' => route('admin-faqs.store'),
                        'faq' => null,
                        'submitLabel' => 'Create FAQ',
                        'submitClass' => 'btn-danger',
                        'formContext' => 'faq-create',
                        'modalId' => 'faqCreateModal',
                    ])
                </div>
            </div>
        </div>
    </div>

    @foreach($faqs as $faq)
        <div class="modal fade" id="faqEditModal{{ $faq->id }}" tabindex="-1" aria-labelledby="faqEditModal{{ $faq->id }}Label" aria-hidden="true" data-admin-modal>
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5 mb-0" id="faqEditModal{{ $faq->id }}Label">Edit FAQ</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @include('admin.content.partials.faq-form', [
                            'action' => route('admin-faqs.update', $faq),
                            'method' => 'PATCH',
                            'faq' => $faq,
                            'submitLabel' => 'Save FAQ',
                            'submitClass' => 'btn-danger',
                            'formContext' => 'faq-'.$faq->id,
                            'modalId' => 'faqEditModal'.$faq->id,
                        ])
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</main>
@endsection
