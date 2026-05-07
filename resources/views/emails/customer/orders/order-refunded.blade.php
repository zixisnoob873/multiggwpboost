@include('emails.customer.orders.partials.layout', [
    'payload' => $payload,
    'title' => 'Your order has been refunded',
    'lead' => 'A refund has been recorded for this order.',
    'messageLines' => [
        'Funds are returned through the payment path shown below. Provider and bank timing can vary.',
    ],
    'eventLabel' => 'Refunded at',
    'eventValue' => data_get($payload, 'order.notification_timestamp_formatted'),
    'detailsRows' => [
        'Order reference' => data_get($payload, 'order.number'),
        'Refunded at' => data_get($payload, 'order.notification_timestamp_formatted'),
        'Refund amount' => data_get($payload, 'order.refund.amount_formatted'),
        'Refund method' => data_get($payload, 'order.refund.method'),
        'Destination' => data_get($payload, 'order.refund.destination'),
        'Estimated arrival' => data_get($payload, 'order.refund.estimated_arrival'),
        'Reference' => data_get($payload, 'order.refund.reference'),
    ],
    'primaryActionLabel' => 'View Order',
    'accentColor' => '#d4b06a',
])
