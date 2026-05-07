@include('emails.customer.orders.partials.layout', [
    'payload' => $payload,
    'title' => 'Your order has resumed',
    'lead' => 'Your order is active again and work can continue.',
    'messageLines' => [
        data_get($payload, 'order.lifecycle.next_step') ?: 'You can track progress and share important notes from the order dashboard.',
    ],
    'eventLabel' => 'Resumed at',
    'eventValue' => data_get($payload, 'order.notification_timestamp_formatted'),
    'detailsRows' => [
        'Order reference' => data_get($payload, 'order.number'),
        'Current status' => data_get($payload, 'order.status_label'),
        'Resumed at' => data_get($payload, 'order.notification_timestamp_formatted'),
    ],
    'primaryActionLabel' => 'View Order',
    'accentColor' => '#d4b06a',
])
