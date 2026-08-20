# Notifications push web — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Envoyer une vraie notification push navigateur (reçue même appli/onglet fermé) aux comptes PME/clients lorsqu'un `AppNotification` existant est créé pour eux, sans toucher les 9 emplacements du code qui créent déjà ces notifications.

**Architecture:** Package `laravel-notification-channels/webpush` (protocole Web Push standard, clés VAPID, pas de service tiers externe) branché sur le listener `AppNotification::created` déjà existant dans `AppServiceProvider.php` (qui gère déjà l'envoi email). Un filtre `User::isPmeClient()` détermine qui reçoit le push. Un vrai `public/service-worker.js` et `public/manifest.json` remplacent le code d'enregistrement actuellement mort (le fichier `service-worker.js` référencé dans 3 pages n'existe pas).

**Tech Stack:** Laravel 13 (PHP 8.4), `laravel-notification-channels/webpush` ^12.1, MySQL, PHPUnit/RefreshDatabase pour les tests, JS natif côté navigateur (Push API / Service Worker API, aucune librairie front ajoutée).

## Global Constraints

- Laravel 13.13+ est **strictement requis** pour ce package sur Laravel 13 (pas de repli Guzzle disponible contrairement à Laravel 12) — le projet est actuellement en 13.4.0, une mise à jour du framework est un préalable obligatoire (Tâche 1).
- Aucune notification push pour les comptes `is_platform_admin`, `is_accountant`, ou `role_key` en `commercial`/`commercial_supervisor` — email/cloche uniquement pour ces rôles, inchangé.
- Aucun rappel basé sur l'inactivité ou l'expiration d'essai dans cette itération — hors scope, voir spec `docs/superpowers/specs/2026-08-20-web-push-notifications-design.md`.
- Les clés VAPID vont dans `.env`, jamais committées.
- Ne pas modifier les 9 emplacements existants qui appellent `AppNotification::create()` — tout le fan-out passe par le listener centralisé.

---

## Task 1 : Mettre à jour Laravel vers 13.13+ et vérifier que rien ne casse

**Files:**
- Modify: `composer.json` (contrainte `laravel/framework`, déjà `^13.0`, pas de changement de contrainte nécessaire — c'est la version installée qui doit monter)
- Modify: `composer.lock` (généré par la commande, ne pas éditer à la main)

**Interfaces:**
- Consumes: rien (première tâche)
- Produces: Laravel Framework en version ≥13.13, socle nécessaire pour la Tâche 2

- [ ] **Step 1: Vérifier la version actuellement installée**

Run: `php artisan --version`
Expected: `Laravel Framework 13.4.0` (confirme qu'une mise à jour est bien nécessaire)

- [ ] **Step 2: Mettre à jour uniquement laravel/framework**

Run: `composer update laravel/framework --with-all-dependencies`

- [ ] **Step 3: Vérifier la nouvelle version**

Run: `php artisan --version`
Expected: `Laravel Framework 13.13.x` ou plus récent (pas de régression en dessous de 13.13)

- [ ] **Step 4: Lancer la suite de tests existante pour vérifier l'absence de régression**

Run: `php artisan config:clear && php artisan test`
Expected: tous les tests déjà présents dans `tests/Feature` et `tests/Unit` passent (0 échec). Si un test échoue à cause de la mise à jour, corriger l'incompatibilité avant de continuer — ne pas passer à la Tâche 2 avec des tests rouges.

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: upgrade laravel/framework to 13.13+ (required by webpush package)"
```

---

## Task 2 : Installer et configurer le package webpush

**Files:**
- Modify: `composer.json`, `composer.lock`
- Create: `config/webpush.php` (publié par le package)
- Create: `database/migrations/xxxx_xx_xx_create_push_subscriptions_table.php` (publié par le package)
- Modify: `.env`, `.env.example` (ajout des clés VAPID)
- Modify: `app/Models/User.php` (ajout du trait)

**Interfaces:**
- Consumes: Laravel ≥13.13 (Tâche 1)
- Produces: table `push_subscriptions` en base, `User` capable de `$user->updatePushSubscription(...)`, `$user->deletePushSubscription(...)`, `$user->notify(...)` vers le canal WebPush (via `HasPushSubscriptions` + `Notifiable`, déjà présent sur `User`)

- [ ] **Step 1: Installer le package**

Run: `composer require laravel-notification-channels/webpush`
Expected: `laravel-notification-channels/webpush` en version `^12.1` ajouté à `composer.json`, installation sans erreur de résolution de dépendances (Laravel ≥13.13 déjà en place depuis la Tâche 1).

- [ ] **Step 2: Publier la migration et le config**

Run:
```bash
php artisan vendor:publish --provider="NotificationChannels\WebPush\WebPushServiceProvider" --tag="migrations"
php artisan vendor:publish --provider="NotificationChannels\WebPush\WebPushServiceProvider" --tag="config"
```
Expected: un nouveau fichier `database/migrations/xxxx_xx_xx_create_push_subscriptions_table.php` et `config/webpush.php` créés.

- [ ] **Step 3: Générer les clés VAPID**

Run: `php artisan webpush:vapid`
Expected: la commande ajoute automatiquement `VAPID_PUBLIC_KEY` et `VAPID_PRIVATE_KEY` dans `.env`. Vérifier avec :
```bash
grep VAPID .env
```
Expected: deux lignes `VAPID_PUBLIC_KEY=...` et `VAPID_PRIVATE_KEY=...` avec des valeurs non vides.

- [ ] **Step 4: Ajouter les clés (vides, en placeholder de documentation) à `.env.example`**

Dans `.env.example`, ajouter :
```
VAPID_SUBJECT="mailto:support@sitiame-capital.com"
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
```

- [ ] **Step 5: Ajouter `VAPID_SUBJECT` dans `.env` local**

Dans `.env`, ajouter la ligne (si absente après `webpush:vapid`) :
```
VAPID_SUBJECT="mailto:support@sitiame-capital.com"
```

- [ ] **Step 6: Ajouter le trait `HasPushSubscriptions` au modèle `User`**

Dans `app/Models/User.php`, ajouter l'import et le trait :
```php
use NotificationChannels\WebPush\HasPushSubscriptions;
```
Et sur la ligne de la classe (actuellement `use HasFactory, Notifiable;`) :
```php
use HasFactory, Notifiable, HasPushSubscriptions;
```

- [ ] **Step 7: Lancer la migration**

Run: `php artisan migrate`
Expected: la table `push_subscriptions` est créée (visible dans la sortie de la commande).

- [ ] **Step 8: Vérifier que l'app démarre toujours correctement**

Run: `php artisan test`
Expected: tous les tests passent toujours (0 échec) — le package ne doit rien casser d'existant.

- [ ] **Step 9: Commit**

```bash
git add composer.json composer.lock config/webpush.php database/migrations app/Models/User.php .env.example
git commit -m "feat: install and configure laravel-notification-channels/webpush"
```

Note : `.env` n'est jamais committé (déjà dans `.gitignore`) — les vraies clés VAPID générées localement restent locales ; il faudra relancer `php artisan webpush:vapid` (ou copier les valeurs) sur le serveur de production lors du déploiement.

---

## Task 3 : Filtre `User::isPmeClient()`

**Files:**
- Modify: `app/Models/User.php`
- Test: `tests/Unit/UserIsPmeClientTest.php` (nouveau)

**Interfaces:**
- Consumes: colonnes déjà existantes sur `User` — `is_platform_admin` (bool), `is_accountant` (bool), `role_key` (string|null)
- Produces: `User::isPmeClient(): bool`, utilisé par la Tâche 5 (fan-out dans `AppServiceProvider`)

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Unit/UserIsPmeClientTest.php` :
```php
<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserIsPmeClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_plain_company_user_is_a_pme_client(): void
    {
        $user = User::factory()->create([
            'is_platform_admin' => false,
            'is_accountant' => false,
            'role_key' => null,
        ]);

        $this->assertTrue($user->isPmeClient());
    }

    public function test_platform_admin_is_not_a_pme_client(): void
    {
        $user = User::factory()->create([
            'is_platform_admin' => true,
            'is_accountant' => false,
            'role_key' => null,
        ]);

        $this->assertFalse($user->isPmeClient());
    }

    public function test_accountant_is_not_a_pme_client(): void
    {
        $user = User::factory()->create([
            'is_platform_admin' => false,
            'is_accountant' => true,
            'role_key' => null,
        ]);

        $this->assertFalse($user->isPmeClient());
    }

    public function test_commercial_is_not_a_pme_client(): void
    {
        $user = User::factory()->create([
            'is_platform_admin' => false,
            'is_accountant' => false,
            'role_key' => 'commercial',
        ]);

        $this->assertFalse($user->isPmeClient());
    }

    public function test_commercial_supervisor_is_not_a_pme_client(): void
    {
        $user = User::factory()->create([
            'is_platform_admin' => false,
            'is_accountant' => false,
            'role_key' => 'commercial_supervisor',
        ]);

        $this->assertFalse($user->isPmeClient());
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test tests/Unit/UserIsPmeClientTest.php`
Expected: FAIL — `Call to undefined method App\Models\User::isPmeClient()`

- [ ] **Step 3: Implémenter la méthode**

Dans `app/Models/User.php`, ajouter la méthode (près de `isPlatformAdmin()`/`isAccountant()` déjà présentes, lignes ~140-151 vues précédemment) :
```php
/**
 * Compte PME/client final — ni admin plateforme, ni comptable, ni commercial.
 * Utilisé pour cibler les notifications push (voir AppServiceProvider).
 */
public function isPmeClient(): bool
{
    if ($this->isPlatformAdmin() || $this->isAccountant()) {
        return false;
    }

    return ! in_array($this->role_key, ['commercial', 'commercial_supervisor'], true);
}
```

- [ ] **Step 4: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test tests/Unit/UserIsPmeClientTest.php`
Expected: PASS — 5 tests, 0 échec

- [ ] **Step 5: Commit**

```bash
git add app/Models/User.php tests/Unit/UserIsPmeClientTest.php
git commit -m "feat: add User::isPmeClient() to target push notifications"
```

---

## Task 4 : Classe `AppPushNotification`

**Files:**
- Create: `app/Notifications/AppPushNotification.php`
- Test: `tests/Unit/AppPushNotificationTest.php` (nouveau)

**Interfaces:**
- Consumes: `App\Models\AppNotification` (colonnes `title`, `body`, `action_url` déjà existantes)
- Produces: `AppPushNotification` (implémente `via()` → `[WebPushChannel::class]` et `toWebPush()` → `WebPushMessage`), utilisée par la Tâche 5

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Unit/AppPushNotificationTest.php` :
```php
<?php

namespace Tests\Unit;

use App\Models\AppNotification;
use App\Models\User;
use App\Notifications\AppPushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;
use Tests\TestCase;

class AppPushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_webpush_channel(): void
    {
        $user = User::factory()->create();
        $appNotification = AppNotification::create([
            'user_id' => $user->id,
            'title' => 'Nouvelle facture FA-2026-001',
            'body' => 'Une nouvelle facture a été générée.',
            'type' => 'info',
            'action_url' => '/invoicing/1',
        ]);

        $notification = new AppPushNotification($appNotification);

        $this->assertSame([WebPushChannel::class], $notification->via($user));
    }

    public function test_to_webpush_builds_message_from_app_notification(): void
    {
        $user = User::factory()->create();
        $appNotification = AppNotification::create([
            'user_id' => $user->id,
            'title' => 'Nouvelle facture FA-2026-001',
            'body' => 'Une nouvelle facture a été générée.',
            'type' => 'info',
            'action_url' => '/invoicing/1',
        ]);

        $notification = new AppPushNotification($appNotification);
        $message = $notification->toWebPush($user, $notification);

        $this->assertInstanceOf(WebPushMessage::class, $message);
        $payload = $message->toArray();

        $this->assertSame('Nouvelle facture FA-2026-001', $payload['title']);
        $this->assertSame('Une nouvelle facture a été générée.', $payload['body']);
        $this->assertSame('/invoicing/1', $payload['data']['url']);
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test tests/Unit/AppPushNotificationTest.php`
Expected: FAIL — `Class "App\Notifications\AppPushNotification" not found`

- [ ] **Step 3: Implémenter la classe**

Créer `app/Notifications/AppPushNotification.php` :
```php
<?php

namespace App\Notifications;

use App\Models\AppNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class AppPushNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly AppNotification $appNotification)
    {
    }

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $message = (new WebPushMessage())
            ->title($this->appNotification->title)
            ->icon('/images/sitiam.png')
            ->body((string) $this->appNotification->body)
            ->data(['url' => $this->appNotification->action_url]);

        return $message;
    }
}
```

- [ ] **Step 4: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test tests/Unit/AppPushNotificationTest.php`
Expected: PASS — 2 tests, 0 échec

- [ ] **Step 5: Commit**

```bash
git add app/Notifications/AppPushNotification.php tests/Unit/AppPushNotificationTest.php
git commit -m "feat: add AppPushNotification wrapping AppNotification for WebPush channel"
```

---

## Task 5 : Brancher le fan-out dans `AppServiceProvider`

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/AppNotificationPushFanoutTest.php` (nouveau)

**Interfaces:**
- Consumes: `User::isPmeClient()` (Tâche 3), `AppPushNotification` (Tâche 4)
- Produces: comportement observable — un `AppNotification::create()` pour un PME déclenche `$recipient->notify(new AppPushNotification(...))` ; pour un admin/comptable/commercial, aucun push n'est déclenché.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/AppNotificationPushFanoutTest.php` :
```php
<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\User;
use App\Notifications\AppPushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AppNotificationPushFanoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_pme_client_receives_push_notification(): void
    {
        Notification::fake();

        $pmeUser = User::factory()->create([
            'is_platform_admin' => false,
            'is_accountant' => false,
            'role_key' => null,
        ]);

        $appNotification = AppNotification::create([
            'user_id' => $pmeUser->id,
            'title' => 'Nouvelle facture',
            'body' => 'Détails de la facture.',
            'type' => 'info',
            'action_url' => '/invoicing/1',
        ]);

        Notification::assertSentTo(
            $pmeUser,
            AppPushNotification::class,
            function (AppPushNotification $notification) use ($appNotification) {
                return true;
            }
        );
    }

    public function test_admin_does_not_receive_push_notification(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'is_platform_admin' => true,
        ]);

        AppNotification::create([
            'user_id' => $admin->id,
            'title' => 'Sauvegarde échouée',
            'body' => 'Détails.',
            'type' => 'danger',
        ]);

        Notification::assertNotSentTo($admin, AppPushNotification::class);
    }

    public function test_accountant_does_not_receive_push_notification(): void
    {
        Notification::fake();

        $accountant = User::factory()->create([
            'is_accountant' => true,
        ]);

        AppNotification::create([
            'user_id' => $accountant->id,
            'title' => 'Demande de validation comptable',
            'body' => 'Détails.',
            'type' => 'accounting_change_request',
        ]);

        Notification::assertNotSentTo($accountant, AppPushNotification::class);
    }

    public function test_commercial_does_not_receive_push_notification(): void
    {
        Notification::fake();

        $commercial = User::factory()->create([
            'role_key' => 'commercial',
        ]);

        AppNotification::create([
            'user_id' => $commercial->id,
            'title' => 'Nouvelle prospection commerciale',
            'body' => 'Détails.',
            'type' => 'info',
        ]);

        Notification::assertNotSentTo($commercial, AppPushNotification::class);
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test tests/Feature/AppNotificationPushFanoutTest.php`
Expected: FAIL — `test_pme_client_receives_push_notification` échoue (`Notification::assertSentTo` ne trouve rien envoyé), les 3 autres passent déjà par absence de comportement (rien n'est jamais envoyé pour l'instant).

- [ ] **Step 3: Ajouter le fan-out dans `AppServiceProvider`**

Dans `app/Providers/AppServiceProvider.php`, ajouter l'import :
```php
use App\Notifications\AppPushNotification;
```
Puis, dans le listener `AppNotification::created(function (AppNotification $notification): void { ... })` déjà existant (lignes ~59-80), ajouter à la fin du callback, après le bloc d'envoi email existant (ne pas modifier le bloc email) :
```php
        AppNotification::created(function (AppNotification $notification): void {
            $notification->loadMissing('user');
            $recipient = $notification->user;

            if (! $recipient || ! $recipient->email) {
                return;
            }

            if ((bool) ($recipient->email_notifications ?? false)) {
                try {
                    Mail::to($recipient->email)->queue(new AppNotificationMail($notification));
                } catch (\Throwable $exception) {
                    Log::warning('notification_email_send_failed', [
                        'notification_id' => $notification->id,
                        'user_id' => $recipient->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            if ($recipient->isPmeClient()) {
                try {
                    $recipient->notify(new AppPushNotification($notification));
                } catch (\Throwable $exception) {
                    Log::warning('notification_push_send_failed', [
                        'notification_id' => $notification->id,
                        'user_id' => $recipient->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        });
```
(Le bloc email est reproduit ci-dessus tel qu'il existe déjà, avec seulement l'ajout du bloc push après — ne pas dupliquer le listener, remplacer le contenu du callback existant par cette version complète.)

- [ ] **Step 4: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test tests/Feature/AppNotificationPushFanoutTest.php`
Expected: PASS — 4 tests, 0 échec

- [ ] **Step 5: Lancer toute la suite de tests pour vérifier l'absence de régression**

Run: `php artisan test`
Expected: tous les tests passent (0 échec), y compris les tests existants qui créent des `AppNotification` ailleurs dans l'app.

- [ ] **Step 6: Commit**

```bash
git add app/Providers/AppServiceProvider.php tests/Feature/AppNotificationPushFanoutTest.php
git commit -m "feat: fan out AppNotification to push for PME clients"
```

---

## Task 6 : `service-worker.js` et `manifest.json`

**Files:**
- Create: `public/service-worker.js`
- Create: `public/manifest.json`
- Modify: `resources/views/layouts/app.blade.php` (ajout du lien `<link rel="manifest">`)

**Interfaces:**
- Consumes: rien de PHP — fichiers statiques servis directement par le serveur web
- Produces: un service worker qui affiche une notification à la réception d'un push, et gère le clic pour ouvrir `action_url` ; un manifest permettant l'installation PWA (préalable requis sur iOS)

- [ ] **Step 1: Créer `public/manifest.json`**

```json
{
    "name": "Sitiame Capital",
    "short_name": "Sitiame",
    "description": "Plateforme de gestion financière pour PME d'Afrique de l'Ouest",
    "start_url": "/dashboard",
    "display": "standalone",
    "background_color": "#0F2747",
    "theme_color": "#0F2747",
    "icons": [
        {
            "src": "/images/sitiam.png",
            "sizes": "192x192",
            "type": "image/png"
        },
        {
            "src": "/images/sitiam.png",
            "sizes": "512x512",
            "type": "image/png"
        }
    ]
}
```

- [ ] **Step 2: Créer `public/service-worker.js`**

```javascript
self.addEventListener('push', function (event) {
    if (!event.data) {
        return;
    }

    const payload = event.data.json();
    const title = payload.title || 'Sitiame Capital';
    const options = {
        body: payload.body || '',
        icon: payload.icon || '/images/sitiam.png',
        badge: '/images/sitiam.png',
        data: payload.data || {},
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    const targetUrl = (event.notification.data && event.notification.data.url) || '/dashboard';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windowClients) {
            for (let i = 0; i < windowClients.length; i++) {
                const client = windowClients[i];
                if (client.url.includes(targetUrl) && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
```

- [ ] **Step 3: Ajouter le lien manifest dans le layout**

Dans `resources/views/layouts/app.blade.php`, dans le `<head>` (près des autres balises `<link>` existantes), ajouter :
```html
<link rel="manifest" href="/manifest.json">
```

- [ ] **Step 4: Vérifier manuellement que les fichiers sont servis**

Run (avec le serveur local démarré, `php artisan serve`) :
```bash
curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8000/service-worker.js
curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8000/manifest.json
```
Expected: `200` pour les deux (avant cette tâche, `service-worker.js` renvoyait `404` — c'était le bug de code mort mentionné dans la spec).

- [ ] **Step 5: Commit**

```bash
git add public/service-worker.js public/manifest.json resources/views/layouts/app.blade.php
git commit -m "feat: add real service worker and manifest for web push + PWA install"
```

---

## Task 7 : Endpoints d'abonnement/désabonnement

**Files:**
- Create: `app/Http/Controllers/PushSubscriptionController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/PushSubscriptionTest.php` (nouveau)

**Interfaces:**
- Consumes: `$user->updatePushSubscription()` / `$user->deletePushSubscription()` (fournis par `HasPushSubscriptions`, Tâche 2)
- Produces: `POST /push/subscribe`, `DELETE /push/unsubscribe` (routes nommées `push.subscribe` / `push.unsubscribe`), consommées par la Tâche 8 (JS front)

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/PushSubscriptionTest.php` :
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_subscribe(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
            'keys' => [
                'p256dh' => 'test-p256dh-key',
                'auth' => 'test-auth-token',
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
        ]);
    }

    public function test_authenticated_user_can_unsubscribe(): void
    {
        $user = User::factory()->create();
        $user->updatePushSubscription(
            'https://fcm.googleapis.com/fcm/send/test-endpoint',
            'test-p256dh-key',
            'test-auth-token'
        );

        $response = $this->actingAs($user)->deleteJson('/push/unsubscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
        ]);

        $response->assertOk();
        $this->assertDatabaseMissing('push_subscriptions', [
            'subscribable_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
        ]);
    }

    public function test_guest_cannot_subscribe(): void
    {
        $response = $this->postJson('/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
            'keys' => ['p256dh' => 'key', 'auth' => 'token'],
        ]);

        $response->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test tests/Feature/PushSubscriptionTest.php`
Expected: FAIL — route `/push/subscribe` inexistante (404)

- [ ] **Step 3: Créer le contrôleur**

Créer `app/Http/Controllers/PushSubscriptionController.php` :
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string'],
        ]);

        $request->user()->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['contentEncoding'] ?? null
        );

        return response()->json(['status' => 'subscribed']);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string'],
        ]);

        $request->user()->deletePushSubscription($validated['endpoint']);

        return response()->json(['status' => 'unsubscribed']);
    }
}
```

- [ ] **Step 4: Ajouter les routes**

Dans `routes/web.php`, ajouter l'import en haut du fichier :
```php
use App\Http\Controllers\PushSubscriptionController;
```
Puis, juste après les routes `notifications.*` existantes (dans le même groupe `Route::middleware('auth')->group(...)`, à côté de la ligne `Route::post('/notifications/read-all', ...)`) :
```php
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'subscribe'])->name('push.subscribe');
    Route::delete('/push/unsubscribe', [PushSubscriptionController::class, 'unsubscribe'])->name('push.unsubscribe');
```

- [ ] **Step 5: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test tests/Feature/PushSubscriptionTest.php`
Expected: PASS — 3 tests, 0 échec

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PushSubscriptionController.php routes/web.php tests/Feature/PushSubscriptionTest.php
git commit -m "feat: add push subscribe/unsubscribe endpoints"
```

---

## Task 8 : Bannière d'activation côté navigateur

**Files:**
- Create: `resources/views/layouts/partials/push-notification-banner.blade.php`
- Modify: `resources/views/layouts/app.blade.php` (inclusion de la bannière + config VAPID exposée au JS)

**Interfaces:**
- Consumes: `route('push.subscribe')`, `route('push.unsubscribe')` (Tâche 7), `/service-worker.js` (Tâche 6), `config('webpush.vapid.public_key')` (Tâche 2)
- Produces: bannière visible uniquement pour les comptes PME/clients (`auth()->user()->isPmeClient()`), gérant la demande de permission et l'abonnement

- [ ] **Step 1: Exposer la clé publique VAPID et inclure la bannière dans le layout**

Dans `resources/views/layouts/app.blade.php`, repérer le bloc `@auth` existant (déjà utilisé ailleurs dans ce fichier, ex. ligne ~650 vue précédemment pour le bouton "Quitter le dossier") et y ajouter, uniquement si l'utilisateur est un PME/client :
```blade
@auth
    @if(Auth::user()->isPmeClient())
        @include('layouts.partials.push-notification-banner')
    @endif
@endauth
```
Placer cet include juste avant `@yield('content')` (même zone que la bannière de feedback générique déjà présente dans ce fichier).

- [ ] **Step 2: Créer la bannière**

Créer `resources/views/layouts/partials/push-notification-banner.blade.php` :
```blade
<div id="pushNotifBanner" class="alert alert-light border shadow-sm d-none align-items-center justify-content-between gap-3 m-3" role="alert">
    <div class="d-flex align-items-center gap-2">
        <i data-feather="bell" style="width:18px; height:18px;"></i>
        <span id="pushNotifBannerText">Active les notifications pour ne rien manquer.</span>
    </div>
    <div class="d-flex gap-2">
        <button type="button" id="pushNotifEnableBtn" class="btn btn-sm btn-primary">Activer</button>
        <button type="button" id="pushNotifDismissBtn" class="btn btn-sm btn-outline-secondary">Plus tard</button>
    </div>
</div>

<script>
(function () {
    const VAPID_PUBLIC_KEY = @json(config('webpush.vapid.public_key'));
    const SUBSCRIBE_URL = @json(route('push.subscribe'));
    const DISMISS_KEY = 'pushNotifBannerDismissed';

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    function isIosSafari() {
        const ua = window.navigator.userAgent;
        const isIos = /iPad|iPhone|iPod/.test(ua);
        const isSafari = /Safari/.test(ua) && !/CriOS|FxiOS/.test(ua);
        return isIos && isSafari;
    }

    function isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            return;
        }
        if (localStorage.getItem(DISMISS_KEY) === '1') {
            return;
        }
        if (Notification.permission === 'granted' || Notification.permission === 'denied') {
            return;
        }

        const banner = document.getElementById('pushNotifBanner');
        const textEl = document.getElementById('pushNotifBannerText');
        const enableBtn = document.getElementById('pushNotifEnableBtn');
        const dismissBtn = document.getElementById('pushNotifDismissBtn');

        if (isIosSafari() && !isStandalone()) {
            textEl.textContent = "Sur iPhone, ajoute d'abord Sitiame à l'écran d'accueil (icône Partager puis \"Sur l'écran d'accueil\") pour pouvoir activer les notifications.";
            enableBtn.classList.add('d-none');
        }

        banner.classList.remove('d-none');
        banner.classList.add('d-flex');

        dismissBtn.addEventListener('click', function () {
            localStorage.setItem(DISMISS_KEY, '1');
            banner.classList.add('d-none');
        });

        enableBtn.addEventListener('click', function () {
            navigator.serviceWorker.register('/service-worker.js').then(function (registration) {
                return Notification.requestPermission().then(function (permission) {
                    if (permission !== 'granted') {
                        banner.classList.add('d-none');
                        return;
                    }
                    return registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
                    });
                });
            }).then(function (subscription) {
                if (!subscription) {
                    return;
                }
                const json = subscription.toJSON();
                return fetch(SUBSCRIBE_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify(json),
                });
            }).then(function () {
                banner.classList.add('d-none');
            }).catch(function (error) {
                console.error('push_subscribe_failed', error);
                banner.classList.add('d-none');
            });
        });
    });
})();
</script>
```

- [ ] **Step 2bis: Vérifier la présence de la balise meta CSRF**

Run: `grep -n "csrf-token" resources/views/layouts/app.blade.php`
Expected: une ligne `<meta name="csrf-token" content="{{ csrf_token() }}">` existe déjà dans le `<head>`. Si absente, l'ajouter dans le `<head>` avant les autres scripts.

- [ ] **Step 3: Vérification manuelle en navigateur (Chrome desktop)**

Avec le serveur local démarré et un compte PME (ni admin, ni comptable, ni commercial) connecté :
1. Ouvrir la page dans Chrome, ouvrir les DevTools > Application > Service Workers — vérifier qu'un service worker `/service-worker.js` est bien enregistré et actif.
2. La bannière doit apparaître (si `Notification.permission` est encore `"default"` dans ce navigateur — sinon la réinitialiser via les paramètres du site Chrome).
3. Cliquer "Activer", accepter la permission dans la popup native du navigateur.
4. Vérifier en base : `SELECT * FROM push_subscriptions WHERE subscribable_id = <id_utilisateur>;` doit retourner une ligne.
5. Déclencher un événement réel qui crée un `AppNotification` PME-facing (ex. créer une facture via le flux existant de `BillingService`) et vérifier qu'une notification navigateur apparaît, y compris onglet fermé (fermer complètement l'onglet Sitiame après l'étape 3, puis déclencher l'événement depuis un autre compte/onglet admin).

- [ ] **Step 4: Commit**

```bash
git add resources/views/layouts/partials/push-notification-banner.blade.php resources/views/layouts/app.blade.php
git commit -m "feat: add opt-in banner for web push notifications"
```

---

## Task 9 : Déploiement en production

**Files:** aucun fichier de code — étapes opérationnelles uniquement.

**Interfaces:**
- Consumes: toutes les tâches précédentes, poussées sur `origin/master`
- Produces: fonctionnalité active en production

- [ ] **Step 1: Vérifier que toute la suite de tests passe une dernière fois**

Run: `php artisan test`
Expected: 0 échec, toutes tâches confondues.

- [ ] **Step 2: Push**

```bash
git push origin master
```

- [ ] **Step 3: Déployer côté serveur (LWS, terminal navigateur, procédure habituelle de ce projet)**

```bash
git fetch origin
git reset --hard origin/master
composer install --no-dev --optimize-autoloader
php artisan migrate --force
```

- [ ] **Step 4: Générer les clés VAPID de production (distinctes des clés locales)**

Run (sur le serveur) : `php artisan webpush:vapid`
Puis vérifier que `VAPID_SUBJECT`, `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` sont bien dans le `.env` de production.

- [ ] **Step 5: Vider les caches**

```bash
php artisan config:clear
php artisan view:clear
php artisan cache:clear
```

- [ ] **Step 6: Vérification manuelle en production**

Répéter la vérification manuelle de la Tâche 8 / Step 3, sur `sitiame-capital.com`, avec un vrai compte PME. Tester également sur iPhone (Safari) : "Ajouter à l'écran d'accueil" puis activer les notifications depuis l'appli installée.
