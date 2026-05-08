@props([
    'reviews' => [],
])

<x-trust.review-section
    id="reviewsHeading"
    :reviews="$reviews"
    kicker="Reviews"
    title="Trusted by players who want the climb handled properly"
    description="Trustpilot-style proof from customers who care about communication, delivery speed, and order safety."
/>
