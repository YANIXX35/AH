# Runbook flux financiers critiques

## Portée
- OCR documents comptables
- Création/mise à jour d’écritures
- Solde et prévisions de trésorerie
- Webhook paiements

## Vérifications rapides
1. Vérifier les migrations: `php artisan migrate:status`
2. Vérifier la santé applicative: `php artisan about`
3. Vérifier les logs critiques:
   - `storage/logs/laravel.log`
   - `storage/logs/financial-audit-*.log`
4. Vérifier la file d’attente: `php artisan queue:monitor`

## Incident OCR en échec
1. Contrôler `PADDLE_OCR_ENABLED`, `PADDLE_OCR_TIMEOUT`, chemin runner Python.
2. Relancer OCR uniquement si statut `failed`.
3. Confirmer que le statut final est `verified`, `manual_verified`, `mismatch` ou `failed`.
4. Si panne runner locale: basculer en mode manuel, consigner l’incident, ouvrir ticket technique.

## Incident webhook paiement
1. Vérifier la route `/payments/stripe/webhook` et la limite `throttle:stripe-webhook`.
2. Vérifier signature webhook et secret `STRIPE_WEBHOOK_SECRET`.
3. Rejouer l’événement depuis le provider si nécessaire.
4. Contrôler le lien `payment_transaction_id` sur les flux trésorerie concernés.

## SLO initiaux
- Disponibilité parcours OCR + saisie comptable: `99.5%` mensuel.
- Taux d’échec webhook non récupéré: `< 0.5%`.
- Temps médian de génération dashboard/solde: `< 1.5s`.

