@props([
    'faqs' => [],
    'id' => 'trustFaqAccordion',
    'headingId' => 'trustFaqHeading',
    'kicker' => 'FAQ',
    'title' => 'Answers before you order',
    'description' => 'Quick answers about safety, delivery timing, playing during orders, and VPN protection.',
    'openFirst' => true,
])

@php
    $items = collect($faqs)->values();
    $accordionId = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $id) ?: 'trustFaqAccordion';
    $hasHeading = trim((string) $kicker.(string) $title.(string) $description) !== '';
@endphp

@if($items->isNotEmpty())
    <section {{ $attributes->class('section-block ggwp-trust-section ggwp-trust-faq') }} aria-labelledby="{{ $headingId }}" data-conversion-component="faq-accordion">
        @if($hasHeading)
            <x-home.section-heading
                :id="$headingId"
                :kicker="$kicker"
                :title="$title"
                :description="$description"
            />
        @endif

        <div class="accordion ggwp-accordion ggwp-trust-faq__accordion" id="{{ $accordionId }}">
            @foreach($items as $item)
                @php
                    $itemId = $accordionId.'Item'.$loop->iteration;
                    $itemHeadingId = $itemId.'Heading';
                    $isOpen = (bool) $openFirst && $loop->first;
                @endphp

                <article class="accordion-item">
                    <h3 class="accordion-header" id="{{ $itemHeadingId }}">
                        <button
                            class="accordion-button{{ $isOpen ? '' : ' collapsed' }}"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#{{ $itemId }}"
                            aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                            aria-controls="{{ $itemId }}"
                        >
                            <span class="ggwp-accordion-question">{{ data_get($item, 'question', 'Question') }}</span>
                        </button>
                    </h3>
                    <div
                        id="{{ $itemId }}"
                        class="accordion-collapse collapse{{ $isOpen ? ' show' : '' }}"
                        aria-labelledby="{{ $itemHeadingId }}"
                        data-bs-parent="#{{ $accordionId }}"
                    >
                        <div class="accordion-body text-secondary">
                            {{ data_get($item, 'answer', '') }}
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif
