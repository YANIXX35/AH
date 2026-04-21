<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Synthèse — {{ config('app.name') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #222; line-height: 1.4; }
        h1 { font-size: 16pt; border-bottom: 2px solid #3b7ddd; padding-bottom: 6px; margin-top: 0; }
        h2 { font-size: 12pt; margin-top: 16px; color: #1e3a5f; }
        h3 { font-size: 10.5pt; margin-top: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0 12px; font-size: 9pt; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f0f4f8; font-weight: bold; }
        .muted { color: #666; font-size: 9pt; }
        .header-meta { margin-bottom: 16px; }
        ul { margin: 6px 0; padding-left: 18px; }
        li { margin: 3px 0; }
        .page-break { page-break-before: always; }
        code { font-size: 8.5pt; background: #f5f5f5; padding: 1px 4px; }
    </style>
</head>
<body>
    <div class="header-meta">
        <h1>Synthèse fonctionnelle et méthodes de calcul</h1>
        <p class="muted"><strong>{{ config('app.name') }}</strong> — Version {{ config('app.version') }} — Document généré le {{ now()->format('d/m/Y à H:i') }}</p>
    </div>

    <h2>1. Fonctionnalités développées (vue d’ensemble)</h2>

    <h3>Authentification et navigation</h3>
    <ul>
        <li>Inscription, connexion, déconnexion</li>
        <li>Redirection vers la fiche entreprise (FIRD) si des informations clés sont manquantes après connexion</li>
        <li>Interface type AdminKit : sidebar, topbar, footer (nom d’application et version)</li>
    </ul>

    <h3>Tableau de bord</h3>
    <ul>
        <li>Indicateurs : nombre d’écritures, volume comptable du mois, documents, solde de trésorerie</li>
        <li>Chiffre d’affaires (mois, année, graphique sur 12 mois)</li>
        <li>Graphiques : trésorerie (6 mois), volume comptable (6 mois), répartition OCR, évolution du CA</li>
        <li>Affichage du statut d’abonnement (simulation Premium / Gratuit)</li>
    </ul>

    <h3>Comptabilité</h3>
    <ul>
        <li>Écritures (saisie, liste, filtres, OCR, statuts)</li>
        <li>Documents comptables (import, validation)</li>
        <li>Plan comptable (import, édition, modèle)</li>
        <li>Rapports : journal, grand livre, balance, bilan simplifié, compte de résultat, export PDF bilan</li>
        <li>Module investisseurs : scores, profil, demandes d’investissement et workflow</li>
    </ul>

    <h3>Trésorerie</h3>
    <ul>
        <li>Suivi des mouvements (filtres, résumés)</li>
        <li>Solde et indicateurs sur période</li>
        <li>Prévisions : scénarios, projections, fiabilité, gouvernance (verrouillage, validation, journal d’audit)</li>
    </ul>

    <h3>Profil et entreprise</h3>
    <ul>
        <li>Profil utilisateur, entreprise, préférences, sécurité</li>
        <li>Fiche FIRD (identification légale, exercices, registres, contacts, banques, certification, etc.)</li>
        <li>Abonnement simulé, historique des abonnements, expiration automatique (tâche planifiée)</li>
    </ul>

    <h3>Notifications et support</h3>
    <ul>
        <li>Notifications in-app (liste, marquage lu)</li>
        <li>Centre d’aide (FAQ), tickets support, fil de messages</li>
    </ul>

    <h3>Données de démonstration</h3>
    <ul>
        <li>Seeder optionnel : fiche entreprise, écritures de CA démo, trésorerie démo, notification de bienvenue</li>
    </ul>

    <div class="page-break"></div>

    <h2>2. Méthodes de calcul financier et comptable</h2>

    <h3>2.1 Trésorerie — soldes et flux</h3>
    <table>
        <thead>
            <tr>
                <th>Indicateur</th>
                <th>Méthode</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Solde actuel (réalisé)</td>
                <td>Somme des encaissements au statut « effectué » moins somme des décaissements au statut « effectué ».</td>
            </tr>
            <tr>
                <td>Flux du mois courant</td>
                <td>Même logique, limité au mois civil, uniquement opérations « effectuées ».</td>
            </tr>
            <tr>
                <td>Solde projeté</td>
                <td>Solde actuel + somme des encaissements planifiés − somme des décaissements planifiés.</td>
            </tr>
            <tr>
                <td>Suivi (période)</td>
                <td>Soldes d’ouverture / clôture possibles sur la base des opérations « effectuées » et des dates de filtre.</td>
            </tr>
        </tbody>
    </table>

    <h3>2.2 Chiffre d’affaires (tableau de bord)</h3>
    <p>Indicateur de volume « produits » basé sur les écritures : pour chaque ligne, si le compte crédité commence par <strong>7</strong> → contribution positive au montant ; si le compte débité commence par <strong>7</strong> → contribution négative. Détection par préfixe de compte (classe 7). Les valeurs affichées en KPI utilisent <code>max(0, CA)</code> pour éviter les montants négatifs sur l’affichage.</p>
    <p><em>Remarque :</em> il ne s’agit pas d’un compte de résultat OHADA complet, mais d’un indicateur de pilotage sur les mouvements enregistrés sur la classe 7.</p>

    <h3>2.3 Comptabilité — agrégats</h3>
    <table>
        <thead>
            <tr>
                <th>Élément</th>
                <th>Méthode</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Volume comptable (graphique / mois)</td>
                <td>Somme des montants des écritures sur la période (tous comptes).</td>
            </tr>
            <tr>
                <td>Balance / grand livre (résumé)</td>
                <td>Par compte : cumuls débit et crédit, solde net ; agrégation pour bilan simplifié et charges/produits selon la classe de compte.</td>
            </tr>
            <tr>
                <td>Équilibre débit / crédit</td>
                <td>Sur une écriture unique, débit et crédit portent le même montant : l’écart global doit être nul si les écritures sont équilibrées.</td>
            </tr>
        </tbody>
    </table>

    <h3>2.4 Prévisions de trésorerie</h3>
    <ul>
        <li>Point de départ : solde cumulé des opérations « effectuées ».</li>
        <li>Projection : flux « planifiés » sur la période ; scénarios avec coefficients sur entrées et sorties.</li>
        <li>Fiabilité : comparaison des écarts planifié / effectué par semaine (indicateur de type MAPE), score 0–100.</li>
    </ul>

    <h3>2.5 Module investisseurs (<code>InvestorReadinessService</code>)</h3>
    <p>Scores sur 0–100 : pondération du risque (liquidité, OCR, backlog documents, alertes prévision) et de la performance (fiabilité prévision, taux OCR vérifié, santé trésorerie, activité sur 90 jours). Profils textuels selon seuils de risque et de performance.</p>

    <h3>2.6 Checklist dossier investissement</h3>
    <p>Contrôles qualitatifs et seuils (plan comptable, volume d’écritures, OCR, documents, prévisions, identité FIRD) — statuts OK / avertissement / échec.</p>

    <h3>2.7 Abonnement</h3>
    <p>Premium actif si indicateur premium et date de fin future ou non renseignée ; expiration automatique repasse le compte en gratuit selon la date de fin.</p>

    <p class="muted" style="margin-top: 24px;">Ce document résume le comportement implémenté dans l’application à la date de génération. Pour toute évolution du code, régénérer le PDF.</p>
</body>
</html>
