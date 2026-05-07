@php
    $refundExpectation = data_get($payload, 'order.is_paid')
        ? 'No refund is recorded in this update. If a refund applies, we will confirm it separately.'
        : 'No payment refund is needed for this order.';
@endphp
@include('emails.customer.orders.partials.layout', [
    'payload' => $payload,
    'title' => 'Your order has been cancelled',
    'lead' => 'This order is no longer active.',
    'messageLines' => [
        data_get($payload, 'order.lifecycle.next_step') ?: 'Contact support if you need clarification on what happens next.',
    ],
    'eventLabel' => 'Cancelled at',
    'eventValue' => data_get($payload, 'order.notification_timestamp_formatted'),
    'detailsRows' => [
        'Order reference' => data_get($payload, 'order.number'),
        'Current status' => data_get($payload, 'order.status_label'),
        'Cancelled at' => data_get($payload, 'order.notification_timestamp_formatted'),
        'Reason' => data_get($payload, 'order.lifecycle.reason'),
        'Refund status' => $refundExpectation,
    ],
    'primaryActionLabel' => 'View Order',
    'accentColor' => '#ff4655',
])
