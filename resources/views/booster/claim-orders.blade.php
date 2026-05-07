@php
    use Illuminate\Support\Str;

    $rankIcons = [
        'unranked' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/0/largeicon.png',
        'iron i' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/3/largeicon.png',
        'iron ii' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/4/largeicon.png',
        'iron iii' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/5/largeicon.png',
        'bronze i' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/6/largeicon.png',
        'bronze ii' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/7/largeicon.png',
        'bronze iii' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/8/largeicon.png',
        'silver i' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/9/largeicon.png',
        'silver ii' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/10/largeicon.png',
        'silver iii' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/11/largeicon.png',
        'gold i' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/12/largeicon.png',
        'gold ii' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/13/largeicon.png',
        'gold iii' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/14/largeicon.png',
        'platinum i' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/15/largeicon.png',
        'platinum ii' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/16/largeicon.png',
        'platinum iii' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/17/largeicon.png',
        'diamond i' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/18/largeicon.png',
        'diamond ii' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/19/largeicon.png',
        'diamond iii' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/20/largeicon.png',
        'ascendant i' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/21/largeicon.png',
        'ascendant ii' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/22/largeicon.png',
        'ascendant iii' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/23/largeicon.png',
        'immortal i' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/24/largeicon.png',
        'immortal ii' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/25/largeicon.png',
        'immortal iii' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/26/largeicon.png',
        'radiant' => 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/27/largeicon.png',
    ];

    $resolveRankIcon = static function (?string $value) use ($rankIcons): ?string {
        $cleaned = Str::of((string) $value)->lower()->squish()->toString();

        if ($cleaned === '') {
            return $rankIcons['unranked'];
        }

        if (isset($rankIcons[$cleaned])) {
            return $rankIcons[$cleaned];
        }

        $numeric = preg_replace_callback('/\b([123])\b/', static function (array $matches): string {
            return match ($matches[1]) {
                '1' => 'i',
                '2' => 'ii',
                default => 'iii',
            };
        }, $cleaned);

        if (is_string($numeric) && isset($rankIcons[$numeric])) {
            return $rankIcons[$numeric];
        }

        if (Str::contains($cleaned, 'radiant')) {
            return $rankIcons['radiant'];
        }

        return null;
    };
@endphp

@extends('layouts.layout')

@section('title', 'GGWP Boost | Claim Orders')



