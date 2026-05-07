@php
    $rankTrackerActions = $rankTrackerActions ?? [];
    $extensionModal = $extensionModal ?? [];
    $paymentProviders = collect($paymentProviders ?? [])
        ->filter(fn (array $provider) => (bool) ($provider['isAvailable'] ?? true) && (bool) ($provider['isConfigured'] ?? true))
        ->values()
        ->all();
    $defaultPaymentProvider = $defaultPaymentProvider ?? ($paymentProviders[0] ?? null);
    $extensionField = $extensionModal['field'] ?? null;
    $extensionFieldName = is_array($extensionField) ? (string) ($extensionField['name'] ?? 'extension') : 'extension';
    $pauseTargetStatus = (string) ($rankTrackerActions['pauseTargetStatus'] ?? '');
    $pauseRoute = $pauseTargetStatus === \App\Support\OrderStatus::PAUSED
        ? route('customer-orders.pause', ['order' => $order])
        : route('customer-orders.resume', ['order' => $order]);
    $pauseTitle = $pauseTargetStatus === \App\Support\OrderStatus::PAUSED ? 'Pause Boost' : 'Continue Boost';
    $pauseDescription = $pauseTargetStatus === \App\Support\OrderStatus::PAUSED
        ? 'Pausing this order updates the canonical order state immediately and tells the booster to contact admin before continuing.'
        : 'Continuing this order returns it to the live workspace immediately so the booster can resume execution.';
@endphp

