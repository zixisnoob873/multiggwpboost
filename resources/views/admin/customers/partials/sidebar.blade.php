<section class="card app-card h-100">
  <div class="card-body">
    <p class="text-secondary small mb-1">Nickname</p>
    <div class="mb-3 fw-semibold">{{ $customer->publicIdentity('Customer') }}</div>
    <p class="text-secondary small mb-1">Total orders</p>
    <div class="h3 mb-2">{{ $customer->orders_count ?? 0 }}</div>
    <p class="text-secondary small mb-1">Status</p>
    <div class="mb-3">
      <span class="badge {{ $customer->account_status === 'suspended' ? 'text-bg-danger' : 'text-bg-success' }}">
        {{ ucfirst($customer->account_status ?? 'active') }}
      </span>
    </div>
    <p class="text-secondary small mb-1">Joined</p>
    <div class="mb-3">{{ $customer->created_at?->toDayDateTimeString() ?? '-' }}</div>

    <div class="d-grid gap-2 mb-3">
      <a class="btn btn-outline-light" href="{{ route('admin-customers.show', $customer) }}">Open Profile</a>
      <a class="btn btn-outline-light" href="{{ route('admin-customers.edit', $customer) }}">Edit Details</a>
    </div>

    <form action="{{ route('admin-customers.status', $customer) }}" method="POST" class="mb-0">
      @csrf
      @method('PATCH')
      <button class="btn {{ $customer->account_status === 'suspended' ? 'btn-success' : 'btn-warning' }} w-100" type="submit">
        {{ $customer->account_status === 'suspended' ? 'Activate Customer' : 'Suspend Customer' }}
      </button>
    </form>
  </div>
</section>