@section('content')
<div class="ggwp-page-shell">
  <div class="ggwp-page-header mb-2">
    <div class="ggwp-page-header__copy">
      <span class="ggwp-page-eyebrow">Claim queue</span>
      <h1 class="h3 mb-1">Claim Orders</h1>
      <div class="text-secondary">Unassigned pending orders ready to claim.</div>
    </div>
    <div class="ggwp-page-actions">
      <a class="btn btn-outline-light" href="{{ route('booster-claim-orders') }}">Claim Orders</a>
      <a class="btn btn-outline-light" href="{{ route('booster-orders', ['view' => 'all']) }}">My Orders</a>
      <a class="btn btn-outline-light" href="{{ route('booster-chats') }}">Chats</a>
      <a class="btn btn-outline-light" href="{{ route('booster-wallet') }}">Wallet</a>
      <a class="btn btn-outline-light" href="{{ route('booster-dashboard') }}">Profile</a>
    </div>
  </div>

  @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif

  @if($errors->has('claim'))
    <div class="alert alert-danger">{{ $errors->first('claim') }}</div>
  @endif

  <div class="card app-card ggwp-panel-card ggwp-panel-card--tight">
    <div class="card-body">
      <div class="row g-3 align-items-end mb-3 ggwp-filter-grid">
        <div class="col-md-6">
          <label class="form-label" for="claimSearch">Search available orders</label>
          <input class="form-control" id="claimSearch" placeholder="Search by order ID, service, rank, region, or addon">
        </div>
        <div class="col-md-3">
          <div class="text-secondary small">Available now</div>
          <div class="h5 mb-0">{{ ($availableOrders ?? collect())->count() }}</div>
        </div>
        <div class="col-md-3 text-md-end">
          <div class="text-secondary small">Default payout rate</div>
          <div class="fw-semibold">{{ number_format(\App\Models\Order::configuredBoosterPayoutPercentage(), 0) }}%</div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 ggwp-data-table ggwp-data-table--stacked">
          <thead>
            <tr>
              <th>Copy ID</th>
              <th>Service</th>
              <th>Rank From -> To</th>
              <th>Region</th>
              <th>Addons</th>
              <th>Est. payout</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="availableOrdersBody">
            @forelse($availableOrders as $order)
              @php
                $orderIdentifier = $order->order_number ?? $order->id;
                $searchToken = Str::lower(implode(' ', array_filter([
                    $orderIdentifier,
                    $order->serviceName(),
                    $order->rankFromLabel(),
                    $order->rankToLabel(),
                    $order->regionLabel(),
                    $order->addonsLabel(),
                ])));
                $payout = number_format(($order->resolvedBoosterPayoutCents() ?? 0) / 100, 2);
                $captchaCode = $claimCaptchaCodes[$order->id] ?? '0000';
                $fromIcon = $resolveRankIcon($order->rankFromLabel());
                $toIcon = $resolveRankIcon($order->rankToLabel());
              @endphp
              <tr data-search="{{ $searchToken }}">
                <td>
                  <button
                    type="button"
                    class="btn btn-outline-light btn-sm ggwp-copy-order-id"
                    data-order-id="{{ $orderIdentifier }}"
                  >
                    Copy ID
                  </button>
                </td>
                <td>{{ $order->serviceName() }}</td>
                <td>
                  <div class="ggwp-claim-rank-flow">
                    <span class="ggwp-claim-rank-chip">
                      <span class="ggwp-claim-rank-meta">
                        @if($fromIcon)
                          <img src="{{ $fromIcon }}" alt="{{ $order->rankFromLabel() }} icon" class="ggwp-claim-rank-icon" width="32" height="32" loading="lazy" decoding="async">
                        @endif
                        <span class="ggwp-claim-rank-copy">
                          <span class="ggwp-claim-rank-eyebrow">From</span>
                          <span class="ggwp-claim-rank-value">{{ $order->rankFromLabel() }}</span>
                        </span>
                      </span>
                    </span>
                    <span class="ggwp-claim-rank-arrow" aria-hidden="true">&rarr;</span>
                    <span class="ggwp-claim-rank-chip ggwp-claim-rank-chip--target">
                      <span class="ggwp-claim-rank-meta">
                        @if($toIcon)
                          <img src="{{ $toIcon }}" alt="{{ $order->rankToLabel() }} icon" class="ggwp-claim-rank-icon" width="32" height="32" loading="lazy" decoding="async">
                        @endif
                        <span class="ggwp-claim-rank-copy">
                          <span class="ggwp-claim-rank-eyebrow">To</span>
                          <span class="ggwp-claim-rank-value">{{ $order->rankToLabel() }}</span>
                        </span>
                      </span>
                    </span>
                  </div>
                </td>
                <td>{{ $order->regionLabel() }}</td>
                <td class="text-secondary small">{{ $order->addonsLabel() }}</td>
                <td>${{ $payout }}</td>
                <td class="text-end">
                  <button
                    type="button"
                    class="btn btn-outline-light btn-sm"
                    data-bs-toggle="modal"
                    data-bs-target="#claimConfirmModal"
                    data-claim-action="{{ route('booster-claim-orders.claim', $order) }}"
                    data-order-id="{{ $orderIdentifier }}"
                    data-service-name="{{ $order->serviceName() }}"
                    data-claim-captcha="{{ $captchaCode }}"
                  >
                    Claim
                  </button>
                </td>
              </tr>
            @empty
              <tr id="noAvailableOrdersRow">
                <td colspan="7" class="text-center text-secondary py-4 ggwp-table-empty">No unassigned pending orders right now.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="claimConfirmModal" tabindex="-1" aria-labelledby="claimConfirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title fs-5" id="claimConfirmModalLabel">Confirm Claim</h2>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" id="claimConfirmForm">
        @csrf
        <div class="modal-body">
          <p class="mb-2">
            Claiming
            <strong data-claim-modal-service>this order</strong>
            for order
            <strong data-claim-modal-order>#0000</strong>.
          </p>
          <p class="ggwp-modal-note mb-3">Enter the 4-digit captcha below before the order is assigned to you.</p>

          <div class="ggwp-claim-captcha-box mb-3" data-claim-modal-captcha-display>0000</div>

          <div class="mb-0">
            <label class="form-label" for="claimCaptchaInput">4-digit captcha</label>
            <input
              type="text"
              class="form-control"
              id="claimCaptchaInput"
              name="claim_captcha"
              inputmode="numeric"
              pattern="[0-9]{4}"
              maxlength="4"
              autocomplete="off"
              required
            >
            <div class="invalid-feedback d-block d-none" id="claimCaptchaFeedback">Captcha code did not match.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Claim Order</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('claimSearch');
  const rows = Array.from(document.querySelectorAll('#availableOrdersBody tr[data-search]'));
  const claimModal = document.getElementById('claimConfirmModal');
  const claimForm = document.getElementById('claimConfirmForm');
  const claimCaptchaInput = document.getElementById('claimCaptchaInput');
  const claimCaptchaFeedback = document.getElementById('claimCaptchaFeedback');
  const claimOrderTarget = claimModal?.querySelector('[data-claim-modal-order]');
  const claimServiceTarget = claimModal?.querySelector('[data-claim-modal-service]');
  const claimCaptchaTarget = claimModal?.querySelector('[data-claim-modal-captcha-display]');
  const copyButtons = Array.from(document.querySelectorAll('.ggwp-copy-order-id'));
  let activeCaptcha = '';

  const copyText = async (value) => {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(value);
      return;
    }

    const fallbackInput = document.createElement('input');
    fallbackInput.value = value;
    document.body.appendChild(fallbackInput);
    fallbackInput.select();
    document.execCommand('copy');
    fallbackInput.remove();
  };

  copyButtons.forEach((button) => {
    button.addEventListener('click', async () => {
      const originalLabel = button.textContent;

      try {
        await copyText(button.dataset.orderId || '');
        button.textContent = 'Copied';
      } catch {
        button.textContent = 'Try Again';
      }

      window.setTimeout(() => {
        button.textContent = originalLabel;
      }, 1500);
    });
  });

  if (claimModal && claimForm && claimCaptchaInput && claimCaptchaFeedback) {
    claimModal.addEventListener('show.bs.modal', (event) => {
      const trigger = event.relatedTarget;

      if (!(trigger instanceof HTMLElement)) {
        return;
      }

      activeCaptcha = trigger.dataset.claimCaptcha || '';
      claimForm.action = trigger.dataset.claimAction || '';
      claimCaptchaInput.value = '';
      claimCaptchaInput.classList.remove('is-invalid');
      claimCaptchaFeedback.classList.add('d-none');

      if (claimOrderTarget) {
        claimOrderTarget.textContent = `#${trigger.dataset.orderId || '0000'}`;
      }

      if (claimServiceTarget) {
        claimServiceTarget.textContent = trigger.dataset.serviceName || 'this order';
      }

      if (claimCaptchaTarget) {
        claimCaptchaTarget.textContent = activeCaptcha || '0000';
      }
    });

    claimForm.addEventListener('submit', (event) => {
      if ((claimCaptchaInput.value || '').trim() !== activeCaptcha) {
        event.preventDefault();
        claimCaptchaInput.classList.add('is-invalid');
        claimCaptchaFeedback.classList.remove('d-none');
        claimCaptchaInput.focus();
      }
    });

    claimCaptchaInput.addEventListener('input', () => {
      claimCaptchaInput.value = claimCaptchaInput.value.replace(/\D+/g, '').slice(0, 4);

      if (claimCaptchaInput.classList.contains('is-invalid')) {
        claimCaptchaInput.classList.remove('is-invalid');
        claimCaptchaFeedback.classList.add('d-none');
      }
    });
  }

  if (!searchInput || !rows.length) {
    return;
  }

  const noResultsRow = document.createElement('tr');
  noResultsRow.className = 'd-none';
  noResultsRow.innerHTML = '<td colspan="7" class="text-center text-secondary py-4">No orders match your search.</td>';
  document.getElementById('availableOrdersBody')?.appendChild(noResultsRow);

  const applySearch = () => {
    const query = searchInput.value.trim().toLowerCase();
    let visibleCount = 0;

    rows.forEach((row) => {
      const visible = !query || (row.dataset.search || '').includes(query);
      row.classList.toggle('d-none', !visible);
      if (visible) {
        visibleCount += 1;
      }
    });

    noResultsRow.classList.toggle('d-none', visibleCount !== 0);
  };

  searchInput.addEventListener('input', applySearch);
});
</script>
@endpush
