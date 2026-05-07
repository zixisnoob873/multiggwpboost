@php
    $appName = data_get($payload, 'branding.app_name', config('app.name', 'GGWP Boost'));
    $supportUrl = data_get($payload, 'links.support_url');
    $supportEmail = data_get($payload, 'links.support_email');
    $primaryActionUrl = $primaryActionUrl ?? null;
    $primaryActionLabel = $primaryActionLabel ?? null;
    $secondaryActionUrl = $secondaryActionUrl ?? null;
    $secondaryActionLabel = $secondaryActionLabel ?? null;
    $accentColor = $accentColor ?? '#ff4655';
    $recipientName = $recipientName ?? data_get($payload, 'user.name', data_get($payload, 'booster.name', 'there'));
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
            background: #0f1923;
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
        .panel {
            margin: 24px 0;
            padding: 18px 20px;
            border-radius: 8px;
            background: #161f2b;
            border: 1px solid #2b333d;
            border: 1px solid rgba(236, 232, 225, 0.12);
            border-left: 4px solid {{ $accentColor }};
        }
        .panel table {
            width: 100%;
            border-collapse: collapse;
        }
        .panel td {
            padding: 8px 0;
            font-size: 14px;
            vertical-align: top;
        }
        .panel td:first-child {
            width: 38%;
            color: #d4b06a;
            padding-right: 12px;
            font-weight: 700;
        }
        .panel td:last-child {
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

                <p class="eyebrow">{{ $appName }} Transactional Update</p>
                <h1>{{ $title }}</h1>

                <p>Hello {{ $recipientName }},</p>
                <p>{{ $lead }}</p>

                @foreach(($messageLines ?? []) as $line)
                    <p>{{ $line }}</p>
                @endforeach

                {{ $slot ?? '' }}

                <div class="actions">
                    @if($primaryActionUrl && $primaryActionLabel)
                        <a class="button button-primary" href="{{ $primaryActionUrl }}">{{ $primaryActionLabel }}</a>
                    @endif

                    @if($secondaryActionUrl && $secondaryActionLabel)
                        <a class="button button-secondary" href="{{ $secondaryActionUrl }}">{{ $secondaryActionLabel }}</a>
                    @endif
                </div>

                <div class="footer">
                    <div>This is a service email from {{ $appName }}.</div>
                    @if($supportEmail)
                        <div>Support email: {{ $supportEmail }}</div>
                    @endif
                    @if($supportUrl)
                        <div>Support: <a href="{{ $supportUrl }}">{{ $supportUrl }}</a></div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</body>
</html>
