<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    @php
        $type = (string) ($notification->type ?? 'info');
        $typeLabel = match ($type) {
            'success' => 'Succes',
            'warning' => 'Alerte',
            'error' => 'Erreur',
            default => 'Information',
        };
        $typeColor = match ($type) {
            'success' => '#198754',
            'warning' => '#f59f00',
            'error' => '#dc3545',
            default => '#0d6efd',
        };
    @endphp

    <div style="margin: 0 0 10px;">
        <span style="display: inline-block; font-size: 12px; color: #fff; background: {{ $typeColor }}; border-radius: 999px; padding: 4px 10px;">
            {{ $typeLabel }}
        </span>
    </div>
    <h2 style="margin: 0 0 12px;">{{ $notification->title }}</h2>

    @if(!empty($notification->body))
        <p style="margin: 0 0 12px;">{{ $notification->body }}</p>
    @endif

    @if(!empty($notification->action_url))
        <p style="margin: 16px 0;">
            <a href="{{ $notification->action_url }}" style="display: inline-block; background: #3b82f6; color: #fff; text-decoration: none; padding: 10px 14px; border-radius: 6px;">
                Voir la notification
            </a>
        </p>
    @endif

    <p style="margin-top: 20px; color: #6b7280; font-size: 13px;">
        Ce message est envoye automatiquement par la plateforme {{ config('app.name') }}.
    </p>
</body>
</html>
