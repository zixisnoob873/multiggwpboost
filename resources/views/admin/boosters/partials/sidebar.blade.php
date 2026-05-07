<section class="card app-card booster-sidebar-card h-100">
  <div class="card-body">
    <div class="booster-card-intro">
      <span class="booster-card-eyebrow">Booster Status</span>
      <h2 class="h5 mb-1">Actions</h2>
      <p class="text-secondary mb-0">Review current status and use the controls below carefully.</p>
    </div>

    <div class="booster-sidebar-stack">
      <div class="booster-sidebar-box">
        <p class="text-secondary small mb-1">Nickname</p>
        <div class="fw-semibold">{{ $booster->publicIdentity('Booster') }}</div>
      </div>

      <div class="booster-sidebar-box">
        <p class="text-secondary small mb-1">Assigned orders</p>
        <div class="h3 mb-0">{{ $booster->booster_orders_count ?? 0 }}</div>
      </div>

      <div class="booster-sidebar-box">
        <p class="text-secondary small mb-2">Status</p>
        <span class="badge {{ $booster->account_status === 'suspended' ? 'text-bg-danger' : 'text-bg-success' }}">
          {{ ucfirst($booster->account_status ?? 'active') }}
        </span>
      </div>

      <div class="booster-sidebar-box">
        <p class="text-secondary small mb-1">Joined</p>
        <div class="fw-semibold">{{ $booster->created_at?->toDayDateTimeString() ?? '-' }}</div>
      </div>

      <div class="booster-sidebar-actions">
        <a class="btn btn-outline-light" href="{{ route('admin-boosters.show', ['booster' => $booster->nickname]) }}">Open Profile</a>
        <a class="btn btn-outline-light" href="{{ route('admin-boosters.edit', ['booster' => $booster->nickname]) }}">Edit Details</a>
        @if(auth()->user()?->canAccessAdminModule('finance'))
          <a class="btn btn-outline-light" href="{{ route('admin-withdrawal-requests.index') }}">Finance Queue</a>
        @endif
        <form action="{{ route('admin-boosters.status', $booster) }}" method="POST">
          @csrf
          @method('PATCH')
          <button class="btn {{ $booster->account_status === 'suspended' ? 'btn-success' : 'btn-warning' }} w-100" type="submit">
            {{ $booster->account_status === 'suspended' ? 'Activate Booster' : 'Suspend Booster' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</section>
