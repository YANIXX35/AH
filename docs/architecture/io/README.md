# Architecture `draw.io` par rubrique

Ce dossier contient maintenant une vue d'ensemble et une vue séparée pour chaque grande rubrique de l'architecture.

## Fichier principal

- `00-architecture-complete.drawio` : vue globale, hiérarchisée, de l'application.

## Vues séparées

- `01-acces-et-experience.drawio` : accès utilisateur, authentification, interface et navigation.
- `02-entree-http-laravel.drawio` : routes, middlewares, limitation de débit et contrôles d'accès.
- `03-orchestration-http-par-domaine.drawio` : contrôleurs et découpage par domaine fonctionnel.
- `04-services-applicatifs-et-support.drawio` : services métier, contrats et objets transverses.
- `05-donnees-modeles-et-persistance.drawio` : modèles Eloquent, migrations et base relationnelle.
- `06-integrations-externes.drawio` : Stripe, FedaPay et stockage de fichiers.
- `07-cible-modulaire-progressive.drawio` : trajectoire de migration vers `app/Domain`, `app/Contracts` et `app/Support`.

## Lecture conseillée

1. Commencer par `00-architecture-complete.drawio`.
2. Ouvrir ensuite la rubrique que l'on souhaite détailler.
3. Garder la même lecture visuelle : titre métier en français, référence technique avec `ref:` ou `refs:`, texte noir et couleur par famille.
