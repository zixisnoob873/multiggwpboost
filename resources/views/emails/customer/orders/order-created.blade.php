@php
    $addons = data_get($payload, 'order.addons_label');
    $addons = $addons && $addons !== 'None' ? $addons : null;
    $placedAt = data_get($payload, 'order.paid_at_formatted') ?: data_get($payload, 'order.created_at_formatted');
@endphp
@include('emails.customer.orders.partials.layout', [
    'payload' => $payload,
    'title' => 'Your order has been received',
    'lead' => 'Your order is confirmed and ready for review.',
    'messageLines' => [
        'We will review the order and assign it to a booster as soon as the queue is ready.',
        'Your payment and order details are available from the order dashboard.',
    ],
    'eventLabel' => data_get($payload, 'order.paid_at_formatted') ? 'Paid at' : 'Placed at',
    'eventValue' => $placedAt,
    'detailsRows' => [
        'Order reference' => data_get($payload, 'order.number'),
        'Service' => data_get($payload, 'order.service_name'),
        'Total' => data_get($payload, 'order.price_formatted'),
        'Region' => data_get($payload, 'order.region'),
        'Scope' => data_get($payload, 'order.scope_label'),
        'Add-ons' => $addons,
        data_get($payload, 'order.paid_at_formatted') ? 'Paid at' : 'Placed at' => $placedAt,
    ],
    'primaryActionLabel' => 'View Order',
    'accentColor' => '#ff4655',
])
