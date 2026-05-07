@php
    $overview = $chatView['overview'] ?? [];
    $progress = $chatView['progress'] ?? [];
    $customerProfile = $chatView['customerProfile'] ?? [];
    $boosterProfile = $chatView['boosterProfile'] ?? [];
    $timeline = $chatView['timeline'] ?? [];
    $availableChannels = $chatView['channels'] ?? [];
    $addonItems = array_values($overview['addons'] ?? []);
    $specificAgentUuids = array_values($overview['specificAgentUuids'] ?? []);
    $oneTrickAgentUuids = array_values($overview['oneTrickAgentUuids'] ?? []);

    $statusOptions = \App\Support\OrderStatus::options();

    $orderDetails = [
        ['label' => 'Order ID', 'value' => $overview['orderNumber'] ?? $order->order_number ?? $order->id],
        ['label' => 'Service', 'value' => $overview['service'] ?? $order->product],
        ['label' => 'Status', 'value' => $overview['status'] ?? 'Pending'],
        ['label' => 'Region', 'value' => $overview['region'] ?? 'Not specified'],
        ['label' => 'Start Rank', 'value' => $overview['startRank'] ?? 'Unranked'],
        ['label' => 'Desired Rank', 'value' => $overview['desiredRank'] ?? 'Unranked'],
        ['label' => 'Current Rank', 'value' => $overview['currentRank'] ?? 'Unranked'],
        ['label' => 'Current RR', 'value' => $overview['currentRR'] ?? '-'],
        ['label' => 'Platform', 'value' => $overview['platform'] ?? 'PC'],
        ['label' => 'Boost Type', 'value' => $overview['boostType'] ?? 'Not specified'],
        ['label' => 'Preferred Contact', 'value' => $overview['contactMethod'] ?? 'Email'],
        ['label' => 'Contact Handle', 'value' => $overview['contactValue'] ?? 'Not supplied'],
        ['label' => 'Charged Total', 'value' => $overview['customerTotal'] ?? ($overview['total'] ?? 'USD 0.00')],
        ['label' => 'Original Price', 'value' => $overview['originalTotal'] ?? ($overview['total'] ?? 'USD 0.00')],
        ['label' => 'Promo Discount', 'value' => $overview['promoDiscount'] ?? 'USD 0.00'],
        ['label' => 'Payout', 'value' => $boosterProfile['payout'] ?? 'USD 0.00'],
    ];

    $boosterDetails = [
        ['label' => 'Account Status', 'value' => $boosterProfile['status'] ?? 'Unassigned'],
        ['label' => 'Assigned At', 'value' => $boosterProfile['assignedAt'] ?? 'Not assigned'],
        ['label' => 'Payout', 'value' => $boosterProfile['payout'] ?? 'USD 0.00'],
        ['label' => 'Email', 'value' => $boosterProfile['email'] ?? 'Not supplied'],
    ];

    $customerDetails = [
        ['label' => 'Account Status', 'value' => $customerProfile['status'] ?? 'Active'],
        ['label' => 'Joined', 'value' => $customerProfile['joinedAt'] ?? '-'],
        ['label' => 'Preferred Contact', 'value' => $overview['contactMethod'] ?? 'Email'],
        ['label' => 'Contact Handle', 'value' => $overview['contactValue'] ?? 'Not supplied'],
    ];
    $snapshotDetails = [
        ['label' => 'Region', 'value' => $overview['region'] ?? 'Not specified'],
        ['label' => 'Platform', 'value' => $overview['platform'] ?? 'PC'],
        ['label' => 'Current Rank', 'value' => $overview['currentRank'] ?? 'Unranked'],
        ['label' => 'Current RR', 'value' => $overview['currentRR'] ?? '-'],
        ['label' => 'Preferred Contact', 'value' => $overview['contactMethod'] ?? 'Email'],
        ['label' => 'Contact Handle', 'value' => $overview['contactValue'] ?? 'Not supplied'],
        ['label' => 'Charged Total', 'value' => $overview['customerTotal'] ?? ($overview['total'] ?? 'USD 0.00')],
        ['label' => 'Payout', 'value' => $boosterProfile['payout'] ?? 'USD 0.00'],
    ];
    $participants = [
        [
            'role' => 'Customer',
            'initial' => strtoupper(substr($customerProfile['name'] ?? 'C', 0, 1)),
            'name' => $customerProfile['name'] ?? 'Customer',
            'email' => $customerProfile['email'] ?? '-',
            'status' => $customerProfile['status'] ?? 'Active',
            'metaLabel' => 'Joined',
            'metaValue' => $customerProfile['joinedAt'] ?? '-',
        ],
        [
            'role' => 'Booster',
            'initial' => strtoupper(substr($boosterProfile['name'] ?? 'B', 0, 1)),
            'name' => $boosterProfile['name'] ?? 'Unassigned',
            'email' => $boosterProfile['email'] ?? '-',
            'status' => $boosterProfile['status'] ?? 'Unassigned',
            'metaLabel' => 'Assigned At',
            'metaValue' => $boosterProfile['assignedAt'] ?? 'Not assigned',
        ],
    ];

    $detailLaunchers = [
        ['target' => '#adminOrderDetailsModal', 'label' => 'Order Details'],
        ['target' => '#adminProgressTrackerModal', 'label' => 'Progress Tracker'],
        ['target' => '#adminBoosterDetailsModal', 'label' => 'Booster Details'],
        ['target' => '#adminCustomerDetailsModal', 'label' => 'Customer Details'],
        ['target' => '#adminOrderTimelineModal', 'label' => 'Order Timeline'],
    ];
    $completionProofPath = is_string($order->completion_proof_path) ? trim($order->completion_proof_path) : '';
    $completionProofUrl = $completionProofPath !== ''
        ? route('admin-orders.completion-proof', ['order' => $order])
        : null;
