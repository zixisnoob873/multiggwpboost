@php
    $overview = $chatView['overview'] ?? [];
    $progress = $chatView['progress'] ?? [];
    $customerProfile = $chatView['customerProfile'] ?? [];
    $availableChannels = $chatView['channels'] ?? [];
    $workspaceNotices = $chatView['workspaceNotices'] ?? [];
    $progressForm = $progressForm ?? [];
    $openModal = $openModal ?? '';
    $rankOptions = $progressForm['rankOptions'] ?? [];
    $addonItems = array_values($overview['addons'] ?? []);
    $specificAgentUuids = array_values($overview['specificAgentUuids'] ?? []);
    $oneTrickAgentUuids = array_values($overview['oneTrickAgentUuids'] ?? []);
    $trackedCompletion = (int) ($progress['pct'] ?? 0);
    $completionProofTimestamp = $order->completion_proof_uploaded_at?->format('M j, Y g:i A');
    $canStartBoost = $order->status === \App\Support\OrderStatus::PENDING;
    $canCompleteOrder = in_array($order->status, [\App\Support\OrderStatus::IN_PROGRESS, \App\Support\OrderStatus::PAUSED], true);
    $showProgressControls = (bool) (
        ($progressForm['showCurrentRank'] ?? false)
        || ($progressForm['showCurrentRr'] ?? false)
        || ($progressForm['showCompletedWins'] ?? false)
        || ($progressForm['showCompletedPlacements'] ?? false)
    );

    $orderDetailItems = [
        ['label' => 'Order ID', 'value' => $overview['orderNumber'] ?? $order->order_number ?? $order->id],
        ['label' => 'Boost Type', 'value' => $overview['boostType'] ?? 'Not specified'],
        ['label' => 'Region', 'value' => $overview['region'] ?? 'Not specified'],
        ['label' => 'Start Rank', 'value' => $overview['startRank'] ?? 'Unranked'],
        ['label' => 'Desired Rank', 'value' => $overview['desiredRank'] ?? 'Unranked'],
        ['label' => 'Addons', 'type' => 'addons'],
        ['label' => 'Platform', 'value' => $overview['platform'] ?? 'PC'],
        ['label' => 'Payout', 'value' => $overview['payout'] ?? 'USD 0.00'],
    ];

    $customerDetailItems = [
        ['label' => 'Customer Status', 'value' => $customerProfile['status'] ?? 'Active'],
        ['label' => 'Joined', 'value' => $customerProfile['joinedAt'] ?? '-'],
    ];
@endphp

@extends('layouts.layout')

@section('title', 'GGWP Boost | Booster Chat')
@section('main_classes', 'site-main site-main--chat site-main--chat-user py-4')


