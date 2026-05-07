<div class="tab-pane fade" id="pane-radiant" role="tabpanel" aria-labelledby="tab-radiant">
  <div class="ggwp-service-pricing-grid">
    <section class="ggwp-service-pricing-grid__config" aria-labelledby="radiantSetupHeading">
      <div class="card app-card ggwp-service-config-card">
        <div class="card-body p-4 p-xl-5">
          <h3 id="radiantSetupHeading" class="h4 mb-3">VALORANT Radiant Boost Setup</h3>
          <p class="text-secondary mb-4">Set your current rank and region for a premium VALORANT boost quote built around a Radiant push.</p>
          <div class="row g-4">
            <div class="col-md-6">
              @include('home.partials.rank-picker-field', [
                'id' => 'homeRadiantCurrentDivision',
                'label' => 'Current rank',
                'options' => $rankOptions,
                'selected' => $ggwpDefaultCurrentRank ?? null,
                'modalTitle' => 'Choose your current rank',
                'placeholder' => 'Choose current rank',
              ])
            </div>
            <div class="col-md-6">
              @include('home.partials.rank-picker-field', [
                'id' => 'homeRadiantDesiredDivision',
                'label' => 'Target rank',
                'options' => ['Radiant'],
                'selected' => 'Radiant',
                'modalTitle' => 'Choose your target rank',
                'placeholder' => 'Choose target rank',
                'locked' => true,
                'lockedTitle' => 'Target rank is locked to Radiant for this service.',
              ])
            </div>
            <div class="col-md-6">
              <label class="form-label" for="averageRadiantRRgains">Avg RR / win</label>
              <select id="averageRadiantRRgains" class="form-select">
                @foreach ($averageRrOptionChoices as $averageRrOption)
                  <option value="{{ $averageRrOption['value'] }}">{{ $averageRrOption['label'] }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="alert alert-info mt-4 mb-0">Choose your region and platform for accurate pricing. Radiant service is handled as a premium VALORANT boost with careful scheduling and progress tracking.</div>
          <div class="row g-4 mt-2">
            <div class="col-md-6"><label class="form-label" for="homeRadiantRegion">Region</label><select id="homeRadiantRegion" class="form-select">@foreach ($regions as $region)<option>{{ $region }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label" for="homeRadiantPlatform">Platform</label><select id="homeRadiantPlatform" class="form-select">@foreach ($platforms as $platform)<option>{{ $platform }}</option>@endforeach</select></div>
          </div>

          <h4 class="h5 mt-5 mb-3">Optional Radiant boost add-ons</h4>
          @include('partials.addon-options', [
            'addons' => $ggwpAddons ?? [],
            'context' => 'radiant',
            'serviceType' => 'Radiant Boost',
            'currentRankInputId' => 'homeRadiantCurrentDivision',
            'targetRankInputId' => 'homeRadiantDesiredDivision',
          ])
        </div>
      </div>
    </section>
    <div class="ggwp-service-pricing-grid__quote">
      @include('home.partials.service-checkout-card', [
        'heading' => 'Radiant Boost Quote',
        'duration' => 'End of act',
        'basePriceId' => 'radiantBasePrice',
        'addonPriceId' => 'radiantAddonPrice',
        'afterRrPriceId' => 'radiantAfterRrPrice',
        'modifierSummaryId' => 'radiantModifierSummary',
        'disabledAddonsId' => 'radiantDisabledAddons',
        'priceErrorId' => 'radiantPriceError',
        'priceStateId' => 'radiantPriceState',
        'priceRetryWrapId' => 'radiantPriceRetryWrap',
        'priceRetryButtonId' => 'radiantPriceRetryBtn',
        'totalPriceId' => 'radiantPrice',
        'checkoutButtonId' => 'radiantCheckoutBtn',
        'summaryCurrentInputId' => 'homeRadiantCurrentDivision',
        'summaryTargetText' => 'Radiant',
        'summaryRegionInputId' => 'homeRadiantRegion',
        'summaryPlatformInputId' => 'homeRadiantPlatform',
        'summaryModeText' => 'Premium scheduling',
      ])
    </div>
  </div>
</div>
