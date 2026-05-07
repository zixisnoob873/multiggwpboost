@php
    use App\Support\OrderMetadataSanitizer;
    use Illuminate\Support\Arr;
    use Illuminate\Support\Str;

    $details = $order->detailsPayload();
    $metadata = OrderMetadataSanitizer::forAdminTooling($order->metadata ?? []);
    $orderPayload = $order->orderPayload();
    $selectedProduct = (string) old('product', $order->product);
    $serviceOptions = collect($serviceOptions ?? []);

    if ($selectedProduct !== '' && ! $serviceOptions->contains($selectedProduct)) {
        $serviceOptions = $serviceOptions->prepend($selectedProduct);
    }

    $flattenStructure = function ($items, $parentKey = null) use (&$flattenStructure) {
        $items = is_array($items) ? $items : [];
        $collection = collect();

        foreach ($items as $key => $value) {
            $name = $parentKey ? "{$parentKey}.{$key}" : $key;

            if (is_array($value)) {
                if (array_values($value) === $value && collect($value)->every(fn ($item) => is_scalar($item) || $item === null)) {
                    $collection[$name] = implode(', ', array_map(fn ($item) => (string) $item, $value));
                    continue;
                }

                $collection = $collection->merge($flattenStructure($value, $name));
                continue;
            }

            $collection[$name] = is_scalar($value) || $value === null
                ? (string) $value
                : json_encode($value, JSON_UNESCAPED_SLASHES);
        }

        return $collection;
    };

    $detailFields = $flattenStructure($details);
    $metadataFields = $flattenStructure($metadata);
    $oldDetails = old('details', []);
    $oldMetadata = old('metadata', []);
@endphp

@extends('layouts.admin')

@section('title', 'GGWP Boost | Order')

@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide">
    @include('admin.partials.page-header', [
        'title' => 'Order #'.$order->order_number,
        'subtitle' => 'Structured for everyday operations, with raw metadata pushed into Advanced.',
        'meta' => [
            $order->serviceName(),
            'Progress: '.$order->progressPercent().'%',
            'Created '.$order->created_at?->format('M j, Y g:i A'),
        ],
        'actions' => [
            ['label' => 'Back To Orders', 'href' => route('admin-total-order')],
            ['label' => 'Open Chat', 'href' => route('admin-chats.show', $order), 'class' => 'btn btn-outline-light btn-sm'],
        ],
    ])

    @if($order->is_custom)
        <div class="alert alert-warning mb-3">This is a manual order. Admin override pricing and restricted addon combinations are preserved when you update it.</div>
    @endif

    <div class="admin-chip-row mb-3">
        @foreach([
            'Summary' => '#summary',
            'Customer' => '#customer',
            'Booster' => '#booster',
            'Pricing' => '#pricing',
            'Progress' => '#progress',
            'Chat' => '#chat',
            'History' => '#history',
            'Advanced' => '#advanced',
        ] as $label => $anchor)
            <a class="admin-chip text-decoration-none" href="{{ $anchor }}">{{ $label }}</a>
        @endforeach
    </div>

    <form method="POST" action="{{ route('admin-orders.update', $order) }}" data-loading-form data-dirty-form>
        @csrf
        @method('PATCH')

        <div class="row g-3">
            <div class="col-xl-8">
                <section class="card app-card admin-section-card" id="summary">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Summary</h2>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select class="form-select @error('status') is-invalid @enderror" name="status">
                                    @foreach($statusOptions as $value => $label)
                                        <option value="{{ $value }}" {{ old('status', $order->status) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Payment Status</label>
                                <select class="form-select @error('payment_status') is-invalid @enderror" name="payment_status">
                                    @foreach($paymentStatusOptions as $value => $label)
                                        <option value="{{ $value }}" {{ old('payment_status', $order->payment_status) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('payment_status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Service</label>
                                <select @class(['form-select', 'is-invalid' => $errors->has('product')]) name="product" required>
                                    @foreach($serviceOptions as $serviceOption)
                                        <option value="{{ $serviceOption }}" {{ $selectedProduct === $serviceOption ? 'selected' : '' }}>{{ $serviceOption }}</option>
                                    @endforeach
                                </select>
                                @error('product')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">Status Note</label>
                                <input class="form-control @error('status_reason') is-invalid @enderror" name="status_reason" value="{{ old('status_reason') }}">
                                @error('status_reason')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Refund Amount</label>
                                <input type="number" min="0" step="0.01" class="form-control @error('refund_amount') is-invalid @enderror" name="refund_amount" value="{{ old('refund_amount') }}">
                                @error('refund_amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Refund Method</label>
                                <input class="form-control @error('refund_method') is-invalid @enderror" name="refund_method" value="{{ old('refund_method') }}">
                                @error('refund_method')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Refund Reference</label>
                                <input class="form-control @error('refund_reference') is-invalid @enderror" name="refund_reference" value="{{ old('refund_reference') }}">
                                @error('refund_reference')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Refund Arrival</label>
                                <input class="form-control @error('refund_arrival_estimate') is-invalid @enderror" name="refund_arrival_estimate" value="{{ old('refund_arrival_estimate') }}">
                                @error('refund_arrival_estimate')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </section>

                <section class="card app-card admin-section-card" id="customer">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Customer</h2>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Order Owner</label>
                                <select class="form-select @error('user_id') is-invalid @enderror" name="user_id">
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}" {{ (string) old('user_id', $order->user_id) === (string) $customer->id ? 'selected' : '' }}>
                                            {{ $customer->fullIdentity('Customer') }} · {{ $customer->email }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('user_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Contact Method</label>
                                <input class="form-control" value="{{ $order->contact_method ?: 'Not set' }}" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">WhatsApp</label>
                                <input class="form-control" value="{{ $order->whatsapp ?: 'Not provided' }}" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Discord</label>
                                <input class="form-control" value="{{ $order->discord ?: 'Not provided' }}" disabled>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="card app-card admin-section-card" id="booster">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Booster</h2>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Assigned Booster</label>
                                <select class="form-select @error('booster_id') is-invalid @enderror" name="booster_id">
                                    <option value="">Unassigned</option>
                                    @foreach($boosters as $booster)
                                        <option value="{{ $booster->id }}" {{ (string) old('booster_id', $order->booster_id) === (string) $booster->id ? 'selected' : '' }}>
                                            {{ $booster->fullIdentity('Booster') }} · {{ $booster->email }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('booster_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Assigned At</label>
                                <input class="form-control" value="{{ $order->assigned_at?->format('M j, Y g:i A') ?? 'Not assigned' }}" disabled>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="card app-card admin-section-card" id="pricing">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Pricing</h2>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Customer Total</label>
                                <input type="number" min="0" step="0.01" class="form-control @error('price') is-invalid @enderror" name="price" value="{{ old('price', number_format($order->customerPriceCents() / 100, 2, '.', '')) }}">
                                @error('price')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Currency</label>
                                <input class="form-control @error('currency') is-invalid @enderror" name="currency" value="{{ old('currency', $order->currency ?? 'USD') }}" maxlength="3">
                                @error('currency')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Original</label>
                                <input class="form-control" value="${{ number_format($order->resolvedOriginalPriceCents() / 100, 2) }}" disabled>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Discount</label>
                                <input class="form-control" value="${{ number_format($order->resolvedDiscountAmountCents() / 100, 2) }}" disabled>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Payout</label>
                                <input class="form-control" value="${{ number_format($order->resolvedBoosterPayoutCents() / 100, 2) }}" disabled>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="card app-card admin-section-card" id="progress">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Progress</h2>
                        <div class="progress mb-3" role="progressbar" aria-valuenow="{{ $order->progressPercent() }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width: {{ $order->progressPercent() }}%">{{ $order->progressPercent() }}%</div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Rank From</label>
                                <input class="form-control" value="{{ $order->rankFromLabel() }}" disabled>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Rank To</label>
                                <input class="form-control" value="{{ $order->rankToLabel() }}" disabled>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Region</label>
                                <input class="form-control" value="{{ $order->regionLabel() }}" disabled>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Addons</label>
                                <input class="form-control" value="{{ $order->addonsLabel() }}" disabled>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="card app-card admin-section-card" id="chat">
                    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <h2 class="h5 mb-1">Chat</h2>
                            <p class="text-secondary mb-0">Move straight into the live order thread without leaving operations.</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="admin-chip">{{ $order->chat_threads_count ?? 0 }} threads</span>
                            <a class="btn btn-outline-light" href="{{ route('admin-chats.show', $order) }}">Open Chat Workspace</a>
                        </div>
                    </div>
                </section>

                <section class="card app-card admin-section-card" id="history">
                    <div class="card-body">
                        <h2 class="h5 mb-3">History / Timeline</h2>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="admin-chip w-100">Created: {{ $order->created_at?->format('M j, Y g:i A') ?? '-' }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="admin-chip w-100">Paid: {{ $order->paid_at?->format('M j, Y g:i A') ?? 'Not marked paid' }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="admin-chip w-100">Completed: {{ $order->completed_at?->format('M j, Y g:i A') ?? 'Not completed' }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="admin-chip w-100">Manual Order: {{ $order->is_custom ? 'Yes' : 'No' }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="admin-chip w-100">Promo Applied: {{ $order->hasPromoApplied() ? 'Yes' : 'No' }}</div>
                            </div>
                            <div class="col-md-4">
                                @if($order->completion_proof_path)
                                    <a class="btn btn-outline-light btn-sm w-100" href="{{ route('admin-orders.completion-proof', $order) }}" target="_blank" rel="noopener">Open Completion Proof</a>
                                @else
                                    <div class="admin-chip w-100">Completion Proof: None</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </section>

                <details class="card app-card admin-section-card" id="advanced">
                    <summary class="card-body d-flex justify-content-between align-items-center admin-summary-toggle">
                        <div>
                            <h2 class="h5 mb-1">Advanced</h2>
                            <p class="text-secondary mb-0">Raw flattened details and metadata for edge cases only.</p>
                        </div>
                        <span class="admin-chip">Expand</span>
                    </summary>
                    <div class="card-body pt-0">
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <h3 class="h6 mb-3">Details</h3>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <tbody>
                                            @forelse($detailFields as $field => $value)
                                                <tr>
                                                    <th class="text-secondary">{{ Str::headline(str_replace('.', ' ', $field)) }}</th>
                                                    <td>
                                                        <input class="form-control form-control-sm" name="details[{{ $field }}]" value="{{ is_array($oldDetails) ? ($oldDetails[$field] ?? $value) : $value }}">
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="2" class="text-secondary">No stored details.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <h3 class="h6 mb-3">Metadata</h3>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <tbody>
                                            @forelse($metadataFields as $field => $value)
                                                <tr>
                                                    <th class="text-secondary">{{ Str::headline(str_replace('.', ' ', $field)) }}</th>
                                                    <td>
                                                        <input class="form-control form-control-sm" name="metadata[{{ $field }}]" value="{{ is_array($oldMetadata) ? ($oldMetadata[$field] ?? $value) : $value }}">
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="2" class="text-secondary">No stored metadata.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </details>
            </div>

            <div class="col-xl-4">
                <section class="card app-card admin-section-card sticky-top admin-sticky-card">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Order Snapshot</h2>
                        <div class="d-flex justify-content-between py-2 border-bottom border-secondary-subtle">
                            <span>Order Number</span>
                            <strong>#{{ $order->order_number }}</strong>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom border-secondary-subtle">
                            <span>Customer</span>
                            <strong>{{ $order->user?->publicIdentity('Customer') }}</strong>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom border-secondary-subtle">
                            <span>Booster</span>
                            <strong>{{ $order->booster?->publicIdentity('Unassigned') ?? 'Unassigned' }}</strong>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom border-secondary-subtle">
                            <span>Status</span>
                            @include('partials.order-status-badge', ['status' => $order->status])
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom border-secondary-subtle">
                            <span>Total</span>
                            <strong>${{ number_format($order->customerPriceCents() / 100, 2) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between py-2">
                            <span>Payment</span>
                            <strong>{{ ucfirst((string) $order->payment_status) }}</strong>
                        </div>

                        <div class="d-grid gap-2 mt-3">
                            <button class="btn btn-danger" type="submit" data-busy-label="Saving...">Save Order</button>
                            <a class="btn btn-outline-light" href="{{ route('admin-total-order') }}">Cancel</a>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </form>
</main>
@endsection
