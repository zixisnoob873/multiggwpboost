@php
  $howItWorks = data_get($pageContent ?? [], 'how_it_works', []);
  $steps = collect(data_get($howItWorks, 'steps', []));
@endphp

<x-trust.order-process
  id="howItWorksHeading"
  class="ggwp-home-steps"
  :steps="$steps"
  kicker="Simple Flow"
  :title="data_get($howItWorks, 'title', 'How Your '.($gameShortName ?? 'VALORANT').' Boost Works')"
  :description="'From quote to progress tracking, the core flow stays predictable.'"
/>
