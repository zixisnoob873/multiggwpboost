@component('emails.transactional.layout', [
    'payload' => $payload,
    'recipientName' => data_get($payload, 'booster.name', 'Booster'),
    'title' => 'Your withdrawal request has been approved',
    'lead' => 'Your withdrawal request is approved and recorded in your wallet history.',
    'messageLines' => [
        data_get($payload, 'withdrawal.next_step'),
    ],
    'primaryActionUrl' => data_get($payload, 'links.wallet_url'),
    'primaryActionLabel' => 'Open Wallet',
])
    <div class="panel">
        <table role="presentation">
            <tr>
                <td>Request ID</td>
                <td>#{{ data_get($payload, 'withdrawal.id') }}</td>
            </tr>
            <tr>
                <td>Amount</td>
                <td>{{ data_get($payload, 'withdrawal.amount_formatted') }}</td>
            </tr>
            <tr>
                <td>Status</td>
                <td>{{ ucfirst((string) data_get($payload, 'withdrawal.status')) }}</td>
            </tr>
            <tr>
                <td>Processed at</td>
                <td>{{ data_get($payload, 'withdrawal.processed_at_formatted', 'Just now') }}</td>
            </tr>
            <tr>
                <td>Payout method</td>
                <td>{{ data_get($payload, 'withdrawal.payout_method') }}</td>
            </tr>
            <tr>
                <td>Estimated arrival</td>
                <td>{{ data_get($payload, 'withdrawal.estimated_arrival') }}</td>
            </tr>
            @if(data_get($payload, 'withdrawal.transaction_reference'))
                <tr>
                    <td>Reference</td>
                    <td>{{ data_get($payload, 'withdrawal.transaction_reference') }}</td>
                </tr>
            @endif
        </table>
    </div>
@endcomponent
