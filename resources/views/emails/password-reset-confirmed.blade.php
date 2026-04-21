<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Mot de passe réinitialisé</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2>Confirmation de sécurité</h2>
    <p>Bonjour {{ $user->name }},</p>
    <p>Votre mot de passe a été modifié avec succès.</p>
    <p>Si vous n’êtes pas à l’origine de cette action, contactez immédiatement le support.</p>
    <p style="margin-top: 24px;">Cordialement,<br>L’équipe {{ config('app.name') }}</p>
</body>
</html>

