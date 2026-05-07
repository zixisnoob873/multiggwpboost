@php
    use Illuminate\Support\Str;

    $orders = $orders ?? collect();
    $customers = $customers ?? collect();
    $boosters = $boosters ?? collect();
    $statusOptions = $statusOptions ?? [];
    $paymentStatusOptions = $paymentStatusOptions ?? [];
    $serviceOptions = $ggwpServiceOptions ?? [];
    $gameOptions = ['VALORANT'];
    $rankOptions = $ggwpRankOptionsWithRadiant ?? [];
    $averageRrOptions = $ggwpAverageRrOptionChoices ?? [];
    $regionOptions = $ggwpRegions ?? [];
    $platformOptions = $ggwpPlatforms ?? [];
    $boostTypeOptions = $ggwpBoostModeOptions ?? [];
    $oldAddons = \App\Support\BoostingCatalog::normalizeAddons(old('addons', []));
    $oldSpecificAgents = \App\Support\ValorantAgentCatalog::normalizeSelection(old('specific_agents', []));
    $oldOneTrickAgent = \App\Support\ValorantAgentCatalog::normalizeSelection(old('one_trick_agent', []));
    $oldWinsNeeded = old('number_of_wins', 1);
    $oldPlacementGames = old('number_of_placement_games', 5);
@endphp

@extends('layouts.admin')

@section('title', 'GGWP Boost | Admin Custom Orders')


