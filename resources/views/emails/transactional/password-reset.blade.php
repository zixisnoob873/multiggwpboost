@component('emails.transactional.layout', [
    'payload' => $payload,
    'title' => 'Reset your password',
    'lead' => 'We received a request to reset the password for your '.$payload['branding']['app_name'].' account.',
    'messageLines' => [
        'Use the link below to choose a new password.',
        'If you did not request this, you can safely ignore this email.',
    ],
    'primaryActionUrl' => data_get($payload, 'reset.url'),
    'primaryActionLabel' => 'Reset Password',
    'secondaryActionUrl' => data_get($payload, 'links.login_url'),
    'secondaryActionLabel' => 'Back to Login',
])
    <div class="panel">
        <table role="presentation">
            <tr>
                <td>Email</td>
                <td>{{ data_get($payload, 'user.email') }}</td>
            </tr>
            <tr>
                <td>Link expires in</td>
                <td>{{ data_get($payload, 'reset.expires_in_minutes') }} minutes</td>
            </tr>
        </table>
    </div>
@endcomponent
