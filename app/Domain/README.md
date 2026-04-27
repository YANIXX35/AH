# Organisation par domaines

Ce dossier prépare la migration progressive vers une architecture modulaire.

- `Accounting/` : cas d’usage comptables et OCR.
- `Treasury/` : cas d’usage trésorerie.
- `Investor/` : scoring et parcours investisseur.
- `Payment/` : intégrations de paiement.
- `Admin/` : supervision plateforme.

Les contrôleurs HTTP restent dans `app/Http/Controllers` pendant la migration (strangler pattern).

