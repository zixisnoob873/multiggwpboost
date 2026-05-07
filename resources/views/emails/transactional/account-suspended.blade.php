@component('emails.transactional.layout', [
    'payload' => $payload,
    'title' => 'Your account has been suspended',
    'lead' => 'Your access to '.$payload['branding']['app_name'].' is currently restricted.',
    'messageLines' => [
        'You will not be able to use protected dashboard areas while the account is suspended.',
        'Contact support if you want us to review the restriction.',
    ],
    'primaryActionUrl' => data_get($payload, 'links.support_url'),
    'primaryActionLabel' => 'Contact Support',
])
    <div class="panel">
        <table role="presentation">
            <tr>
                <td>Account type</td>
                <td>{{ data_get($payload, 'user.role_label') }}</td>
            </tr>
            <tr>
                <td>Email</td>
                <td>{{ data_get($payload, 'user.email') }}</td>
            </tr>
            <tr>
                <td>Status</td>
                <td>Suspended</td>
            </tr>
            <tr>
                <td>Suspended at</td>
                <td>{{ data_get($payload, 'account.changed_at_formatted') }}</td>
            </tr>
            @if(data_get($payload, 'account.reason'))
                <tr>
                    <td>Reason</td>
                    <td>{{ data_get($payload, 'account.reason') }}</td>
                </tr>
            @endif
        </table>
    </div>
@endcomponent
