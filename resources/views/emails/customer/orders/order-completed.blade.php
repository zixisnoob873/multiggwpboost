@include('emails.customer.orders.partials.layout', [
    'payload' => $payload,
    'title' => 'Your order is complete',
    'lead' => 'This order has been marked complete.',
    'messageLines' => [
        'Review the final result in your dashboard. If anything looks wrong, contact support and include your order reference.',
    ],
    'eventLabel' => 'Completed at',
    'eventValue' => data_get($payload, 'order.completed_at_formatted') ?: data_get($payload, 'order.notification_timestamp_formatted'),
    'detailsRows' => [
        'Order reference' => data_get($payload, 'order.number'),
        'Service' => data_get($payload, 'order.service_name'),
        'Completed at' => data_get($payload, 'order.completed_at_formatted') ?: data_get($payload, 'order.notification_timestamp_formatted'),
        'Final result' => data_get($payload, 'order.result_summary'),
        'Completion proof' => data_get($payload, 'order.completion_proof_available') ? 'Uploaded' : null,
    ],
    'primaryActionLabel' => 'Review Order',
    'accentColor' => '#d4b06a',
])