<div class="modal fade chat-detail-modal rank-tracker-modal" id="extendBoostModal" tabindex="-1" aria-labelledby="extendBoostModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <div class="small text-uppercase fw-semibold text-secondary">Rank Tracker</div>
                    <h2 class="modal-title h4 mb-0" id="extendBoostModalTitle">{{ $extensionModal['title'] ?? 'Extend Boost' }}</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                @if(!empty($extensionModal['description']))
                    <div class="ggwp-note-box rank-tracker-modal__intro">
                        {{ $extensionModal['description'] }}
                    </div>
                @endif

                @if(!empty($extensionModal['summaryLabel']) || !empty($extensionModal['summaryValue']))
                    <div class="ggwp-detail-item rank-tracker-modal__summary-item mb-3">
                        <span class="ggwp-detail-label">{{ $extensionModal['summaryLabel'] ?? 'Current scope' }}</span>
                        <span class="ggwp-detail-value">{{ $extensionModal['summaryValue'] ?? '—' }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('customer-orders.extend.checkout', ['order' => $order]) }}" class="rank-tracker-modal__form" data-validate-form novalidate>
                    @csrf

                    @if(is_array($extensionField))
                        <div class="mb-3">
                            <label class="form-label" for="rank-tracker-extension-field">{{ $extensionField['label'] ?? 'Extension option' }}</label>
                            @if(($extensionField['type'] ?? 'text') === 'select')
                                <select
                                    id="rank-tracker-extension-field"
                                    class="form-select @error($extensionFieldName) is-invalid @enderror"
                                    name="{{ $extensionFieldName }}"
                                >
                                    @foreach(($extensionField['options'] ?? []) as $option)
                                        <option value="{{ $option }}" @selected(old($extensionFieldName, $extensionField['value'] ?? null) === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input
                                    id="rank-tracker-extension-field"
                                    type="{{ $extensionField['type'] ?? 'text' }}"
                                    class="form-control @error($extensionFieldName) is-invalid @enderror"
                                    name="{{ $extensionFieldName }}"
                                    value="{{ old($extensionFieldName, $extensionField['value'] ?? null) }}"
                                    min="{{ $extensionField['min'] ?? null }}"
                                    max="{{ $extensionField['max'] ?? null }}"
                                    step="{{ $extensionField['step'] ?? null }}"
                                >
                            @endif
                            @error($extensionFieldName)
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            @if(!empty($extensionField['help']))
                                <div class="form-text text-secondary">{{ $extensionField['help'] }}</div>
                            @endif
                        </div>
                    @endif

                    <div class="rank-tracker-modal__payment-block">
                        <div class="small text-uppercase fw-semibold text-secondary mb-2">Payment Method</div>
                        @if(count($paymentProviders))
                            <div class="rank-tracker-modal__payment-grid">
                                @foreach($paymentProviders as $provider)
                                    <label class="rank-tracker-modal__provider">
                                        <input
                                            class="form-check-input"
                                            type="radio"
                                            name="paymentMethod"
                                            value="{{ $provider['key'] }}"
                                            @checked(old('paymentMethod', $defaultPaymentProvider['key'] ?? null) === ($provider['key'] ?? null))
                                        >
                                        <span class="rank-tracker-modal__provider-copy">
                                            <span class="rank-tracker-modal__provider-title">{{ $provider['label'] }}</span>
                                            <span class="rank-tracker-modal__provider-meta">{{ $provider['description'] }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="form-text text-secondary mt-2">{{ $defaultPaymentProvider['notice'] ?? 'Select a payment provider to continue.' }}</div>
                        @else
                            <div class="alert alert-secondary small mb-0">Payments are unavailable right now, so this extension cannot be started yet.</div>
                        @endif
                        @error('paymentMethod')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        @error('payment')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        @error('extension')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="modal-footer border-0 px-0 pb-0 pt-3">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger" @disabled(!($rankTrackerActions['canExtend'] ?? false) || !count($paymentProviders) || empty($extensionModal['canSubmit']))>
                            Continue to Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade chat-detail-modal rank-tracker-modal" id="pauseBoostModal" tabindex="-1" aria-labelledby="pauseBoostModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <div class="small text-uppercase fw-semibold text-secondary">Rank Tracker</div>
                    <h2 class="modal-title h4 mb-0" id="pauseBoostModalTitle">{{ $pauseTitle }}</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="ggwp-note-box rank-tracker-modal__intro">
                    {{ $pauseDescription }}
                </div>
                @error('order')
                    <div class="invalid-feedback d-block mt-2">{{ $message }}</div>
                @enderror

                <form method="POST" action="{{ $pauseRoute }}" class="modal-footer border-0 px-0 pb-0 pt-3">
                    @csrf
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger" @disabled(!($rankTrackerActions['canPauseToggle'] ?? false))>
                        {{ $rankTrackerActions['pauseLabel'] ?? 'Pause Boost' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade chat-detail-modal rank-tracker-modal" id="tipBoosterModal" tabindex="-1" aria-labelledby="tipBoosterModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <div class="small text-uppercase fw-semibold text-secondary">Rank Tracker</div>
                    <h2 class="modal-title h4 mb-0" id="tipBoosterModalTitle">Tip Booster</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="ggwp-note-box rank-tracker-modal__intro">
                    Send a direct order-linked tip to your assigned booster. The paid tip is recorded against this order and added to the booster wallet balance.
                </div>

                <form method="POST" action="{{ route('customer-orders.tips.booster.checkout', ['order' => $order]) }}" class="rank-tracker-modal__form" data-validate-form novalidate>
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="tip-booster-amount">Tip amount (USD)</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input
                                id="tip-booster-amount"
                                type="number"
                                min="1"
                                max="1000"
                                step="0.01"
                                class="form-control @error('amount') is-invalid @enderror"
                                name="amount"
                                value="{{ old('amount', '10.00') }}"
                            >
                        </div>
                        @error('amount')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="rank-tracker-modal__payment-block">
                        <div class="small text-uppercase fw-semibold text-secondary mb-2">Payment Method</div>
                        @if(count($paymentProviders))
                            <div class="rank-tracker-modal__payment-grid">
                                @foreach($paymentProviders as $provider)
                                    <label class="rank-tracker-modal__provider">
                                        <input
                                            class="form-check-input"
                                            type="radio"
                                            name="paymentMethod"
                                            value="{{ $provider['key'] }}"
                                            @checked(old('paymentMethod', $defaultPaymentProvider['key'] ?? null) === ($provider['key'] ?? null))
                                        >
                                        <span class="rank-tracker-modal__provider-copy">
                                            <span class="rank-tracker-modal__provider-title">{{ $provider['label'] }}</span>
                                            <span class="rank-tracker-modal__provider-meta">{{ $provider['description'] }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <div class="alert alert-secondary small mb-0">Payments are unavailable right now, so tipping is unavailable.</div>
                        @endif
                        @error('paymentMethod')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        @error('payment')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="modal-footer border-0 px-0 pb-0 pt-3">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger" @disabled(!($rankTrackerActions['canTipBooster'] ?? false) || !count($paymentProviders))>
                            Continue to Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade chat-detail-modal rank-tracker-modal" id="tipAdminModal" tabindex="-1" aria-labelledby="tipAdminModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <div class="small text-uppercase fw-semibold text-secondary">Rank Tracker</div>
                    <h2 class="modal-title h4 mb-0" id="tipAdminModalTitle">Tip Admin</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="ggwp-note-box rank-tracker-modal__intro">
                    Send a separate admin tip tied to this order. It is tracked in admin finances on its own and does not change existing order revenue metrics.
                </div>

                <form method="POST" action="{{ route('customer-orders.tips.admin.checkout', ['order' => $order]) }}" class="rank-tracker-modal__form" data-validate-form novalidate>
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="tip-admin-amount">Tip amount (USD)</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input
                                id="tip-admin-amount"
                                type="number"
                                min="1"
                                max="1000"
                                step="0.01"
                                class="form-control @error('amount') is-invalid @enderror"
                                name="amount"
                                value="{{ old('amount', '10.00') }}"
                            >
                        </div>
                        @error('amount')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="rank-tracker-modal__payment-block">
                        <div class="small text-uppercase fw-semibold text-secondary mb-2">Payment Method</div>
                        @if(count($paymentProviders))
                            <div class="rank-tracker-modal__payment-grid">
                                @foreach($paymentProviders as $provider)
                                    <label class="rank-tracker-modal__provider">
                                        <input
                                            class="form-check-input"
                                            type="radio"
                                            name="paymentMethod"
                                            value="{{ $provider['key'] }}"
                                            @checked(old('paymentMethod', $defaultPaymentProvider['key'] ?? null) === ($provider['key'] ?? null))
                                        >
                                        <span class="rank-tracker-modal__provider-copy">
                                            <span class="rank-tracker-modal__provider-title">{{ $provider['label'] }}</span>
                                            <span class="rank-tracker-modal__provider-meta">{{ $provider['description'] }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <div class="alert alert-secondary small mb-0">Payments are unavailable right now, so tipping is unavailable.</div>
                        @endif
                        @error('paymentMethod')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        @error('payment')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="modal-footer border-0 px-0 pb-0 pt-3">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger" @disabled(!($rankTrackerActions['canTipAdmin'] ?? false) || !count($paymentProviders))>
                            Continue to Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
