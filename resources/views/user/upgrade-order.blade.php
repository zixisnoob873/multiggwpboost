@extends('layouts.layout')

@section('title', 'GGWP Boost | Upgrade / Extend Order')



@section('content')
<div class="ggwp-page-shell">
  <div id="upgradeAlert" class="alert d-none" role="alert"></div>

  <div class="ggwp-page-header mb-2">
    <div class="ggwp-page-header__copy">
      <span class="ggwp-page-eyebrow">Order upgrade</span>
      <h1 class="h3 mb-1">Extend / Upgrade Boost</h1>
      <div class="text-secondary">Select an active order, adjust options, and submit the upgrade.</div>
    </div>
    <div class="d-flex flex-wrap gap-2 ggwp-page-actions">
      <a class="btn btn-outline-light" href="{{ route('customer-dashboard') }}">Back to Dashboard</a>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-4">
      <div class="card app-card h-100">
        <div class="card-body">
          <h2 class="h5 mb-2">Select order</h2>
          <label class="form-label" for="orderSelect">Ongoing orders</label>
          <select class="form-select" id="orderSelect">
            <option value="">Loading...</option>
          </select>

          <div class="mt-2 p-2 rounded border">
            <div class="small text-secondary">Selected order</div>
            <div class="fw-semibold" id="selOrderTitle">-</div>
            <div class="small text-secondary mt-1" id="selOrderMeta">-</div>
            <div class="mt-2">
              <a class="btn btn-outline-light btn-sm ggwp-disabled-link" id="viewOrderBtn" href="#" aria-disabled="true">View details</a>
            </div>
          </div>

          <hr class="my-3">

          <h3 class="h6 mb-2">Upgrade type</h3>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="upgradeExtend" checked>
            <label class="form-check-label" for="upgradeExtend">Extend boost (increase target rank / RR)</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="upgradeAddons" checked>
            <label class="form-check-label" for="upgradeAddons">Add addons</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="upgradePriority">
            <label class="form-check-label" for="upgradePriority">Priority upgrade</label>
          </div>

          <hr class="my-3">

          <h3 class="h6 mb-2">Pricing</h3>
          <div class="d-flex justify-content-between small">
            <span class="text-secondary">Current total</span>
            <span id="currentTotal">$0.00</span>
          </div>
          <div class="d-flex justify-content-between small">
            <span class="text-secondary">New total</span>
            <span id="newTotal">$0.00</span>
          </div>
          <div class="d-flex justify-content-between small mt-2">
            <span class="fw-semibold">Additional due</span>
            <span class="fw-semibold" id="diffTotal">$0.00</span>
          </div>

          <button class="btn btn-danger w-100 mt-3" id="submitUpgradeBtn" disabled>Submit Upgrade</button>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-8">
      <div class="card app-card">
        <div class="card-body">
          <h2 class="h5 mb-2">Upgrade options</h2>

          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label" for="serviceType">Service type</label>
              <select class="form-select" id="serviceType">
                @foreach(($ggwpServiceOptions ?? []) as $serviceOption)
                  <option value="{{ $serviceOption }}">{{ $serviceOption }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="playType">Boost type</label>
              <select class="form-select" id="playType">
                @foreach(($ggwpBoostModeOptions ?? []) as $boostMode)
                  <option value="{{ $boostMode['value'] }}">{{ $boostMode['label'] }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="homeBoostRegion">Region</label>
              <select class="form-select" id="homeBoostRegion">
                @foreach(($ggwpRegions ?? []) as $region)
                  <option value="{{ $region }}">{{ $region }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="homeBoostPlatform">Platform</label>
              <select class="form-select" id="homeBoostPlatform">
                @foreach(($ggwpPlatforms ?? []) as $platform)
                  <option value="{{ $platform }}">{{ $platform }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="homeBoostCurrentDivision">From</label>
              <select class="form-select" id="homeBoostCurrentDivision">
                @foreach(($ggwpRankOptions ?? []) as $rankOption)
                  <option value="{{ $rankOption }}" @selected(($ggwpDefaultCurrentRank ?? null) === $rankOption)>{{ $rankOption }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="homeBoostDesiredDivision">To</label>
              <select class="form-select" id="homeBoostDesiredDivision">
                @foreach(($ggwpRankOptionsWithRadiant ?? []) as $rankOption)
                  <option value="{{ $rankOption }}" @selected(($ggwpDefaultDesiredRank ?? null) === $rankOption)>{{ $rankOption }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="averageRRgains">Average RR gain</label>
              <select class="form-select" id="averageRRgains">
                @foreach(($ggwpAverageRrOptionChoices ?? []) as $averageRrOption)
                  <option value="{{ $averageRrOption['value'] }}">{{ $averageRrOption['label'] }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="currentRR">Current RR</label>
              <input class="form-control" id="currentRR" type="number" min="0" max="100" inputmode="numeric">
            </div>

            <div class="col-12">
              <div class="form-label">Add-ons</div>
              @include('partials.addon-options', [
                'addons' => $ggwpAddons ?? [],
                'context' => 'upgrade-order',
                'serviceInputId' => 'serviceType',
                'boostModeInputId' => 'playType',
                'currentRankInputId' => 'homeBoostCurrentDivision',
                'targetRankInputId' => 'homeBoostDesiredDivision',
                'messageId' => 'upgradeAddonRulesMessage',
              ])
            </div>

            <div class="col-12">
              <label class="form-label" for="boostNotes">Notes</label>
              <textarea class="form-control" id="boostNotes" rows="3"></textarea>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