@section('admin_content')
<main class="ggwp-page-shell ggwp-page-shell--wide admin-page admin-page--dense">
  @include('admin.partials.page-header', [
    'title' => 'Custom Orders',
    'subtitle' => 'Create and review admin-entered orders.',
    'actions' => [
      ['label' => 'Dashboard', 'href' => route('admin-dashboard')],
      ['label' => 'All Orders', 'href' => route('admin-total-order')],
      ['label' => 'Chats', 'href' => route('admin-chats')],
    ],
  ])

  @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger">
      <div class="fw-semibold mb-1">The order could not be created.</div>
      <ul class="mb-0 ps-3">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="row g-2">
    <div class="col-xl-7">
      <section class="card app-card ggwp-panel-card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
            <h2 class="h5 mb-0">Create Manual Order</h2>
            <span class="badge text-bg-secondary">{{ number_format($customers->count()) }} customers</span>
          </div>

          <form method="POST" action="{{ route('admin-orders.store-manual') }}" class="d-grid gap-3" data-loading-form data-dirty-form data-validate-form novalidate>
            @csrf

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Customer owner</label>
                <select class="form-select @error('user_id') is-invalid @enderror" name="user_id" required>
                  <option value="">Select a customer</option>
                  @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((string) old('user_id') === (string) $customer->id)>
                      {{ $customer->publicIdentity('Customer') }}@if($customer->publicIdentity('Customer') !== $customer->fullIdentity('Customer')) ({{ $customer->fullIdentity('Customer') }})@endif - {{ $customer->email }}{{ $customer->account_status === 'suspended' ? ' [Suspended]' : '' }}
                    </option>
                  @endforeach
                </select>
                @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Assigned booster</label>
                <select class="form-select @error('booster_id') is-invalid @enderror" name="booster_id">
                  <option value="">Leave unassigned</option>
                  @foreach($boosters as $booster)
                    <option value="{{ $booster->id }}" @selected((string) old('booster_id') === (string) $booster->id)>
                      {{ $booster->publicIdentity('Booster') }}@if($booster->publicIdentity('Booster') !== $booster->fullIdentity('Booster')) ({{ $booster->fullIdentity('Booster') }})@endif - {{ $booster->email }}{{ $booster->account_status === 'suspended' ? ' [Suspended]' : '' }}
                    </option>
                  @endforeach
                </select>
                @error('booster_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="row g-3">
              <div class="col-md-8">
                <label class="form-label">Service / product</label>
                <select class="form-select @error('product') is-invalid @enderror" id="manualServiceProduct" name="product" required>
                  <option value="">Select service</option>
                  @foreach($serviceOptions as $serviceOption)
                    <option value="{{ $serviceOption }}" @selected(old('product', 'Rank Boosting') === $serviceOption)>{{ $serviceOption }}</option>
                  @endforeach
                </select>
                @error('product')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-4">
                <label class="form-label">Game</label>
                <select class="form-select @error('game') is-invalid @enderror" name="game">
                  <option value="">Select game</option>
                  @foreach($gameOptions as $gameOption)
                    <option value="{{ $gameOption }}" @selected(old('game', 'VALORANT') === $gameOption)>{{ $gameOption }}</option>
                  @endforeach
                </select>
                @error('game')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Admin Price (USD)</label>
                <input class="form-control @error('price') is-invalid @enderror" id="manualPriceInput" type="number" min="0" step="0.01" name="price" value="{{ old('price') }}" placeholder="Suggested total will autofill when available">
                @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-4">
                <label class="form-label">Status</label>
                <div class="form-control bg-body-tertiary">
                  Pending by default. Automatically switches to In Progress when a booster is assigned.
                </div>
              </div>

              <div class="col-md-4">
                <label class="form-label">Payment</label>
                <select class="form-select @error('payment_status') is-invalid @enderror" name="payment_status" required>
                  @foreach($paymentStatusOptions as $value => $label)
                    <option value="{{ $value }}" @selected(old('payment_status', 'paid') === $value)>{{ $label }}</option>
                  @endforeach
                </select>
                @error('payment_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="row g-3" id="manualRankFields">
              <div class="col-md-6">
                <label class="form-label">Current Rank</label>
                <select class="form-select @error('current_division') is-invalid @enderror" id="manualCurrentDivision" name="current_division">
                  <option value="">Select current rank</option>
                  @foreach($rankOptions as $rankOption)
                    <option value="{{ $rankOption }}" @selected(old('current_division', $ggwpDefaultCurrentRank ?? null) === $rankOption)>{{ $rankOption }}</option>
                  @endforeach
                </select>
                @error('current_division')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-6" id="manualDesiredDivisionWrap">
                <label class="form-label">Desired Rank</label>
                <select class="form-select @error('desired_division') is-invalid @enderror" id="manualDesiredDivision" name="desired_division">
                  <option value="">Select desired rank</option>
                  @foreach($rankOptions as $rankOption)
                    <option value="{{ $rankOption }}" @selected(old('desired_division', $ggwpDefaultDesiredRank ?? null) === $rankOption)>{{ $rankOption }}</option>
                  @endforeach
                </select>
                @error('desired_division')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="row g-3">
              <div class="col-md-3" id="manualCurrentRrWrap">
                <label class="form-label">Current RR</label>
                <input class="form-control @error('current_rr') is-invalid @enderror" id="manualCurrentRR" name="current_rr" value="{{ old('current_rr') }}" placeholder="52">
                @error('current_rr')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-3" id="manualAverageRrWrap">
                <label class="form-label">Average RR</label>
                <select class="form-select @error('average_rr') is-invalid @enderror" id="manualAverageRr" name="average_rr">
                  <option value="">Select average RR</option>
                  @foreach($averageRrOptions as $averageRrOption)
                    <option
                      value="{{ $averageRrOption['value'] }}"
                      @selected(in_array(old('average_rr'), [$averageRrOption['value'], $averageRrOption['label']], true))
                    >{{ $averageRrOption['label'] }}</option>
                  @endforeach
                </select>
                @error('average_rr')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-3 d-none" id="manualWinsWrap">
                <label class="form-label">Wins Needed</label>
                <input class="form-control @error('number_of_wins') is-invalid @enderror" id="manualWinsNeeded" type="number" min="1" max="5" step="1" name="number_of_wins" value="{{ $oldWinsNeeded }}">
                @error('number_of_wins')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-3 d-none" id="manualPlacementWrap">
                <label class="form-label">Placement Matches</label>
                <input class="form-control @error('number_of_placement_games') is-invalid @enderror" id="manualPlacementGames" type="number" min="1" max="5" step="1" name="number_of_placement_games" value="{{ $oldPlacementGames }}">
                @error('number_of_placement_games')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-3">
                <label class="form-label">Region</label>
                <select class="form-select @error('region') is-invalid @enderror" id="manualRegion" name="region">
                  <option value="">Select region</option>
                  @foreach($regionOptions as $regionOption)
                    <option value="{{ $regionOption }}" @selected(old('region') === $regionOption)>{{ $regionOption }}</option>
                  @endforeach
                </select>
                @error('region')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-3">
                <label class="form-label">Platform</label>
                <input class="form-control @error('platform') is-invalid @enderror" id="manualPlatform" name="platform" list="platformSuggestions" value="{{ old('platform', 'PC') }}" placeholder="PC">
                <datalist id="platformSuggestions">
                  @foreach($platformOptions as $platformOption)
                    <option value="{{ $platformOption }}"></option>
                  @endforeach
                </datalist>
                @error('platform')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Boost type</label>
                <select class="form-select @error('account_type') is-invalid @enderror" id="manualAccountType" name="account_type">
                  <option value="">Select boost type</option>
                  @foreach($boostTypeOptions as $boostTypeOption)
                    <option
                      value="{{ $boostTypeOption['value'] }}"
                      @selected(in_array(old('account_type'), [$boostTypeOption['value'], $boostTypeOption['label']], true))
                    >{{ $boostTypeOption['label'] }}</option>
                  @endforeach
                </select>
                @error('account_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-6 d-none d-md-block"></div>
            </div>

            <div>
                <label class="form-label">Addons</label>
                @include('partials.addon-options', [
                    'addons' => $ggwpAddons ?? [],
                    'context' => 'admin-manual',
                    'inputName' => 'addons',
                    'selected' => $oldAddons,
                    'serviceInputId' => 'manualServiceProduct',
                    'boostModeInputId' => 'manualAccountType',
                    'currentRankInputId' => 'manualCurrentDivision',
                    'targetRankInputId' => 'manualDesiredDivision',
                    'messageId' => 'manualAddonRulesMessage',
                    'specificAgentsInputName' => 'specific_agents',
                    'selectedSpecificAgents' => $oldSpecificAgents,
                    'specificAgentsErrorKey' => 'specific_agents',
                    'oneTrickAgentInputName' => 'one_trick_agent',
                    'selectedOneTrickAgent' => $oldOneTrickAgent,
                    'oneTrickAgentErrorKey' => 'one_trick_agent',
                    'allowAdminOverride' => true,
                ])
                @error('addons')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                @error('addons.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                @error('specific_agents.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                @error('one_trick_agent.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              </div>

            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Contact method</label>
                <select class="form-select @error('contact_method') is-invalid @enderror" name="contact_method">
                  <option value="email" @selected(old('contact_method', 'email') === 'email')>Email</option>
                  <option value="whatsapp" @selected(old('contact_method') === 'whatsapp')>WhatsApp</option>
                  <option value="discord" data-discord-option @selected(old('contact_method') === 'discord')>Discord</option>
                </select>
                @error('contact_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-4">
                <label class="form-label">WhatsApp</label>
                <input class="form-control @error('whatsapp') is-invalid @enderror" name="whatsapp" value="{{ old('whatsapp') }}" maxlength="255" placeholder="+1 555 123 4567">
                @error('whatsapp')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-4">
                <label class="form-label">Discord</label>
                <input class="form-control @error('discord') is-invalid @enderror" name="discord" value="{{ old('discord') }}" maxlength="255" placeholder="player#1234">
                @error('discord')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div>
              <label class="form-label">Internal notes</label>
              <textarea class="form-control @error('notes') is-invalid @enderror" name="notes" rows="4" maxlength="2000" placeholder="Special instructions, agreed pricing, queue notes...">{{ old('notes') }}</textarea>
              @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="rounded border p-3 bg-dark-subtle">
              <div class="d-flex justify-content-between small">
                <span class="text-secondary">Base</span>
                <span id="manualPricingBase">$0.00</span>
              </div>
              <div class="d-flex justify-content-between small mt-1">
                <span class="text-secondary">Addons</span>
                <span id="manualPricingAddons">$0.00</span>
              </div>
              <div class="d-flex justify-content-between small mt-1">
                <span class="text-secondary">After modifiers</span>
                <span id="manualPricingAfterModifiers">$0.00</span>
              </div>
              <div class="d-flex justify-content-between fw-semibold mt-2">
                <span>Customer-flow preview</span>
                <span id="manualPricingTotal">$0.00</span>
              </div>
              <div class="small text-secondary mt-2" id="manualPricingStatus">Choose a supported service setup to calculate the customer-flow preview, or enter a custom admin price to override it.</div>
            </div>

            <div class="d-flex flex-wrap gap-2">
              <button class="btn btn-danger" id="manualCreateOrderBtn" type="submit" data-busy-label="Creating...">Create Order</button>
              <a class="btn btn-outline-light" href="{{ route('admin-customers.create') }}">Create Customer</a>
              <a class="btn btn-outline-light" href="{{ route('admin-boosters.create') }}">Create Booster</a>
            </div>
          </form>
        </div>
      </section>
    </div>

    <div class="col-xl-5">
      <section class="card app-card h-100">
        <div class="card-body p-3">
          <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
            <h2 class="h5 mb-0">Recent Manual Orders</h2>
            <span class="badge text-bg-secondary">{{ number_format($orders->total()) }}</span>
          </div>

          <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0">
              <thead>
                <tr>
                  <th>Order</th>
                  <th>Customer</th>
                  <th>Status</th>
                  <th class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
                @forelse($orders as $order)
                  @php
                    $details = is_array($order->details) ? $order->details : (json_decode($order->details ?? '[]', true) ?? []);
                    $payload = is_array($details['order'] ?? null) ? $details['order'] : [];
                    $serviceName = $payload['orderType'] ?? $order->product ?? 'Order';
                  @endphp
                  <tr>
                    <td>
                      <div class="fw-semibold">{{ $order->order_number ?? $order->id }}</div>
                      <div class="text-secondary small">{{ $serviceName }}</div>
                      <div class="text-secondary small">{{ $order->created_at?->format('M j, Y H:i') ?? '—' }}</div>
                    </td>
                    <td>
                      <div class="fw-semibold">{{ $order->user?->publicIdentity('Unknown customer') ?? 'Unknown customer' }}</div>
                      <div class="text-secondary small">{{ $order->user?->email ?? '—' }}</div>
                      <div class="text-secondary small">
                        Booster:
                        {{ $order->booster?->publicIdentity('Unassigned') ?? $order->booster?->email ?? 'Unassigned' }}
                      </div>
                    </td>
                    <td>
                      <div>@include('partials.order-status-badge', ['status' => $order->status])</div>
                      <div class="text-secondary small mt-1">Payment: {{ Str::title($order->payment_status ?? 'pending') }}</div>
                    </td>
                    <td class="text-end">
                      <div class="fw-semibold">${{ number_format(($order->price_cents ?? 0) / 100, 2) }}</div>
                      <a class="btn btn-outline-light btn-sm mt-2" href="{{ route('admin-orders.edit', $order) }}">Edit</a>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="4" class="text-center text-secondary py-4">No manual orders have been created yet.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          @if(method_exists($orders, 'links'))
            <div class="mt-3">
              {{ $orders->withQueryString()->links('pagination::bootstrap-5') }}
            </div>
          @endif
        </div>
      </section>
    </div>
  </div>
</main>
@endsection
