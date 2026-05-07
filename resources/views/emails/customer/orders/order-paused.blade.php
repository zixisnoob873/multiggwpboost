@php
    $actionRequired = data_get($payload, 'order.lifecycle.customer_action_required');
    $actionValue = $actionRequired === true ? 'Yes' : ($actionRequired === false ? 'No' : 'Check the order dashboard');
@endphp
@include('emails.customer.orders.partials.layout', [
    'payload' => $payload,
    'title' => 'Your order is currently paused',
    'lead' => 'Work on this order is on hold for now.',
    'messageLines' => [
        data_get($payload, 'order.lifecycle.next_step') ?: 'We will resume the order once the hold is cleared.',
    ],
    'eventLabel' => 'Paused at',
    'eventValue' => data_get($payload, 'order.notification_timestamp_formatted'),
    'detailsRows' => [
        'Order reference' => data_get($payload, 'order.number'),
        'Current status' => data_get($payload, 'order.status_label'),
        'Paused at' => data_get($payload, 'order.notification_timestamp_formatted'),
        'Reason' => data_get($payload, 'order.lifecycle.reason'),
        'Customer action needed' => $actionValue,
    ],
    'primaryActionLabel' => 'View Order',
    'accentColor' => '#d4b06a',
])
