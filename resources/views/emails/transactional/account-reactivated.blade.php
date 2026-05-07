@component('emails.transactional.layout', [
    'payload' => $payload,
    'title' => 'Your account has been reactivated',
    'lead' => 'Your access to '.$payload['branding']['app_name'].' has been restored.',
    'messageLines' => [
        'You can sign in again and continue from your dashboard.',
    ],
    'primaryActionUrl' => data_get($payload, 'links.login_url'),
    'primaryActionLabel' => 'Sign In',
    'secondaryActionUrl' => data_get($payload, 'links.dashboard_url'),
    'secondaryActionLabel' => 'Open Dashboard',
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
                <td>Active</td>
            </tr>
            <tr>
                <td>Reactivated at</td>
                <td>{{ data_get($payload, 'account.changed_at_formatted') }}</td>
            </tr>
        </table>
    </div>
@endcomponent
