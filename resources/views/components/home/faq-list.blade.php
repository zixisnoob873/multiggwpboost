@props([
    'faqs' => [],
    'accordionId' => 'marketplaceFaqAccordion',
])

<x-trust.faq-accordion
    :id="$accordionId"
    heading-id="marketplaceFaqHeading"
    :faqs="$faqs"
    kicker="FAQ"
    title="Answers before you order"
    description="Quick answers about safety, delivery timing, playing during orders, and VPN protection."
/>