@endphp

@extends('layouts.admin')

@section('title', 'GGWP Boost | Admin Chat')
@section('main_classes', 'site-main site-main--chat site-main--chat-user py-4')


@section('admin_content')
<div class="chat-admin-stack ggwp-chat-shell ggwp-chat-shell--user ggwp-chat-shell--admin" data-order-chat-app data-chat-order-id="{{ $order->id }}" data-chat-user-id="{{ auth()->id() }}" data-chat-page-role="{{ auth()->user()->role }}">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 chat-admin-header chat-workspace-header">
        <div>
            <div class="small text-uppercase fw-semibold text-secondary mb-1">Admin Workspace</div>
            <h1 class="h4 mb-1">Order #{{ $overview['orderNumber'] ?? $order->order_number ?? $order->id }}</h1>
            <p class="text-secondary mb-0">Review customer, booster, and internal coordination from one compact workspace.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-light" href="{{ route('admin-chats') }}">All Chats</a>
            <a class="btn btn-outline-light" href="{{ route('admin-total-order') }}">All Orders</a>
            <a class="btn btn-outline-light" href="{{ route('admin-orders.edit', ['order' => $order]) }}">Open Editor</a>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success mb-0">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger mb-0">{{ $errors->first() }}</div>
    @endif

    <div class="chat-workspace-layout chat-workspace-layout--admin">
        <aside class="chat-workspace-sidebar">
            @include('chats.partials.progress-panel', [
                'overview' => $overview,
                'progress' => $progress,
            ])

            <section class="card app-card chat-panel-card chat-sidebar-card chat-user-booster-card chat-admin-people-card">
                <div class="card-body">
                    <div class="chat-user-booster-card__eyebrow">Participants</div>
                    <div class="chat-admin-people-card__grid">
                        @foreach($participants as $participant)
                            <div class="chat-admin-person-tile">
                                <div class="ggwp-person-card chat-user-booster-card__identity">
                                    <span class="ggwp-avatar-badge chat-user-booster-card__avatar">{{ $participant['initial'] }}</span>
                                    <div class="chat-user-booster-card__copy">
                                        <div class="small text-uppercase fw-semibold text-secondary">{{ $participant['role'] }}</div>
                                        <h3 class="chat-user-booster-card__name mb-0">{{ $participant['name'] }}</h3>
                                        <div class="chat-user-booster-card__meta">{{ $participant['email'] }}</div>
                                    </div>
                                </div>
                                <div class="ggwp-detail-list ggwp-detail-list-compact chat-user-booster-card__details">
                                    <div class="ggwp-detail-item chat-user-booster-card__detail-item">
                                        <span class="ggwp-detail-label chat-user-booster-card__label">Account Status</span>
                                        <span class="ggwp-detail-value chat-user-booster-card__value">{{ $participant['status'] }}</span>
                                    </div>
                                    <div class="ggwp-detail-item chat-user-booster-card__detail-item">
                                        <span class="ggwp-detail-label chat-user-booster-card__label">{{ $participant['metaLabel'] }}</span>
                                        <span class="ggwp-detail-value chat-user-booster-card__value">{{ $participant['metaValue'] }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="card app-card chat-panel-card chat-sidebar-summary-card chat-user-snapshot-card chat-admin-snapshot-card">
                <div class="card-body">
                    <div class="chat-user-snapshot-card__eyebrow">Order Snapshot</div>
                    <div class="chat-sidebar-chip-row">
                        <span class="chat-sidebar-chip">Order #{{ $overview['orderNumber'] ?? $order->order_number ?? $order->id }}</span>
                        <span class="chat-sidebar-chip">{{ $overview['service'] ?? $order->product }}</span>
                        <span class="badge text-bg-{{ $overview['statusTone'] ?? 'secondary' }}" data-order-status-badge>
                            <span data-order-bind="statusLabel">{{ $overview['status'] ?? 'Pending' }}</span>
                        </span>
                    </div>
                    <div class="ggwp-detail-list ggwp-detail-list-compact chat-user-snapshot-list chat-user-snapshot-card__list">
                        @foreach($snapshotDetails as $item)
                            <div class="ggwp-detail-item chat-user-snapshot-card__item">
                                <span class="ggwp-detail-label chat-user-snapshot-card__label">{{ $item['label'] }}</span>
                                <span class="ggwp-detail-value chat-user-snapshot-card__value">{{ $item['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="card app-card chat-panel-card chat-sidebar-card chat-user-snapshot-card chat-admin-controls-card">
                <div class="card-body">
                    <div class="chat-user-snapshot-card__eyebrow">Admin Controls</div>
                    <button
                        type="button"
                        class="btn btn-outline-light w-100 chat-admin-modal-trigger"
                        data-bs-toggle="modal"
                        data-bs-target="#adminControlsModal"
                    >
                        Open Admin Controls
                    </button>
                </div>
            </section>

            @if($completionProofUrl)
                <section class="card app-card chat-panel-card chat-sidebar-card chat-user-snapshot-card chat-admin-proof-card">
                    <div class="card-body">
                        <div class="chat-user-snapshot-card__eyebrow">Completion Proof</div>
                        <a class="d-block mb-3" href="{{ $completionProofUrl }}" target="_blank" rel="noopener">
                            <img src="{{ $completionProofUrl }}" alt="Completion proof screenshot" class="img-fluid rounded" loading="lazy" decoding="async">
                        </a>
                        <div class="small text-secondary mb-3">
                            Uploaded {{ $order->completion_proof_uploaded_at?->format('M j, Y g:i A') ?? 'recently' }}
                        </div>
                        <a class="btn btn-outline-light w-100" href="{{ $completionProofUrl }}" target="_blank" rel="noopener">Open Full Size</a>
                    </div>
                </section>
            @endif

            <section class="card app-card chat-panel-card chat-admin-launchers-card chat-sidebar-card chat-user-snapshot-card">
                <div class="card-body">
                    <div class="chat-user-snapshot-card__eyebrow">Supporting Panels</div>
                    <div class="chat-admin-launcher-grid">
                        @foreach($detailLaunchers as $launcher)
                            <button
                                type="button"
                                class="btn btn-outline-light chat-admin-launcher-btn"
                                data-bs-toggle="modal"
                                data-bs-target="{{ $launcher['target'] }}"
                            >
                                {{ $launcher['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </section>
        </aside>

        <div class="chat-workspace-main">
            @include('chats.partials.chat-card', [
                'availableChannels' => $availableChannels,
                'sendButtonLabel' => 'Send message',
                'sendButtonAriaLabel' => 'Send message',
                'sendButtonIcon' => asset('assets/chats/send_button.png'),
                'cardEyebrow' => 'Realtime Chat',
                'chatCardClass' => 'chat-main-card--workspace chat-main-card--user chat-main-card--admin',
                'showContextStrip' => false,
                'showComposeNotice' => false,
                'inlineCompose' => true,
            ])
        </div>
    </div>
</div>

<div class="modal fade chat-detail-modal" id="adminControlsModal" tabindex="-1" aria-labelledby="adminControlsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <div class="small text-uppercase fw-semibold text-secondary">Admin Controls</div>
                    <h2 class="modal-title h4 mb-0" id="adminControlsModalTitle">Manage this order</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                    <div class="ggwp-quick-actions">
                        <button type="button" class="btn btn-outline-light" data-copy-order="{{ $overview['orderNumber'] ?? $order->order_number ?? $order->id }}">Copy Order ID</button>
                        <a class="btn btn-outline-light" href="{{ route('admin-orders.edit', ['order' => $order]) }}">Full Order Editor</a>
                    </div>
                </div>

                <div class="chat-admin-controls-grid">
                    <div>
                        <div class="ggwp-control-card">
                            <div class="small text-uppercase fw-semibold text-secondary mb-2">Assign or reassign booster</div>
                            <form method="POST" action="{{ route('admin-orders.assign-booster', ['order' => $order]) }}">
                                @csrf
                                @method('PATCH')
                                <label class="form-label">Booster</label>
                                <select class="form-select mb-3" name="booster_id">
                                    <option value="">Unassigned</option>
                                    @foreach($boosters as $booster)
                                        <option value="{{ $booster->id }}" {{ (int) $order->booster_id === (int) $booster->id ? 'selected' : '' }}>
                                            {{ $booster->publicIdentity('Booster '.$booster->id) }}@if($booster->publicIdentity('Booster '.$booster->id) !== $booster->fullIdentity('Booster '.$booster->id)) ({{ $booster->fullIdentity('Booster '.$booster->id) }})@endif
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-danger w-100">Save Booster Assignment</button>
                            </form>
                        </div>
                    </div>

                    <div>
                        <div class="ggwp-control-card">
                            <div class="small text-uppercase fw-semibold text-secondary mb-2">Update order status</div>
                            <form method="POST" action="{{ route('admin-orders.update', ['order' => $order]) }}">
                                @csrf
                                @method('PATCH')
                                <label class="form-label">Status</label>
                                <select class="form-select mb-3" name="status">
                                    @foreach($statusOptions as $value => $label)
                                        <option value="{{ $value }}" {{ ($order->status ?? 'Pending') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-danger w-100">Save Status</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade chat-detail-modal" id="adminOrderDetailsModal" tabindex="-1" aria-labelledby="adminOrderDetailsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <div class="small text-uppercase fw-semibold text-secondary">Order Details</div>
                    <h2 class="modal-title h4 mb-0" id="adminOrderDetailsModalTitle">Execution summary</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="chat-admin-detail-grid">
                    @foreach($orderDetails as $item)
                        <div class="ggwp-detail-item">
                            <span class="ggwp-detail-label">{{ $item['label'] }}</span>
                            <span class="ggwp-detail-value">{{ $item['value'] }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="ggwp-note-box mt-3">
                    Addons: {{ count($addonItems) ? implode(', ', $addonItems) : 'None' }}
                </div>
                @if(count($specificAgentUuids))
                    <div class="mt-3">
                        <x-agent-selectors.view-trigger
                            selector-key="specificAgents"
                            :selected-uuids="$specificAgentUuids"
                            label="See Specific Agents"
                            title="Selected specific agents"
                            description="Review the agents attached to this order."
                        />
                    </div>
                @endif
                @if(count($oneTrickAgentUuids))
                    <div class="mt-3">
                        <x-agent-selectors.view-trigger
                            selector-key="oneTrickAgent"
                            :selected-uuids="$oneTrickAgentUuids"
                            label="See One-Trick Agent"
                            title="Selected one-trick agent"
                            description="Review the one-trick agent attached to this order."
                        />
                    </div>
                @endif
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-light" data-copy-order="{{ $overview['orderNumber'] ?? $order->order_number ?? $order->id }}">Copy Order ID</button>
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade chat-detail-modal" id="adminBoosterDetailsModal" tabindex="-1" aria-labelledby="adminBoosterDetailsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <div class="small text-uppercase fw-semibold text-secondary">Booster Details</div>
                    <h2 class="modal-title h4 mb-0" id="adminBoosterDetailsModalTitle">Assigned booster</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="ggwp-person-card mb-3">
                    <span class="ggwp-avatar-badge">{{ strtoupper(substr($boosterProfile['name'] ?? 'B', 0, 1)) }}</span>
                    <div>
                        <h3 class="h6 mb-1">{{ $boosterProfile['name'] ?? 'Unassigned' }}</h3>
                        <div class="text-secondary small">{{ $boosterProfile['email'] ?? '-' }}</div>
                    </div>
                </div>
                <div class="chat-admin-detail-grid">
                    @foreach($boosterDetails as $item)
                        <div class="ggwp-detail-item">
                            <span class="ggwp-detail-label">{{ $item['label'] }}</span>
                            <span class="ggwp-detail-value">{{ $item['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade chat-detail-modal" id="adminProgressTrackerModal" tabindex="-1" aria-labelledby="adminProgressTrackerModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <div class="small text-uppercase fw-semibold text-secondary">Progress Tracker</div>
                    <h2 class="modal-title h4 mb-0" id="adminProgressTrackerModalTitle">Rank tracker</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                @include('chats.partials.progress-panel', [
                    'overview' => $overview,
                    'progress' => $progress,
                ])
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade chat-detail-modal" id="adminCustomerDetailsModal" tabindex="-1" aria-labelledby="adminCustomerDetailsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <div class="small text-uppercase fw-semibold text-secondary">Customer Details</div>
                    <h2 class="modal-title h4 mb-0" id="adminCustomerDetailsModalTitle">Customer profile</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="ggwp-person-card mb-3">
                    <span class="ggwp-avatar-badge">{{ strtoupper(substr($customerProfile['name'] ?? 'C', 0, 1)) }}</span>
                    <div>
                        <h3 class="h6 mb-1">{{ $customerProfile['name'] ?? 'Customer' }}</h3>
                        <div class="text-secondary small">{{ $customerProfile['email'] ?? '-' }}</div>
                    </div>
                </div>
                <div class="chat-admin-detail-grid">
                    @foreach($customerDetails as $item)
                        <div class="ggwp-detail-item">
                            <span class="ggwp-detail-label">{{ $item['label'] }}</span>
                            <span class="ggwp-detail-value">{{ $item['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade chat-detail-modal" id="adminOrderTimelineModal" tabindex="-1" aria-labelledby="adminOrderTimelineModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <div class="small text-uppercase fw-semibold text-secondary">Order Timeline</div>
                    <h2 class="modal-title h4 mb-0" id="adminOrderTimelineModalTitle">Activity history</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="chat-admin-detail-grid">
                    @forelse($timeline as $item)
                        <div class="ggwp-detail-item">
                            <span class="ggwp-detail-label">{{ $item['label'] ?? 'Event' }}</span>
                            <span class="ggwp-detail-value">{{ $item['value'] ?? '-' }}</span>
                        </div>
                    @empty
                        <div class="ggwp-note-box">No timeline events are available for this order yet.</div>
                    @endforelse
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection
