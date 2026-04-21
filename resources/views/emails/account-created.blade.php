<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Compte créé</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2>Bienvenue {{ $user->name }},</h2>
    <p>Votre compte a été créé avec succès sur <strong>{{ config('app.name') }}</strong>.</p>
    <p>Vous pouvez vous connecter immédiatement et accéder à votre espace.</p>
    <p style="margin-top: 24px;">Cordialement,<br>L’équipe {{ config('app.name') }}</p>
</body>
</html>

