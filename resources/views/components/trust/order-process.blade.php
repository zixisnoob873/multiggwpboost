@props([
    'steps' => [],
    'id' => 'trustOrderProcessHeading',
    'kicker' => 'Order process',
    'title' => 'From service pick to completion',
    'description' => 'A simple flow keeps orders easy to start and easy to monitor.',
])

@php
    $items = collect($steps)->values();
@endphp

@if($items->isNotEmpty())
    <section {{ $attributes->class('section-block ggwp-trust-section ggwp-trust-process') }} aria-labelledby="{{ $id }}" data-conversion-component="order-process">
        <x-home.section-heading
            :id="$id"
            :kicker="$kicker"
            :title="$title"
            :description="$description"
        />

        <ol class="ggwp-trust-process__list">
            @foreach($items as $step)
                <li class="ggwp-trust-process-step">
                    <span class="ggwp-trust-process-step__number" aria-hidden="true">{{ $loop->iteration }}</span>
                    <div>
                        <h3>{{ data_get($step, 'title', 'Order step') }}</h3>
                        <p>{{ data_get($step, 'body', '') }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>
@endif
