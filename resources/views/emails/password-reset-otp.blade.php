<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Code OTP de réinitialisation</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2>Réinitialisation du mot de passe</h2>
    <p>Bonjour {{ $user->name }},</p>
    <p>Utilisez ce code OTP pour confirmer la réinitialisation de votre mot de passe :</p>
    <p style="font-size: 28px; font-weight: 700; letter-spacing: 3px; color: #ea580c;">{{ $otp }}</p>
    <p>Ce code expire dans <strong>{{ $expiresInMinutes }} minutes</strong>.</p>
    <p>Si vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail.</p>
    <p style="margin-top: 24px;">Cordialement,<br>L’équipe {{ config('app.name') }}</p>
</body>
</html>

