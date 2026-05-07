@component('emails.transactional.layout', [
    'payload' => $payload,
    'recipientName' => data_get($payload, 'booster.name', 'Booster'),
    'title' => 'Your withdrawal request was rejected',
    'lead' => 'We were unable to approve your withdrawal request at this time.',
    'messageLines' => [
        data_get($payload, 'withdrawal.next_step'),
    ],
    'primaryActionUrl' => data_get($payload, 'links.wallet_url'),
    'primaryActionLabel' => 'Open Wallet',
    'secondaryActionUrl' => data_get($payload, 'links.support_url'),
    'secondaryActionLabel' => 'Contact Support',
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
                <td>Reason</td>
                <td>{{ data_get($payload, 'withdrawal.rejection_reason') }}</td>
            </tr>
            <tr>
                <td>Can resubmit</td>
                <td>{{ data_get($payload, 'withdrawal.can_resubmit') ? 'Yes' : 'No' }}</td>
            </tr>
        </table>
    </div>
@endcomponent
