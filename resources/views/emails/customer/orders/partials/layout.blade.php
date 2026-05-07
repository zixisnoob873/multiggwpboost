@php
    $customerName = data_get($payload, 'customer.name', 'Customer');
    $statusLabel = data_get($payload, 'order.status_label', 'Updated');
    $orderUrl = data_get($payload, 'links.order_url');
    $supportUrl = data_get($payload, 'links.support_url');
    $supportEmail = data_get($payload, 'links.support_email');
    $messageLines = $messageLines ?? [];
    $detailsRows = $detailsRows ?? [];
    $primaryActionLabel = $primaryActionLabel ?? 'View Order';
    $secondaryActionLabel = $secondaryActionLabel ?? 'Contact Support';
    $accentColor = $accentColor ?? '#ff4655';
    $appName = data_get($payload, 'branding.app_name', config('app.name', 'GGWP Boost'));
    $logoUrl = data_get($payload, 'branding.logo_url', env('MAIL_LOGO_URL', env('APP_LOGO_URL')));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #0f1923;
            color: #ece8e1;
            font-family: Arial, Helvetica, sans-serif;
        }
        .wrapper {
            width: 100%;
            padding: 28px 12px;
            box-sizing: border-box;
        }
        .card {
            max-width: 680px;
            margin: 0 auto;
            background: #111823;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #2b333d;
            border: 1px solid rgba(236, 232, 225, 0.12);
            box-shadow: 0 20px 54px rgba(0, 0, 0, 0.35);
        }
        .bar {
            height: 7px;
            background: {{ $accentColor }};
            border-bottom: 1px solid #d4b06a;
        }
        .content {
            padding: 34px 30px;
        }
        .brand {
            margin: 0 0 30px;
        }
        .brand img {
            display: block;
            max-width: 260px;
            height: auto;
            border: 0;
            outline: none;
            text-decoration: none;
        }
        .brand-name {
            color: #ffffff;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.04em;
        }
        .eyebrow {
            margin: 0 0 12px;
            color: #d4b06a;
            font-size: 12px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-weight: 700;
        }
        h1 {
            margin: 0 0 20px;
            font-size: 30px;
            line-height: 1.2;
            color: #ffffff;
        }
        p {
            margin: 0 0 14px;
            font-size: 15px;
            line-height: 1.65;
            color: #ece8e1;
        }
        .status-box {
            margin: 24px 0;
            padding: 18px 20px;
            border-radius: 8px;
            background: #161f2b;
            border: 1px solid #2b333d;
            border: 1px solid rgba(236, 232, 225, 0.12);
            border-left: 4px solid {{ $accentColor }};
        }
        .status-label {
            margin: 0 0 6px;
            font-size: 12px;
            color: #d4b06a;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            font-weight: 700;
        }
        .status-value {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            color: #ffffff;
        }
        .status-meta {
            margin-top: 8px;
            font-size: 14px;
            color: #b8b2aa;
        }
        .details {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 12px;
            border: 1px solid rgba(236, 232, 225, 0.12);
        }
        .details td {
            padding: 11px 14px;
            border-bottom: 1px solid rgba(236, 232, 225, 0.12);
            font-size: 14px;
            vertical-align: top;
        }
        .details td:first-child {
            width: 36%;
            background: #0f1923;
            color: #d4b06a;
            font-weight: 700;
        }
        .details td:last-child {
            background: #161f2b;
            color: #ffffff;
            font-weight: 600;
        }
        .actions {
            margin: 28px 0 0;
        }
        .button {
            display: inline-block;
            padding: 12px 18px;
            margin: 0 12px 12px 0;
            border-radius: 6px;
            border: 1px solid transparent;
            box-sizing: border-box;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
        }
        .button-primary {
            background: #ff4655;
            color: #ffffff !important;
        }
        .button-secondary {
            background: #161f2b;
            border-color: #d4b06a;
            color: #d4b06a !important;
        }
        .footer {
            margin-top: 28px;
            padding-top: 18px;
            border-top: 1px solid #2b333d;
            border-top: 1px solid rgba(236, 232, 225, 0.12);
            font-size: 13px;
            color: #b8b2aa;
        }
        .footer a {
            color: #d4b06a;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="bar"></div>
            <div class="content">
                <div class="brand">
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" width="260" alt="{{ $appName }}">
                    @else
                        <div class="brand-name">{{ $appName }}</div>
                    @endif
                </div>

                <p class="eyebrow">Order Update</p>
                <h1>{{ $title }}</h1>

                <p>Hello {{ $customerName }},</p>
                <p>{{ $lead }}</p>

                @foreach($messageLines as $line)
                    <p>{{ $line }}</p>
                @endforeach

                <div class="status-box">
                    <div class="status-label">Current status</div>
                    <div class="status-value">{{ $statusLabel }}</div>

                    @if(! empty($eventLabel) && ! empty($eventValue))
                        <div class="status-meta">{{ $eventLabel }}: {{ $eventValue }}</div>
                    @endif
                </div>

                @if($detailsRows !== [])
                    <table role="presentation" class="details" width="100%" cellpadding="0" cellspacing="0">
                        @foreach($detailsRows as $label => $value)
                            @if($value !== null && trim((string) $value) !== '' && trim((string) $value) !== '-')
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td>{{ $value }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </table>
                @endif

                <div class="actions">
                    @if($orderUrl)
                        <a class="button button-primary" href="{{ $orderUrl }}">{{ $primaryActionLabel }}</a>
                    @endif

                    @if($supportUrl)
                        <a class="button button-secondary" href="{{ $supportUrl }}">{{ $secondaryActionLabel }}</a>
                    @endif
                </div>

                <div class="footer">
                    <div>This is a service update for your GGWP Boost order.</div>
                    @if($supportEmail)
                        <div>Support email: {{ $supportEmail }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</body>
</html>
