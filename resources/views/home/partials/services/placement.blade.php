@php($serviceType = $serviceType ?? 'Placement Matches')
<div class="tab-pane fade @if($isActiveServiceTab ?? false) show active @endif" id="{{ $serviceTab['pane_id'] ?? 'pane-placement' }}" role="tabpanel" aria-labelledby="{{ $serviceTab['tab_id'] ?? 'tab-placement' }}">
  <div class="ggwp-service-pricing-grid">
    <section class="ggwp-service-pricing-grid__config" aria-labelledby="placementSetupHeading">
      <div class="card app-card ggwp-service-config-card">
        <div class="card-body p-4 p-xl-5">
          <h3 id="placementSetupHeading" class="h4 mb-3">{{ $gameShortName ?? 'VALORANT' }} Placement Boost Setup</h3>
          <p class="text-secondary mb-4">Set your previous act rank, placement volume, and queue preferences for a clear {{ $gameShortName ?? 'VALORANT' }} boost quote.</p>
          <div class="row g-4">
            <div class="col-md-6">
              @include('home.partials.rank-picker-field', [
                'id' => 'homePlacementLastTier',
                'label' => 'Previous act rank',
                'options' => $rankOptions,
                'selected' => $ggwpDefaultCurrentRank ?? null,
                'modalTitle' => 'Choose your previous act rank',
                'placeholder' => 'Choose previous act rank',
              ])
            </div>
            <div class="col-md-6"><label class="form-label" for="homePlacementGames">Placement games</label><input class="form-control" id="homePlacementGames" type="number" min="1" max="5" step="1" inputmode="numeric" value="5"></div>
            <div class="col-md-6">
              <label class="form-label" for="homePlacementPlayType">Boost mode</label>
              <select id="homePlacementPlayType" class="form-select">
                @foreach ($boostModeOptions as $boostModeOption)
                  <option value="{{ $boostModeOption['value'] }}">{{ $boostModeOption['label'] }}</option>
                @endforeach
              </select>
              <div class="form-text">{{ $selfPlayBoostModeLabel }} keeps you in the games with your booster.</div>
            </div>
            <div class="col-md-6"><label class="form-label" for="homePlacementRegion">Region</label><select id="homePlacementRegion" class="form-select">@foreach ($regions as $region)<option>{{ $region }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label" for="homePlacementPlatform">Platform</label><select id="homePlacementPlatform" class="form-select">@foreach ($platforms as $platform)<option>{{ $platform }}</option>@endforeach</select></div>
          </div>

          <h4 class="h5 mt-5 mb-3">Optional placement boost add-ons</h4>
          @include('partials.addon-options', [
            'addons' => $ggwpAddons ?? [],
            'context' => 'placement',
            'serviceType' => $serviceType,
            'boostModeInputId' => 'homePlacementPlayType',
            'currentRankInputId' => 'homePlacementLastTier',
          ])
        </div>
      </div>
    </section>
    <div class="ggwp-service-pricing-grid__quote">
      @include('home.partials.service-checkout-card', [
        'heading' => 'Placement Boost Quote',
        'duration' => '3 hours 45 minutes',
        'basePriceId' => 'placementBasePrice',
        'addonPriceId' => 'placementAddonPrice',
        'afterRrPriceId' => 'placementAfterRrPrice',
        'modifierSummaryId' => 'placementModifierSummary',
        'disabledAddonsId' => 'placementDisabledAddons',
        'priceErrorId' => 'placementPriceError',
        'priceStateId' => 'placementPriceState',
        'priceRetryWrapId' => 'placementPriceRetryWrap',
        'priceRetryButtonId' => 'placementPriceRetryBtn',
        'totalPriceId' => 'placementPrice',
        'checkoutButtonId' => 'placementCheckoutBtn',
        'summaryCurrentInputId' => 'homePlacementLastTier',
        'summaryTargetInputId' => 'homePlacementGames',
        'summaryTargetSuffix' => 'placement games',
        'summaryTargetFallback' => 'Placement games',
        'summaryRegionInputId' => 'homePlacementRegion',
        'summaryPlatformInputId' => 'homePlacementPlatform',
        'summaryModeInputId' => 'homePlacementPlayType',
      ])
    </div>
  </div>
</div>
