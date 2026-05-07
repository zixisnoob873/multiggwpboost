<ul class="nav nav-tabs justify-content-center mb-3" id="servicesTab" role="tablist" aria-label="{{ $gameShortName ?? 'VALORANT' }} boost services">
  @foreach(($serviceTabs ?? []) as $serviceTab)
    <li class="nav-item" role="presentation">
      <button
        class="nav-link ggwp-service-tab @if($serviceTab['active'] ?? false) active @endif"
        id="{{ $serviceTab['tab_id'] }}"
        data-bs-toggle="tab"
        data-bs-target="#{{ $serviceTab['pane_id'] }}"
        type="button"
        role="tab"
        aria-controls="{{ $serviceTab['pane_id'] }}"
        aria-selected="{{ ($serviceTab['active'] ?? false) ? 'true' : 'false' }}"
      >{{ $serviceTab['name'] }}</button>
    </li>
  @endforeach
</ul>
