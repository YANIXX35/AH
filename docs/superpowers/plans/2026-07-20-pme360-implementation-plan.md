
# Plan d'Implémentation - PME360 / Sitiame Capital

&gt; **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rendre l'application PME360 complètement fonctionnelle et prête pour la production, en suivant le "Plan d'État Fonctionnel - Sitiame Capital".

**Architecture:** Amélioration progressive du système existant, en activant les fonctionnalités déjà partiellement implémentées, en configurant les services externes et en améliorant la qualité du code.

**Tech Stack:** Laravel 13, PHP 8.3+, SQLite/MySQL, PaddleOCR/Tesseract, CinetPay/FedaPay/Stripe, SMTP, AWS S3 (optionnel)

## Global Constraints

- PHP &gt;= 8.3
- Laravel 13.x
- Python 3.x pour l'OCR
- Respect des conventions de codage Laravel et Spatie

---

## Plan Général par Priorité

Basé sur le "Plan d'État Fonctionnel - Sitiame Capital", voici l'ordre d'implémentation recommandé :

### Priorité 1 : Configurer SMTP et Emails
### Priorité 2 : Activer l'OCR (PaddleOCR/Tesseract)
### Priorité 3 : Intégrer les passerelles de paiement (CinetPay/FedaPay/Stripe)
### Priorité 4 : Activer IA Hugging Face et AWS S3 (si nécessaire)
### Priorité 5 : Améliorer les tests et la qualité du code
### Priorité 6 : Vérifier le déploiement

---

## Tâche 1 : Configuration SMTP et Emails

**Description:** Remplacer les placeholders SMTP dans le fichier `.env` et tester l'envoi d'emails.

**Fichiers concernés:**
- `.env` (à créer depuis `.env.example`)
- `config/mail.php` (déjà existant)

**Étapes:**
1. Dupliquer `.env.example` vers `.env` (si pas déjà fait)
2. Remplir les variables SMTP :
   - `MAIL_MAILER`
   - `MAIL_HOST`
   - `MAIL_PORT`
   - `MAIL_USERNAME`
   - `MAIL_PASSWORD`
   - `MAIL_ENCRYPTION`
   - `MAIL_FROM_ADDRESS`
   - `MAIL_FROM_NAME`
3. Tester l'envoi d'un email via une commande artisan ou un contrôleur
4. Vérifier la réception de l'email

**Validation:** Un email de test est envoyé et reçu avec succès.

---

## Tâche 2 : Activation de l'OCR (PaddleOCR/Tesseract)

**Description:** Installer les dépendances Python, configurer et activer le service OCR.

**Fichiers concernés:**
- `.env` (ajouter/modifier `PADDLE_OCR_ENABLED=true`)
- `paddle_ocr_runner.py` (déjà existant)
- `requirements-paddleocr.txt` (déjà existant)
- `app/Services/OcrService.php` et `app/Services/OcrPipelineService.php` (déjà existants)

**Étapes:**
1. Installer les dépendances Python : `pip install -r requirements-paddleocr.txt`
2. Installer Tesseract OCR (si pas déjà fait, voir `TESSERACT_INSTALLATION.md`)
3. Modifier `.env` pour passer `PADDLE_OCR_ENABLED=true`
4. Tester l'OCR avec un document de test (facture, reçu)
5. Vérifier que les champs sont correctement extraits

**Validation:** L'OCR extrait correctement les informations (montant, date, référence, partenaire) d'un document test.

---

## Tâche 3 : Intégration des Passerelles de Paiement

**Description:** Configurer CinetPay, FedaPay et/ou Stripe avec les clés API correctes.

**Fichiers concernés:**
- `.env` (ajouter les clés API)
- `config/services.php` (déjà existant)
- `app/Services/CinetPayService.php` et `app/Services/FedaPaySandboxService.php` (déjà existants)
- `app/Http/Controllers/CinetPayController.php` (déjà existant)

**Étapes par passerelle:**
### CinetPay :
1. Obtenir les clés API CinetPay
2. Ajouter à `.env` :
   - `CINETPAY_API_KEY`
   - `CINETPAY_SITE_ID`
   - `CINETPAY_SECRET_KEY`
3. Tester un paiement via l'interface

### FedaPay :
1. Obtenir les clés API FedaPay
2. Ajouter à `.env` :
   - `FEDAPAY_API_KEY`
   - `FEDAPAY_PUBLIC_KEY`
3. Tester un paiement sandbox

### Stripe :
1. Obtenir les clés API Stripe
2. Ajouter à `.env` :
   - `STRIPE_KEY`
   - `STRIPE_SECRET`
   - `STRIPE_WEBHOOK_SECRET`
   - `STRIPE_ENABLED=true`
3. Configurer les webhooks Stripe
4. Tester un paiement test

**Validation:** Chaque passerelle configurée permet de réaliser un paiement test avec succès.

---

## Tâche 4 : IA Hugging Face et Stockage AWS S3 (Optionnel)

**Description:** Configurer les services IA et de stockage cloud si nécessaire.

**Fichiers concernés:**
- `.env`
- `config/services.php`
- `config/filesystems.php`

**Étapes:**
### IA Hugging Face :
1. Obtenir un token Hugging Face
2. Ajouter à `.env` : `HUGGING_FACE_API_KEY`
3. Tester le service IA (ex: `app/Services/HuggingFaceOpsAssistantService.php`)

### AWS S3 :
1. Obtenir les credentials AWS (Access Key, Secret Key)
2. Ajouter à `.env` :
   - `AWS_ACCESS_KEY_ID`
   - `AWS_SECRET_ACCESS_KEY`
   - `AWS_DEFAULT_REGION`
   - `AWS_BUCKET`
   - `AWS_USE_PATH_STYLE_ENDPOINT`
3. Modifier `FILESYSTEM_DISK=s3` dans `.env`
4. Tester l'upload et le téléchargement de fichiers

**Validation:** Les services IA et S3 fonctionnent comme attendu.

---

## Tâche 5 : Amélioration des Tests et Qualité du Code

**Description:** Écrire des tests supplémentaires et améliorer la couverture.

**Fichiers concernés:**
- `tests/` (répertoire de tests existant avec 7 tests)
- `phpunit.xml` (déjà existant)

**Étapes:**
1. Lancer les tests existants : `php artisan test`
2. Identifier les zones non couvertes
3. Écrire des tests Feature pour les modules clés (comptabilité, trésorerie, facturation)
4. Écrire des tests Unit pour les services
5. Vérifier la couverture (si outil disponible)

**Validation:** Tous les tests passent et la couverture est améliorée.

---

## Tâche 6 : Vérification du Déploiement

**Description:** S'assurer que l'application est prête pour la production.

**Fichiers concernés:**
- `Dockerfile` (déjà existant)
- `render.yaml` (déjà existant)
- `deploy/lws/` (fichiers de déploiement LWS)

**Étapes:**
1. Vérifier que le Dockerfile build correctement : `docker build .`
2. Tester le déploiement via Render (si applicable)
3. Vérifier les fichiers de déploiement LWS
4. Effectuer un test complet de l'application en environnement similaire à la production

**Validation:** L'application se déploie et fonctionne correctement dans un environnement de production simulé.

---

## Next Steps

Une fois ce plan approuvé, nous pourrons commencer l'implémentation tâche par tâche !

