<div class="tab-pane fade" id="pane-ranked" role="tabpanel" aria-labelledby="tab-ranked">
  <div class="ggwp-service-pricing-grid">
    <section class="ggwp-service-pricing-grid__config" aria-labelledby="rankedSetupHeading">
      <div class="card app-card ggwp-service-config-card">
        <div class="card-body p-4 p-xl-5">
          <h3 id="rankedSetupHeading" class="h4 mb-3">Fast VALORANT Ranked Wins Setup</h3>
          <p class="text-secondary mb-4">Choose your current rank, win count, and queue preferences for a fast VALORANT boosting quote.</p>
          <div class="row g-4">
            <div class="col-md-6">
              @include('home.partials.rank-picker-field', [
                'id' => 'homeRankedCurrentDivision',
                'label' => 'Current rank',
                'options' => $rankOptionsWithRadiant,
                'selected' => $ggwpDefaultCurrentRank ?? null,
                'modalTitle' => 'Choose your current rank',
                'placeholder' => 'Choose current rank',
              ])
            </div>
            <div class="col-md-6"><label class="form-label" for="homeRankedWins">Wins needed</label><input id="homeRankedWins" class="form-control" type="number" min="1" max="5" step="1" inputmode="numeric" value="1"></div>
            <div class="col-md-6">
              <label class="form-label" for="homeRankedPlayType">Boost mode</label>
              <select id="homeRankedPlayType" class="form-select">
                @foreach ($boostModeOptions as $boostModeOption)
                  <option value="{{ $boostModeOption['value'] }}">{{ $boostModeOption['label'] }}</option>
                @endforeach
              </select>
              <div class="form-text">{{ $selfPlayBoostModeLabel }} means you stay active while we help with the wins.</div>
            </div>
            <div class="col-md-6"><label class="form-label" for="homeRankedRegion">Region</label><select id="homeRankedRegion" class="form-select">@foreach ($regions as $region)<option>{{ $region }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label" for="homeRankedPlatform">Platform</label><select id="homeRankedPlatform" class="form-select">@foreach ($platforms as $platform)<option>{{ $platform }}</option>@endforeach</select></div>
          </div>

          <h4 class="h5 mt-5 mb-3">Optional ranked wins add-ons</h4>
          @include('partials.addon-options', [
            'addons' => $ggwpAddons ?? [],
            'context' => 'ranked',
            'serviceType' => 'Ranked Wins',
            'boostModeInputId' => 'homeRankedPlayType',
            'currentRankInputId' => 'homeRankedCurrentDivision',
            'messageId' => 'rankedAddonRulesMessage',
          ])
        </div>
      </div>
    </section>
    <div class="ggwp-service-pricing-grid__quote">
      @include('home.partials.service-checkout-card', [
        'heading' => 'Ranked Wins Quote',
        'duration' => '2 hours',
        'basePriceId' => 'rankedBasePrice',
        'addonPriceId' => 'rankedAddonPrice',
        'afterRrPriceId' => 'rankedAfterRrPrice',
        'modifierSummaryId' => 'rankedModifierSummary',
        'disabledAddonsId' => 'rankedDisabledAddons',
        'priceErrorId' => 'rankedPriceError',
        'priceStateId' => 'rankedPriceState',
        'priceRetryWrapId' => 'rankedPriceRetryWrap',
        'priceRetryButtonId' => 'rankedPriceRetryBtn',
        'totalPriceId' => 'rankedPrice',
        'checkoutButtonId' => 'rankedCheckoutBtn',
        'summaryCurrentInputId' => 'homeRankedCurrentDivision',
        'summaryTargetInputId' => 'homeRankedWins',
        'summaryTargetSuffix' => 'wins',
        'summaryTargetFallback' => 'Wins needed',
        'summaryRegionInputId' => 'homeRankedRegion',
        'summaryPlatformInputId' => 'homeRankedPlatform',
        'summaryModeInputId' => 'homeRankedPlayType',
      ])
    </div>
  </div>
</div>
