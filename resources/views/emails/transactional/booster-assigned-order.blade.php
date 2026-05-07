@component('emails.transactional.layout', [
    'payload' => $payload,
    'recipientName' => data_get($payload, 'booster.name', 'Booster'),
    'title' => 'A new order has been assigned to you',
    'lead' => 'An admin assigned you to order '.data_get($payload, 'order.number').'.',
    'messageLines' => [
        'Open the order, review the scope, and continue from your booster workspace.',
    ],
    'primaryActionUrl' => data_get($payload, 'links.order_url'),
    'primaryActionLabel' => 'Open Assigned Order',
    'secondaryActionUrl' => data_get($payload, 'links.orders_url'),
    'secondaryActionLabel' => 'View All Orders',
])
    <div class="panel">
        <table role="presentation">
            <tr>
                <td>Order</td>
                <td>{{ data_get($payload, 'order.number') }}</td>
            </tr>
            <tr>
                <td>Service</td>
                <td>{{ data_get($payload, 'order.service_name') }}</td>
            </tr>
            <tr>
                <td>Customer</td>
                <td>{{ data_get($payload, 'customer.name') }}</td>
            </tr>
            <tr>
                <td>Task</td>
                <td>{{ data_get($payload, 'order.task_label') }}</td>
            </tr>
            <tr>
                <td>Region</td>
                <td>{{ data_get($payload, 'order.region') }}</td>
            </tr>
            @if(data_get($payload, 'order.addons_label') && data_get($payload, 'order.addons_label') !== 'None')
                <tr>
                    <td>Add-ons</td>
                    <td>{{ data_get($payload, 'order.addons_label') }}</td>
                </tr>
            @endif
            <tr>
                <td>Status</td>
                <td>{{ data_get($payload, 'order.status_label') }}</td>
            </tr>
            <tr>
                <td>Assigned at</td>
                <td>{{ data_get($payload, 'order.assigned_at_formatted') }}</td>
            </tr>
            <tr>
                <td>Payout</td>
                <td>{{ data_get($payload, 'order.payout_formatted') }}</td>
            </tr>
            <tr>
                <td>Payout basis</td>
                <td>{{ data_get($payload, 'order.payout_basis_formatted') }}</td>
            </tr>
        </table>
    </div>
@endcomponent
