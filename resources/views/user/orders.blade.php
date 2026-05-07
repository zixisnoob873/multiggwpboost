@php($lifetimeSpend = number_format(($lifetimeSpendCents ?? 0) / 100, 2))

@extends('layouts.layout')

@section('title', 'GGWP Boost | All Orders')



@section('content')
<div class="ggwp-page-shell">
  <div class="ggwp-page-header mb-2">
    <div class="ggwp-page-header__copy">
      <span class="ggwp-page-eyebrow">Customer orders</span>
      <h1 class="mb-1">Orders</h1>
      <div class="text-secondary">Filter order history, review charged totals, and reopen eligible boost workspaces.</div>
    </div>
    <div class="ggwp-page-actions">
      <a class="btn btn-outline-light" href="{{ route('home') }}">Start New Boost</a>
      <a class="btn btn-outline-light" href="{{ route('customer-dashboard') }}">Dashboard</a>
    </div>
  </div>

  <div class="row g-2 ggwp-metric-grid mb-3">
    <div class="col-md-3">
      <div class="card app-card h-100 ggwp-panel-card">
        <div class="card-body">
          <p class="text-secondary small mb-1">Total orders</p>
          <div class="h3 mb-0" id="mTotalOrders">{{ $totalOrders ?? 0 }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card app-card h-100 ggwp-panel-card">
        <div class="card-body">
          <p class="text-secondary small mb-1">Active</p>
          <div class="h3 mb-0" id="mActiveOrders">{{ $activeOrders ?? 0 }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card app-card h-100 ggwp-panel-card">
        <div class="card-body">
          <p class="text-secondary small mb-1">Completed</p>
          <div class="h3 mb-0" id="mCompletedOrders">{{ $completedOrders ?? 0 }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card app-card h-100 ggwp-panel-card">
        <div class="card-body">
          <p class="text-secondary small mb-1">Lifetime spend</p>
          <div class="h3 mb-0" id="mLifetimeSpend">${{ $lifetimeSpend }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-2 align-items-end mb-3 ggwp-filter-grid">
    <div class="col-md-3">
      <label class="form-label" for="statusFilter">Status</label>
      <select class="form-select" id="statusFilter">
        <option value="all" selected>All</option>
        <option value="Pending">Pending</option>
        <option value="InProgress">In Progress</option>
        <option value="Paused">Paused</option>
        <option value="Completed">Completed</option>
        <option value="Cancelled">Cancelled</option>
        <option value="Refunded">Refunded</option>
      </select>
    </div>

    <div class="col-md-6">
      <label class="form-label" for="searchInput">Search</label>
      <input class="form-control" id="searchInput" placeholder="Order ID, service, or rank">
    </div>

    <div class="col-md-3 d-grid">
      <button class="btn btn-outline-light" type="button" id="resetBtn">Reset</button>
    </div>
  </div>

  <section class="card app-card ggwp-panel-card ggwp-panel-card--tight">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0 ggwp-data-table ggwp-data-table--stacked">
          <thead>
            <tr>
              <th>Order ID</th>
              <th>Created</th>
              <th>Service</th>
              <th>Status</th>
              <th class="text-end">Charged Total</th>
              <th class="text-end">Open</th>
            </tr>
          </thead>
          <tbody id="ordersBody">
            @forelse($orders as $order)
              @php($total = number_format(($order->customerPriceCents() ?? 0) / 100, 2))
              <tr>
                <td class="fw-semibold">#{{ $order->order_number ?? $order->id }}</td>
                <td>{{ $order->created_at?->format('M j, Y H:i') ?? '-' }}</td>
                <td>{{ $order->serviceName() }}</td>
                <td>@include('partials.order-status-badge', ['status' => $order->status])</td>
                <td class="text-end">
                  <div>${{ $total }}</div>
                  @if($order->hasDiscountApplied())
                    <div class="small text-secondary">Original ${{ number_format($order->resolvedOriginalPriceCents() / 100, 2) }}</div>
                    <div class="small text-secondary">Promo -${{ number_format($order->resolvedDiscountAmountCents() / 100, 2) }}</div>
                  @endif
                </td>
                <td class="text-end">
                  <a class="btn btn-outline-light btn-sm" href="{{ route('user-chats.show', ['order' => $order]) }}">Open</a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-center text-secondary py-4 ggwp-table-empty">No orders were found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </section>
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
(() => {
  const INITIAL_ORDERS = @json($ordersData ?? []);
  const USER_CHAT_URL_TEMPLATE = @json(route('user-chats.show', ['order' => '__ORDER_NUMBER__']));

  const fmtMoney = (cents, currency = 'USD') => {
    const amount = Number(cents || 0) / 100;
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency,
      minimumFractionDigits: 2,
    }).format(amount);
  };

  const parseDate = (value) => {
    const parsed = value ? new Date(value) : null;
    return parsed instanceof Date && !Number.isNaN(parsed.getTime()) ? parsed : null;
  };

  const prettyStatus = (status) => status === 'InProgress' ? 'In Progress' : (status || '-');

  const badgeClass = (status) => {
    const normalized = String(status || '').toLowerCase();
    if (normalized.includes('pending')) return 'text-bg-secondary';
    if (normalized.includes('inprogress') || normalized.includes('progress')) return 'text-bg-warning';
    if (normalized.includes('paused')) return 'text-bg-info';
    if (normalized.includes('complete')) return 'text-bg-success';
    if (normalized.includes('cancel')) return 'text-bg-danger';
    if (normalized.includes('refund')) return 'text-bg-dark';
    return 'text-bg-primary';
  };

  const normalizeOrder = (order) => ({
    id: order.id,
    orderNumber: order.orderNumber || '',
    createdAt: order.createdAt || null,
    status: order.status || '',
    service: order.serviceLabel || order.product || '-',
    from: order.rankFrom || '',
    to: order.rankTo || '',
    priceCents: Number(order.priceCents || 0),
    originalPriceCents: Number(order.originalPriceCents || order.priceCents || 0),
    discountAmountCents: Number(order.discountAmountCents || 0),
    currency: order.currency || 'USD',
  });

  const applyFilters = (orders) => {
    const status = document.getElementById('statusFilter')?.value || 'all';
    const query = (document.getElementById('searchInput')?.value || '').trim().toLowerCase();

    return orders.filter((order) => {
      const matchesStatus = status === 'all' ? true : order.status === status;
      const haystack = `${order.orderNumber} ${order.service} ${order.from} ${order.to}`.toLowerCase();
      const matchesQuery = !query || haystack.includes(query);

      return matchesStatus && matchesQuery;
    });
  };

  const renderMetrics = (orders) => {
    const totalOrders = orders.length;
    const completedOrders = orders.filter((order) => String(order.status).toLowerCase().includes('complete')).length;
    const activeOrders = orders.filter((order) => {
      const status = String(order.status).toLowerCase();
      return status.includes('pending') || status.includes('inprogress') || status.includes('progress') || status.includes('paused');
    }).length;
    const lifetimeSpend = orders
      .filter((order) => {
        const status = String(order.status).toLowerCase();
        return !status.includes('cancel') && !status.includes('refund');
      })
      .reduce((sum, order) => sum + Number(order.priceCents || 0), 0);

    const setText = (id, value) => {
      const element = document.getElementById(id);
      if (element) {
        element.textContent = value;
      }
    };

    setText('mTotalOrders', String(totalOrders));
    setText('mActiveOrders', String(activeOrders));
    setText('mCompletedOrders', String(completedOrders));
    setText('mLifetimeSpend', fmtMoney(lifetimeSpend));
  };

  const renderTable = (orders) => {
    const tbody = document.getElementById('ordersBody');
    if (!tbody) {
      return;
    }

    const buildEmptyRow = () => {
      const row = document.createElement('tr');
      const cell = document.createElement('td');
      cell.colSpan = 6;
      cell.className = 'py-4 text-center text-secondary';
      cell.textContent = 'No orders match the current filters.';
      row.append(cell);

      return row;
    };

    const buildOrderRow = (order) => {
      const row = document.createElement('tr');
      const orderCell = document.createElement('td');
      const createdAtCell = document.createElement('td');
      const serviceCell = document.createElement('td');
      const statusCell = document.createElement('td');
      const priceCell = document.createElement('td');
      const actionCell = document.createElement('td');
      const statusBadge = document.createElement('span');
      const openLink = document.createElement('a');

      orderCell.className = 'fw-semibold';
      orderCell.textContent = `#${order.orderNumber}`;

      createdAtCell.className = 'text-secondary small';
      createdAtCell.textContent = parseDate(order.createdAt)?.toLocaleString() || '-';

      serviceCell.textContent = order.service || '-';

      statusBadge.className = `badge ${badgeClass(order.status)}`;
      statusBadge.textContent = prettyStatus(order.status);
      statusCell.append(statusBadge);

      priceCell.className = 'text-end';
      priceCell.innerHTML = '';
      const totalValue = document.createElement('div');
      totalValue.textContent = fmtMoney(order.priceCents, order.currency);
      priceCell.append(totalValue);

      if (order.discountAmountCents > 0) {
        const originalValue = document.createElement('div');
        const discountValue = document.createElement('div');
        originalValue.className = 'small text-secondary';
        discountValue.className = 'small text-secondary';
        originalValue.textContent = `Original ${fmtMoney(order.originalPriceCents, order.currency)}`;
        discountValue.textContent = `Promo ${fmtMoney(order.discountAmountCents * -1, order.currency)}`;
        priceCell.append(originalValue, discountValue);
      }

      actionCell.className = 'text-end';
      openLink.className = 'btn btn-outline-light btn-sm';
      openLink.href = USER_CHAT_URL_TEMPLATE.replace('__ORDER_NUMBER__', encodeURIComponent(order.orderNumber || ''));
      openLink.textContent = 'Open';
      actionCell.append(openLink);

      row.append(orderCell, createdAtCell, serviceCell, statusCell, priceCell, actionCell);

      return row;
    };

    if (!orders.length) {
      tbody.replaceChildren(buildEmptyRow());
      window.ggwpApplyResponsiveTableLabels?.(tbody.closest('.table-responsive') || document);
      return;
    }

    const rows = orders
      .slice()
      .sort((left, right) => (parseDate(right.createdAt)?.getTime() || 0) - (parseDate(left.createdAt)?.getTime() || 0))
      .map(buildOrderRow);

    tbody.replaceChildren(...rows);
    window.ggwpApplyResponsiveTableLabels?.(tbody.closest('.table-responsive') || document);
  };

  const orders = (Array.isArray(INITIAL_ORDERS) ? INITIAL_ORDERS : []).map(normalizeOrder);
  const rerender = () => {
    renderMetrics(orders);
    renderTable(applyFilters(orders));
  };

  document.getElementById('statusFilter')?.addEventListener('change', rerender);
  document.getElementById('searchInput')?.addEventListener('input', rerender);
  document.getElementById('resetBtn')?.addEventListener('click', () => {
    const statusFilter = document.getElementById('statusFilter');
    const searchInput = document.getElementById('searchInput');

    if (statusFilter) {
      statusFilter.value = 'all';
    }

    if (searchInput) {
      searchInput.value = '';
    }

    rerender();
  });

  rerender();
})();
</script>
@endpush
