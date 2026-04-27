# SLO et monitoring

## Objectifs de service
- OCR document -> statut terminal: P95 `< 45s`.
- Création écriture comptable: taux succès `>= 99.5%`.
- Écriture trésorerie (CRUD): taux succès `>= 99.8%`.
- Webhook paiement traité sans action manuelle: `>= 99.5%`.

## Indicateurs
- Erreurs HTTP 5xx par route critique.
- Volume de statuts OCR `failed` / `mismatch`.
- Dérive prévision trésorerie (écart planifié vs effectué).
- Paiements non rapprochés (absence de lien technique).

## Sources
- Logs applicatifs (`laravel.log`).
- Logs ciblés (`financial-audit.log`, `menu-actions.log`).
- Tests CI (workflow GitHub Actions).

## Alertes minimales
- Spike 5xx > 2% sur 5 minutes.
- `failed` OCR > 10 sur 30 minutes.
- Webhook Stripe en erreur > 3 événements consécutifs.

