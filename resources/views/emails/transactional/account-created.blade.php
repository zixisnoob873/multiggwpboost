@php
    $dashboardUrl = data_get($payload, 'links.dashboard_url');
    $loginUrl = data_get($payload, 'links.login_url');
@endphp
@component('emails.transactional.layout', [
    'payload' => $payload,
    'title' => 'Your account is ready',
    'lead' => 'Your '.$payload['branding']['app_name'].' account has been created successfully.',
    'messageLines' => [
        'You can sign in now, open your dashboard, and manage your GGWP Boost activity from one place.',
    ],
    'primaryActionUrl' => $loginUrl,
    'primaryActionLabel' => 'Sign In',
    'secondaryActionUrl' => $dashboardUrl,
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
                <td>Created at</td>
                <td>{{ data_get($payload, 'account.created_at_formatted') }}</td>
            </tr>
        </table>
    </div>
@endcomponent
