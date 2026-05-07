@extends('layouts.admin')

@section('title', 'GGWP Boost | Contact Message')

@php
    use App\Models\ContactMessage;

    $statusOptions = collect(ContactMessage::transitionTargets($message->status))
        ->mapWithKeys(fn (string $status): array => [$status => ContactMessage::statusOptions()[$status]])
        ->all();
    $quickStatuses = [
        ContactMessage::STATUS_READ,
        ContactMessage::STATUS_REPLIED,
        ContactMessage::STATUS_IGNORED,
    ];
@endphp

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense">
    @include('admin.partials.page-header', [
        'title' => 'Inbox Message',
        'subtitle' => 'Triage, assign, and link records.',
        'meta' => [
            $message->statusLabel(),
            'Submitted '.$message->created_at?->format('M j, Y g:i A'),
        ],
        'actions' => [
            ['label' => 'Back To Inbox', 'href' => route('admin-contact-messages.index')],
        ],
    ])

    <div class="row g-2">
        <div class="col-xl-7">
            <section class="card app-card admin-section-card mb-2">
                <div class="card-body">
                    <h2 class="h5 mb-3">Message</h2>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input class="form-control" value="{{ $message->name }}" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input class="form-control" value="{{ $message->email }}" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Order Reference</label>
                            <input class="form-control" value="{{ $message->order_ref ?: 'Not provided' }}" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Closed At</label>
                            <input class="form-control" value="{{ $message->closed_at?->format('M j, Y g:i A') ?? 'Open' }}" disabled>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Submitted Message</label>
                            <textarea class="form-control" rows="8" disabled>{{ $message->message }}</textarea>
                        </div>
                    </div>
                </div>
            </section>

            <div class="d-flex flex-wrap gap-2 mb-2">
                @foreach($quickStatuses as $status)
                    @php
                        $label = ContactMessage::statusOptions()[$status];
                    @endphp
                    <form method="POST" action="{{ route('admin-contact-messages.update', $message) }}" data-loading-form class="d-inline-flex">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ $status }}">
                        <button
                            class="btn btn-sm {{ $message->status === $status ? 'btn-danger' : 'btn-outline-light' }}"
                            type="submit"
                            data-busy-label="Saving..."
                            {{ $message->status === $status || ! $message->canTransitionTo($status) ? 'disabled' : '' }}
                        >
                            {{ $label }}
                        </button>
                    </form>
                @endforeach
            </div>

            <form method="POST" action="{{ route('admin-contact-messages.update', $message) }}" class="card app-card admin-section-card" data-loading-form data-dirty-form data-validate-form novalidate>
                @csrf
                @method('PATCH')
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">Handling</h2>
                        <button class="btn btn-danger" type="submit" data-busy-label="Saving...">Save</button>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select class="form-select @error('status') is-invalid @enderror" name="status">
                                @foreach($statusOptions as $value => $label)
                                    <option value="{{ $value }}" {{ old('status', $message->status) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Assigned Admin</label>
                            <select class="form-select @error('assigned_admin_id') is-invalid @enderror" name="assigned_admin_id">
                                <option value="">Unassigned</option>
                                @foreach($admins as $admin)
                                    <option value="{{ $admin->id }}" {{ (string) old('assigned_admin_id', $message->assigned_admin_id) === (string) $admin->id ? 'selected' : '' }}>
                                        {{ $admin->fullIdentity('Admin') }} · {{ $admin->adminRoleLabel() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('assigned_admin_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Related Customer</label>
                            <select class="form-select @error('related_customer_id') is-invalid @enderror" name="related_customer_id">
                                <option value="">None</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}" {{ (string) old('related_customer_id', $message->related_customer_id) === (string) $customer->id ? 'selected' : '' }}>
                                        {{ $customer->fullIdentity('Customer') }} · {{ $customer->email }}
                                    </option>
                                @endforeach
                            </select>
                            @error('related_customer_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Related Order</label>
                            <select class="form-select @error('related_order_id') is-invalid @enderror" name="related_order_id">
                                <option value="">None</option>
                                @foreach($orders as $order)
                                    <option value="{{ $order->id }}" {{ (string) old('related_order_id', $message->related_order_id) === (string) $order->id ? 'selected' : '' }}>
                                        #{{ $order->order_number }} · {{ $order->user?->fullIdentity('Customer') }} · {{ $order->status }}
                                    </option>
                                @endforeach
                            </select>
                            @error('related_order_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Internal Notes</label>
                            <textarea class="form-control @error('internal_notes') is-invalid @enderror" name="internal_notes" rows="5" maxlength="2000">{{ old('internal_notes', $message->internal_notes) }}</textarea>
                            @error('internal_notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-xl-5">
            <section class="card app-card admin-section-card">
                <div class="card-body">
                    <h2 class="h5 mb-3">Snapshot</h2>
                    <div class="d-flex justify-content-between py-2 border-bottom border-secondary-subtle">
                        <span>Status</span>
                        <strong>{{ $message->statusLabel() }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom border-secondary-subtle">
                        <span>Assigned Admin</span>
                        <strong>{{ $message->assignedAdmin?->fullIdentity('Unassigned') ?? 'Unassigned' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom border-secondary-subtle">
                        <span>Related Order</span>
                        <strong>{{ $message->relatedOrder?->order_number ? '#'.$message->relatedOrder->order_number : 'None' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom border-secondary-subtle">
                        <span>Related Customer</span>
                        <strong>{{ $message->relatedCustomer?->fullIdentity('None') ?? 'None' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2">
                        <span>Closed At</span>
                        <strong>{{ $message->closed_at?->format('M j, Y g:i A') ?? 'Open' }}</strong>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>
@endsection
