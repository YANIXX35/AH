# Notifications push web — design

## Contexte

Sitiame Capital est une application web (Laravel/Blade), pas une application mobile native. L'utilisateur veut donner aux PME clientes une expérience proche de ce qu'offre une vraie application installée (notifications reçues même appli fermée, comme un message Telegram) afin de les inciter à revenir plus souvent dans l'appli.

Il n'existe aujourd'hui aucune notification push : les seuls canaux sont la cloche de notifications dans la topbar et l'email (`AppNotification::created` déclenche déjà un envoi email dans `AppServiceProvider.php`). Du code mort tente déjà d'enregistrer un service worker (`navigator.serviceWorker.register('/service-worker.js')`, présent dans `layouts/app.blade.php`, `login.blade.php`, `register.blade.php`), mais le fichier `public/service-worker.js` n'existe pas — l'enregistrement échoue silencieusement.

Décisions validées avec l'utilisateur :
- Direction technique : **notifications push web** (Web Push API), pas d'application native. Fonctionne sur ordinateur (Chrome/Edge/Firefox) et Android sans contrainte particulière ; sur iPhone, uniquement si l'utilisateur a "Ajouté à l'écran d'accueil" (PWA installée), limitation d'iOS/Safari, pas de contournement possible.
- Périmètre des destinataires : uniquement les comptes **PME/clients** (pas les admins, comptables, commerciaux — qui restent sur email/cloche uniquement).
- Périmètre des déclencheurs (v1) : uniquement les événements `AppNotification` **déjà existants** (facture, paiement reçu, pièces en attente, demande de validation comptable...). Les rappels basés sur l'inactivité ou l'expiration d'essai (ex. "tu n'es pas venu depuis 5 jours") sont explicitement **hors scope v1** — évoqués comme piste v2, pas construits maintenant.

## Architecture

### Package

`laravel-notification-channels/webpush` (wrapper Laravel autour de `minishlink/web-push`) — gère le chiffrement/la signature VAPID du protocole Web Push, package mûr et standard dans l'écosystème Laravel. Pas de service tiers externe (pas de compte OneSignal/Firebase à créer) — tout reste self-hosted, cohérent avec le reste de l'infra du projet.

### Composants à ajouter

- **Migration** `push_subscriptions` (fournie par le package) : un enregistrement par abonnement navigateur/appareil, lié à `user_id`. Un même utilisateur peut avoir plusieurs abonnements actifs (plusieurs appareils/navigateurs).
- **Clés VAPID** : générées une fois via `php artisan webpush:vapid`, stockées en variables d'environnement (`.env`), jamais committées.
- **`app/Models/User.php`** : ajout du trait `NotificationChannels\WebPush\HasPushSubscriptions`.
- **`public/service-worker.js`** (nouveau fichier, remplace le code mort existant) : écoute l'événement `push`, affiche la notification (titre/corps/icône déjà présents dans `AppNotification`), gère le clic pour rouvrir/rediriger vers `action_url`.
- **`public/manifest.json`** (nouveau) : nom, icônes, `display: standalone` — nécessaire pour qu'iOS Safari propose "Ajouter à l'écran d'accueil" comme une vraie PWA installable, condition préalable aux push sur iPhone.
- **Route + contrôleur** pour enregistrer/désenregistrer un abonnement côté serveur (`POST /push/subscribe`, `DELETE /push/unsubscribe`), appelés en JS après acceptation de la permission navigateur.
- **`AppNotification` → classe `Notification` Laravel** (`WebPushChannel`) : un petit wrapper qui transforme un `AppNotification` (title/body/action_url) en message push formaté.

### Point d'accroche unique (fan-out)

Dans `AppServiceProvider.php`, à côté du listener email existant sur `AppNotification::created`, ajout d'un second traitement :

```php
AppNotification::created(function (AppNotification $notification): void {
    // ... envoi email existant, inchangé ...

    $recipient = $notification->user;
    if ($recipient && self::isPmeClient($recipient)) {
        $recipient->notify(new AppPushNotification($notification));
    }
});
```

où `isPmeClient($user)` exclut `is_platform_admin`, `is_accountant`, et `role_key` en `commercial`/`commercial_supervisor`. `ClientWorkspace::isAssignableClient()` fait un filtrage voisin (exclut `is_platform_admin`/`is_accountant`) mais ne traite pas le cas commercial — servira de point de départ pour écrire ce nouveau critère, pas une réutilisation telle quelle.

Ce point d'accroche unique couvre automatiquement les 9 emplacements existants qui créent un `AppNotification` (BillingService, AccountingChangeApprovalService, AccountingPendingDocumentsReminderCommand, SupportController, etc.) sans modifier chacun individuellement.

## UX d'activation (opt-in)

Les navigateurs bloquent toute demande de permission déclenchée automatiquement au chargement — elle doit suivre un geste utilisateur explicite, sinon le taux de refus est proche de 100 %. Comportement proposé :
- Une bannière discrète dans l'appli (pas une popup système immédiate) : "Active les notifications pour ne rien manquer", avec un bouton d'action.
- Au clic : sur navigateur compatible (Chrome/Edge/Firefox desktop, Android), déclenche directement la demande de permission native puis l'abonnement.
- Sur iPhone/Safari, si l'appli n'est pas encore installée (`window.matchMedia('(display-mode: standalone)')` faux), la bannière explique d'abord comment faire "Ajouter à l'écran d'accueil" avant de proposer d'activer les notifications.
- La bannière ne réapparaît pas si l'utilisateur a déjà un abonnement actif, ou s'il l'a explicitement fermée (mémorisé en session/localStorage pour ne pas harceler).

## Ce qui est explicitement hors scope (v2 potentiel)

- Rappels basés sur l'inactivité ("tu n'es pas venu depuis N jours") ou sur l'expiration d'essai — nécessiteraient une tâche planifiée (`Console\Kernel` schedule) détectant ces conditions et créant les `AppNotification` correspondants. Pas construit dans cette itération.
- Notifications push pour les comptes admin/comptable/commercial.
- Application mobile native.

## Vérification

- Générer les clés VAPID en local, vérifier `php artisan migrate` pour `push_subscriptions`.
- Tester l'abonnement sur un navigateur desktop (Chrome) avec un compte PME réel : accepter la permission, déclencher un événement existant (ex. créer une facture via `BillingService`), vérifier la réception de la notification navigateur.
- Vérifier qu'un compte admin/comptable ne reçoit **aucune** notification push pour le même type d'événement (email/cloche uniquement).
- Tester le flux d'installation PWA sur iPhone (Safari) : bannière d'installation, "Ajouter à l'écran d'accueil", puis activation des notifications depuis l'appli installée.
- Vérifier qu'un utilisateur avec plusieurs appareils/navigateurs abonnés reçoit bien la notification sur chacun.
