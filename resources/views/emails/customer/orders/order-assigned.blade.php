@include('emails.customer.orders.partials.layout', [
    'payload' => $payload,
    'title' => 'Your order is now in progress',
    'lead' => 'Your order is now being worked on.',
    'messageLines' => [
        'Keep an eye on the order dashboard in case our team needs anything while the service is active.',
    ],
    'eventLabel' => 'Assigned at',
    'eventValue' => data_get($payload, 'order.assigned_at_formatted') ?: data_get($payload, 'order.notification_timestamp_formatted'),
    'detailsRows' => [
        'Order reference' => data_get($payload, 'order.number'),
        'Service' => data_get($payload, 'order.service_name'),
        'Current status' => data_get($payload, 'order.status_label'),
        'Assigned at' => data_get($payload, 'order.assigned_at_formatted') ?: data_get($payload, 'order.notification_timestamp_formatted'),
    ],
    'primaryActionLabel' => 'View Order',
    'accentColor' => '#d4b06a',
])
