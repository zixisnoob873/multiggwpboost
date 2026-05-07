@extends('layouts.admin')

@section('title', 'GGWP Boost | Contact Inbox')

@php
    use App\Models\ContactMessage;

    $statusOptions = ContactMessage::statusOptions();
@endphp

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense">
    @include('admin.partials.page-header', [
        'title' => 'Contact Inbox',
        'subtitle' => 'Pre-sales and support queue.',
        'actions' => [
            ['label' => 'People', 'href' => route('admin-customers.index')],
        ],
    ])

    <div class="admin-chip-row mb-3">
        @foreach($statusOptions as $statusKey => $statusLabel)
            <a class="admin-chip text-decoration-none" href="{{ route('admin-contact-messages.index', array_merge(request()->query(), ['status' => $statusKey, 'page' => 1])) }}">
                {{ $statusLabel }}: {{ $contactStats[$statusKey] ?? 0 }}
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin-contact-messages.index') }}" class="card app-card admin-filters-card mb-3" data-loading-form>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-5">
                    <label class="form-label">Search</label>
                    <input class="form-control @error('search') is-invalid @enderror" name="search" value="{{ $contactFilters['search'] ?? '' }}" placeholder="Name, email, order ref, message">
                    @error('search')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select @error('status') is-invalid @enderror" name="status">
                        <option value="">All</option>
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" {{ ($contactFilters['status'] ?? null) === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Assigned Admin</label>
                    <select class="form-select @error('assigned_admin_id') is-invalid @enderror" name="assigned_admin_id">
                        <option value="">All</option>
                        @foreach($admins as $admin)
                            <option value="{{ $admin->id }}" {{ (string) ($contactFilters['assigned_admin_id'] ?? '') === (string) $admin->id ? 'selected' : '' }}>
                                {{ $admin->fullIdentity('Admin') }}
                            </option>
                        @endforeach
                    </select>
                    @error('assigned_admin_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Per Page</label>
                    <select class="form-select @error('per_page') is-invalid @enderror" name="per_page">
                        @foreach([10, 25, 50, 100] as $size)
                            <option value="{{ $size }}" {{ (int) ($contactFilters['per_page'] ?? 25) === $size ? 'selected' : '' }}>{{ $size }}</option>
                        @endforeach
                    </select>
                    @error('per_page')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <button class="btn btn-danger" type="submit" data-busy-label="Applying...">Apply Filters</button>
                <a class="btn btn-outline-light" href="{{ route('admin-contact-messages.index') }}">Reset</a>
            </div>
        </div>
    </form>

    <section class="card app-card admin-section-card">
        <div class="card-body">
            @if($messages->isEmpty())
                @include('admin.partials.empty-state', [
                    'title' => 'No inbox messages matched these filters',
                    'copy' => 'Clear the filters or wait for the next contact form submission to enter the inbox.',
                ])
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Submitted</th>
                                <th>Sender</th>
                                <th>Status</th>
                                <th>Assigned</th>
                                <th>Related</th>
                                <th>Message</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($messages as $message)
                                <tr>
                                    <td>{{ $message->created_at?->format('M j, Y g:i A') ?? '-' }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $message->name }}</div>
                                        <div class="small text-secondary">{{ $message->email }}</div>
                                    </td>
                                    <td>{{ $message->statusLabel() }}</td>
                                    <td>{{ $message->assignedAdmin?->fullIdentity('Unassigned') ?? 'Unassigned' }}</td>
                                    <td>
                                        @if($message->relatedOrder)
                                            <div class="small">Order #{{ $message->relatedOrder->order_number }}</div>
                                        @endif
                                        @if($message->relatedCustomer)
                                            <div class="small">Customer {{ $message->relatedCustomer->fullIdentity('Customer') }}</div>
                                        @endif
                                        @if(! $message->relatedOrder && ! $message->relatedCustomer)
                                            <span class="text-secondary small">None linked</span>
                                        @endif
                                    </td>
                                    <td>{{ \Illuminate\Support\Str::limit($message->message, 120) }}</td>
                                    <td class="text-end">
                                        <a class="btn btn-outline-light btn-sm" href="{{ route('admin-contact-messages.edit', $message) }}">Open</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $messages->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </section>
</main>
@endsection
