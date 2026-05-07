@props([
    'faqs' => [],
    'accordionId' => 'marketplaceFaqAccordion',
])

@php
    $items = collect($faqs)->values();
@endphp

<section class="section-block ggwp-marketplace-section ggwp-marketplace-faq" aria-labelledby="marketplaceFaqHeading">
    <x-home.section-heading
        id="marketplaceFaqHeading"
        kicker="FAQ"
        title="Answers before you order"
        description="Quick answers about safety, delivery timing, playing during orders, and VPN protection."
    />

    <div class="accordion ggwp-accordion" id="{{ $accordionId }}">
        @foreach($items as $item)
            @php
                $itemId = $accordionId.'Item'.$loop->iteration;
                $headingId = $itemId.'Heading';
                $isOpen = $loop->first;
            @endphp

            <article class="accordion-item">
                <h3 class="accordion-header" id="{{ $headingId }}">
                    <button
                        class="accordion-button{{ $isOpen ? '' : ' collapsed' }}"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#{{ $itemId }}"
                        aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                        aria-controls="{{ $itemId }}"
                    >
                        {{ data_get($item, 'question', 'Question') }}
                    </button>
                </h3>
                <div
                    id="{{ $itemId }}"
                    class="accordion-collapse collapse{{ $isOpen ? ' show' : '' }}"
                    aria-labelledby="{{ $headingId }}"
                    data-bs-parent="#{{ $accordionId }}"
                >
                    <div class="accordion-body">
                        {{ data_get($item, 'answer', '') }}
                    </div>
                </div>
            </article>
        @endforeach
    </div>
</section>