@section('content')
<div class="ggwp-chat-shell ggwp-chat-shell--user ggwp-chat-shell--booster" data-order-chat-app data-chat-order-id="{{ $order->id }}" data-chat-user-id="{{ auth()->id() }}" data-chat-page-role="{{ auth()->user()->role }}" data-open-modal="{{ $openModal }}">
    <header class="ggwp-page-header ggwp-chat-page-header">
        <div class="ggwp-page-header__copy">
            <span class="ggwp-page-eyebrow">Booster workspace</span>
            <h1 class="h3 mb-1">Order #{{ $overview['orderNumber'] ?? $order->order_number ?? $order->id }}</h1>
            <div class="text-secondary">Update progress, upload completion proof, and keep customer or admin conversations in view.</div>
        </div>
        <div class="ggwp-page-actions">
            <a class="btn btn-outline-light" href="{{ route('booster-orders', ['view' => 'all']) }}">My orders</a>
            <a class="btn btn-outline-light" href="{{ route('booster-chats') }}">All chats</a>
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
                'footerMode' => 'booster',
                'workspaceNotices' => $workspaceNotices,
                'trackedCompletion' => $trackedCompletion,
                'completionProofTimestamp' => $completionProofTimestamp,
                'canStartBoost' => $canStartBoost,
                'canCompleteOrder' => $canCompleteOrder,
                'showProgressControls' => $showProgressControls,
                'progressForm' => $progressForm,
                'rankOptions' => $rankOptions,
            ])

            <section class="card app-card chat-panel-card chat-sidebar-summary-card chat-user-snapshot-card">
                <div class="card-body">
                    <div class="chat-user-snapshot-card__eyebrow">Order Details</div>
                    <div class="ggwp-detail-list ggwp-detail-list-compact chat-user-snapshot-list chat-user-snapshot-card__list">
                        @foreach($orderDetailItems as $item)
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
                                    <span class="ggwp-detail-value chat-user-snapshot-card__value">
                                        {{ $item['value'] }}
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="card app-card chat-panel-card chat-sidebar-card chat-user-booster-card">
                <div class="card-body">
                    <div class="chat-user-booster-card__eyebrow">Customer Brief</div>
                    <div class="ggwp-person-card chat-user-booster-card__identity">
                        <span class="ggwp-avatar-badge chat-user-booster-card__avatar">{{ strtoupper(substr($customerProfile['name'] ?? 'C', 0, 1)) }}</span>
                        <div class="chat-user-booster-card__copy">
                            <h3 class="chat-user-booster-card__name mb-0" data-order-bind="customerName">{{ $customerProfile['name'] ?? 'Customer' }}</h3>
                            <div class="chat-user-booster-card__meta" data-order-bind="customerEmail">{{ $customerProfile['email'] ?? 'Protected' }}</div>
                        </div>
                    </div>
                    <div class="ggwp-detail-list ggwp-detail-list-compact chat-user-booster-card__details">
                        @foreach($customerDetailItems as $item)
                            <div class="ggwp-detail-item chat-user-booster-card__detail-item">
                                <span class="ggwp-detail-label chat-user-booster-card__label">{{ $item['label'] }}</span>
                                <span class="ggwp-detail-value chat-user-booster-card__value">{{ $item['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="ggwp-note-box mt-3">
                        Customer notes: {{ $overview['notes'] ?? '-' }}
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
                'cardEyebrow' => 'ChatBox',
                'chatCardClass' => 'chat-main-card--workspace chat-main-card--user',
                'showContextStrip' => false,
                'showComposeNotice' => false,
                'inlineCompose' => true,
            ])
        </div>
    </div>
</div>

<div class="modal fade" id="boosterDropConfirmModal" tabindex="-1" aria-labelledby="boosterDropConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="boosterDropConfirmModalLabel">Drop Order</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('booster-orders.drop', ['order' => $order]) }}" data-loading-form data-static-captcha-form data-static-captcha-code="{{ $dropCaptchaCode }}">
                @csrf
                <div class="modal-body">
                    <p class="mb-2">Dropping this order will remove you from the assignment and return it to the pending queue.</p>
                    <p class="ggwp-modal-note mb-3">Enter the 4-digit captcha below before the order is unassigned.</p>

                    <div class="ggwp-claim-captcha-box mb-3">{{ $dropCaptchaCode }}</div>

                    <div class="mb-0">
                        <label class="form-label" for="dropCaptchaInput">4-digit captcha</label>
                        <input
                            type="text"
                            class="form-control"
                            id="dropCaptchaInput"
                            name="drop_captcha"
                            inputmode="numeric"
                            pattern="[0-9]{4}"
                            maxlength="4"
                            autocomplete="off"
                            required
                        >
                        <div class="invalid-feedback d-block d-none" data-static-captcha-feedback>Captcha code did not match.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" data-busy-label="Dropping...">Drop Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="boosterCompleteProofModal" tabindex="-1" aria-labelledby="boosterCompleteProofModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="boosterCompleteProofModalLabel">Upload Completion Proof</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('booster-orders.completion-proof.store', ['order' => $order]) }}" enctype="multipart/form-data" data-loading-form data-validate-form novalidate>
                @csrf
                <div class="modal-body">
                    <p class="mb-3">Upload the required completion screenshot first. After the upload succeeds, the captcha confirmation will open automatically.</p>

                    <div class="mb-0">
                        <label class="form-label" for="completionProofInput">Completion screenshot</label>
                        <input
                            type="file"
                            class="form-control"
                            id="completionProofInput"
                            name="completion_proof"
                            accept="image/png,image/jpeg,image/webp"
                            required
                        >
                        <div class="form-text">JPG, PNG, or WEBP up to 4MB.</div>
                    </div>

                    @if($completionProofTimestamp)
                        <div class="small text-secondary mt-3">Latest uploaded proof: {{ $completionProofTimestamp }}</div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" data-busy-label="Uploading...">Continue</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="boosterCompleteCaptchaModal" tabindex="-1" aria-labelledby="boosterCompleteCaptchaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="boosterCompleteCaptchaModalLabel">Confirm Completion</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('booster-orders.complete', ['order' => $order]) }}" data-loading-form data-static-captcha-form data-static-captcha-code="{{ $completeCaptchaCode }}">
                @csrf
                <div class="modal-body">
                    <p class="mb-2">This will mark the order as completed after the captcha is verified.</p>
                    @if($completionProofTimestamp)
                        <p class="text-secondary small mb-3">Proof uploaded {{ $completionProofTimestamp }}.</p>
                    @endif
                    <p class="ggwp-modal-note mb-3">Enter the 4-digit captcha below to finish the completion flow.</p>

                    <div class="ggwp-claim-captcha-box mb-3">{{ $completeCaptchaCode }}</div>

                    <div class="mb-0">
                        <label class="form-label" for="completeCaptchaInput">4-digit captcha</label>
                        <input
                            type="text"
                            class="form-control"
                            id="completeCaptchaInput"
                            name="complete_captcha"
                            inputmode="numeric"
                            pattern="[0-9]{4}"
                            maxlength="4"
                            autocomplete="off"
                            required
                        >
                        <div class="invalid-feedback d-block d-none" data-static-captcha-feedback>Captcha code did not match.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" data-busy-label="Completing...">Mark as Completed</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', () => {
  const forms = Array.from(document.querySelectorAll('[data-static-captcha-form]'));
  const resetModalForm = (form) => {
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    form.reset();
    form.classList.remove('was-validated');
    form.querySelectorAll('.is-invalid').forEach((field) => {
      field.classList.remove('is-invalid');
    });
    form.querySelectorAll('[data-static-captcha-feedback]').forEach((feedback) => {
      feedback.classList.add('d-none');
    });
  };

  forms.forEach((form) => {
    const expectedCode = form.getAttribute('data-static-captcha-code') || '';
    const input = form.querySelector('input[inputmode="numeric"]');
    const feedback = form.querySelector('[data-static-captcha-feedback]');

    if (!(input instanceof HTMLInputElement)) {
      return;
    }

    form.addEventListener('submit', (event) => {
      if ((input.value || '').trim() !== expectedCode) {
        event.preventDefault();
        input.classList.add('is-invalid');
        feedback?.classList.remove('d-none');
        input.focus();
      }
    });

    input.addEventListener('input', () => {
      input.value = input.value.replace(/\D+/g, '').slice(0, 4);

      if (input.classList.contains('is-invalid')) {
        input.classList.remove('is-invalid');
        feedback?.classList.add('d-none');
      }
    });
  });

  document.querySelectorAll('.modal').forEach((modal) => {
    modal.addEventListener('hidden.bs.modal', () => {
      modal.querySelectorAll('form').forEach(resetModalForm);
    });
  });
});
</script>
@endpush
