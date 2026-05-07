@php
    $overview = $chatView['overview'] ?? [];
    $progress = $chatView['progress'] ?? [];
    $boosterProfile = $chatView['boosterProfile'] ?? [];
    $availableChannels = $chatView['channels'] ?? [];
    $addonItems = array_values($overview['addons'] ?? []);
    $specificAgentUuids = array_values($overview['specificAgentUuids'] ?? []);
    $oneTrickAgentUuids = array_values($overview['oneTrickAgentUuids'] ?? []);
    $snapshotDetails = [
        ['label' => 'Start Rank', 'value' => $overview['startRank'] ?? 'Unranked'],
        ['label' => 'Desired Rank', 'value' => $overview['desiredRank'] ?? 'Unranked'],
        ['label' => 'List of Addons', 'type' => 'addons'],
        ['label' => 'Platform', 'value' => $overview['platform'] ?? 'PC'],
        ['label' => 'Boost Type', 'value' => $overview['boostType'] ?? 'Not specified'],
        ['label' => 'Charged Total', 'value' => $overview['customerTotal'] ?? ($overview['total'] ?? 'USD 0.00')],
        ...(array) (($overview['hasDiscount'] ?? false) ? [
            ['label' => 'Original Price', 'value' => $overview['originalTotal'] ?? 'USD 0.00'],
            ['label' => 'Promo Discount', 'value' => $overview['promoDiscount'] ?? 'USD 0.00'],
        ] : []),
    ];
@endphp

@extends('layouts.layout')

@section('title', 'GGWP Boost | Order Chat')
@section('main_classes', 'site-main site-main--chat site-main--chat-user py-4')


@section('content')
<div class="ggwp-chat-shell ggwp-chat-shell--user" data-order-chat-app data-chat-order-id="{{ $order->id }}" data-chat-user-id="{{ auth()->id() }}" data-chat-page-role="{{ auth()->user()->role }}" data-open-modal="{{ session('rankTrackerModal', '') }}">
    <header class="ggwp-page-header ggwp-chat-page-header">
        <div class="ggwp-page-header__copy">
            <span class="ggwp-page-eyebrow">Order workspace</span>
            <h1 class="h3 mb-1">Order #{{ $overview['orderNumber'] ?? $order->order_number ?? $order->id }}</h1>
            <div class="text-secondary">Track progress, review order details, and message your booster or support.</div>
        </div>
        <div class="ggwp-page-actions">
            <a class="btn btn-outline-light" href="{{ route('allorders') }}">All orders</a>
            <a class="btn btn-outline-light" href="{{ route('customer-dashboard') }}">Dashboard</a>
        </div>
    </header>

    @if(session('status'))
        <div class="alert alert-success mb-0">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger mb-0">{{ $errors->first() }}</div>
    @endif

    <div class="chat-workspace-layout">
        <aside class="chat-workspace-sidebar">
            @include('chats.partials.progress-panel', [
                'overview' => $overview,
                'progress' => $progress,
                'footerMode' => 'actions',
                'actionsDisabled' => false,
                'actionState' => $rankTrackerActions ?? [],
                'showFooterNote' => false,
            ])

            <section class="card app-card chat-panel-card chat-sidebar-card chat-user-booster-card">
                <div class="card-body">
                    <div class="chat-user-booster-card__eyebrow">Your booster</div>
                    <div class="ggwp-person-card chat-user-booster-card__identity">
                        <span class="ggwp-avatar-badge chat-user-booster-card__avatar">{{ strtoupper(substr($boosterProfile['name'] ?? 'B', 0, 1)) }}</span>
                        <div class="chat-user-booster-card__copy">
                            <h3 class="chat-user-booster-card__name mb-0" data-order-bind="boosterName">{{ $boosterProfile['name'] ?? 'Unassigned' }}</h3>
                            <div class="chat-user-booster-card__meta" data-order-bind="boosterEmail">{{ $boosterProfile['email'] ?? '-' }}</div>
                        </div>
                    </div>
                    <div class="ggwp-detail-list ggwp-detail-list-compact chat-user-booster-card__details">
                        <div class="ggwp-detail-item chat-user-booster-card__detail-item">
                            <span class="ggwp-detail-label chat-user-booster-card__label">Booster Status</span>
                            <span class="ggwp-detail-value chat-user-booster-card__value">{{ $boosterProfile['status'] ?? 'Unassigned' }}</span>
                        </div>
                        <div class="ggwp-detail-item chat-user-booster-card__detail-item">
                            <span class="ggwp-detail-label chat-user-booster-card__label">Assigned At</span>
                            <span class="ggwp-detail-value chat-user-booster-card__value chat-user-booster-card__timestamp" data-order-bind="assignedAt">{{ $boosterProfile['assignedAt'] ?? 'Not assigned' }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card app-card chat-panel-card chat-sidebar-summary-card chat-user-snapshot-card">
                <div class="card-body">
                    <div class="chat-user-snapshot-card__eyebrow">Order Snapshot</div>
                    <div class="ggwp-detail-list ggwp-detail-list-compact chat-user-snapshot-list chat-user-snapshot-card__list">
                        @foreach($snapshotDetails as $item)
                            <div class="ggwp-detail-item chat-user-snapshot-card__item{{ ($item['type'] ?? null) === 'addons' ? ' ggwp-detail-item--addons chat-user-snapshot-card__item--addons' : '' }}">
                                <span class="ggwp-detail-label chat-user-snapshot-card__label">{{ $item['label'] }}</span>
                                @if(($item['type'] ?? null) === 'addons')
                                    <div class="ggwp-detail-value chat-user-snapshot-card__value">
                                        @if(count($addonItems))
                                            <ol class="chat-user-addon-list chat-user-snapshot-card__addon-list">
                                                @foreach($addonItems as $addon)
                                                    <li>{{ $addon }}</li>
                                                @endforeach
                                            </ol>
                                            @if(count($specificAgentUuids))
                                                <div class="mt-2">
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
                                                <div class="mt-2">
                                                    <x-agent-selectors.view-trigger
                                                        selector-key="oneTrickAgent"
                                                        :selected-uuids="$oneTrickAgentUuids"
                                                        label="See One-Trick Agent"
                                                        title="Selected one-trick agent"
                                                        description="Review the one-trick agent attached to this order."
                                                    />
                                                </div>
                                            @endif
                                        @else
                                            <span>None</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="ggwp-detail-value chat-user-snapshot-card__value">{{ $item['value'] }}</span>
                                @endif
                            </div> 
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
                'chatCardClass' => 'chat-main-card--workspace chat-main-card--user',
                'showContextStrip' => false,
                'showComposeNotice' => false,
                'inlineCompose' => true,
            ])
        </div>
    </div>
</div>

@include('chats.partials.customer-rank-tracker-modals', [
    'order' => $order,
    'rankTrackerActions' => $rankTrackerActions ?? [],
    'extensionModal' => $extensionModal ?? [],
    'paymentProviders' => $paymentProviders ?? [],
    'defaultPaymentProvider' => $defaultPaymentProvider ?? null,
])
@endsection
