@php($serviceType = $serviceType ?? 'Rank Boosting')
<div class="tab-pane fade @if($isActiveServiceTab ?? true) show active @endif" id="{{ $serviceTab['pane_id'] ?? 'pane-boosting' }}" role="tabpanel" aria-labelledby="{{ $serviceTab['tab_id'] ?? 'tab-boosting' }}">
  <div class="ggwp-service-pricing-grid">
    <section class="ggwp-service-pricing-grid__config" aria-labelledby="boostSetupHeading">
      <div class="card app-card ggwp-service-config-card">
        <div class="card-body p-4 p-xl-5">
          <h3 id="boostSetupHeading" class="h4 mb-3">{{ $gameShortName ?? 'VALORANT' }} Rank Boost Setup</h3>
          <p class="text-secondary mb-4">Choose your current rank, target rank, and boost preferences for a live quote on safe rank boosting for {{ $gameShortName ?? 'VALORANT' }}.</p>
          <div class="row g-4">
            <div class="col-md-6">
              @include('home.partials.rank-picker-field', [
                'id' => 'homeBoostCurrentDivision',
                'label' => 'Current rank',
                'options' => array_values(array_filter($rankOptions, fn ($rankOption) => $rankOption !== 'Radiant')),
                'selected' => $ggwpDefaultCurrentRank ?? null,
                'modalTitle' => 'Choose your current rank',
                'placeholder' => 'Choose current rank',
              ])
            </div>
            <div class="col-md-6">
              @include('home.partials.rank-picker-field', [
                'id' => 'homeBoostDesiredDivision',
                'label' => 'Target rank',
                'options' => $rankOptionsWithRadiant,
                'selected' => $ggwpDefaultDesiredRank ?? null,
                'modalTitle' => 'Choose your target rank',
                'placeholder' => 'Choose target rank',
              ])
            </div>
            <div class="col-md-4"><label class="form-label" for="currentRR">Current RR</label><input class="form-control" id="currentRR" type="number" min="0" max="100" step="1" inputmode="numeric" value="0"></div>
            <div class="col-md-4">
              <label class="form-label" for="averageRRgains">Avg RR / win</label>
              <select id="averageRRgains" class="form-select">
                @foreach ($averageRrOptionChoices as $averageRrOption)
                  <option value="{{ $averageRrOption['value'] }}">{{ $averageRrOption['label'] }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="playType">Boost mode</label>
              <select id="playType" class="form-select">
                @foreach ($boostModeOptions as $boostModeOption)
                  <option value="{{ $boostModeOption['value'] }}">{{ $boostModeOption['label'] }}</option>
                @endforeach
              </select>
              <div class="form-text">{{ $selfPlayBoostModeLabel }} lets you play alongside your booster.</div>
            </div>
            <div class="col-md-6"><label class="form-label" for="homeBoostRegion">Region</label><select id="homeBoostRegion" class="form-select">@foreach ($regions as $region)<option>{{ $region }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label" for="homeBoostPlatform">Platform</label><select id="homeBoostPlatform" class="form-select">@foreach ($platforms as $platform)<option>{{ $platform }}</option>@endforeach</select></div>
          </div>

          <h4 class="h5 mt-5 mb-3">Optional {{ $gameShortName ?? 'VALORANT' }} boost add-ons</h4>
          @include('partials.addon-options', [
            'addons' => $ggwpAddons ?? [],
            'context' => 'boost',
            'serviceType' => $serviceType,
            'boostModeInputId' => 'playType',
            'currentRankInputId' => 'homeBoostCurrentDivision',
            'targetRankInputId' => 'homeBoostDesiredDivision',
            'messageId' => 'boostAddonRulesMessage',
          ])
        </div>
      </div>
    </section>
    <div class="ggwp-service-pricing-grid__quote">
      @include('home.partials.service-checkout-card', [
        'heading' => ($gameShortName ?? 'VALORANT').' Boost Quote',
        'duration' => '1 day 2 hours',
        'basePriceId' => 'boostBasePrice',
        'addonPriceId' => 'boostAddonPrice',
        'afterRrPriceId' => 'boostAfterRrPrice',
        'modifierSummaryId' => 'boostModifierSummary',
        'disabledAddonsId' => 'boostDisabledAddons',
        'priceErrorId' => 'boostPriceError',
        'priceStateId' => 'boostPriceState',
        'priceRetryWrapId' => 'boostPriceRetryWrap',
        'priceRetryButtonId' => 'boostPriceRetryBtn',
        'totalPriceId' => 'boostPrice',
        'checkoutButtonId' => 'checkoutBtn',
        'summaryCurrentInputId' => 'homeBoostCurrentDivision',
        'summaryTargetInputId' => 'homeBoostDesiredDivision',
        'summaryRegionInputId' => 'homeBoostRegion',
        'summaryPlatformInputId' => 'homeBoostPlatform',
        'summaryModeInputId' => 'playType',
      ])
    </div>
  </div>
</div>
