<?php
/**
 * Outil de Diagnostic & Déploiement - Sitiame Capital (LWS)
 * Sécurisé par jeton d'accès.
 *
 * Usage sur le serveur : https://app.sitiame-capital.com/public/deploy.php?token=sitiame2026
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('SECURITY_TOKEN', 'sitiame2026');

$token = isset($_GET['token']) ? $_GET['token'] : '';
if ($token !== SECURITY_TOKEN) {
    header('HTTP/1.1 403 Forbidden');
    echo '<!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Accès Interdit - Sitiame Deploy</title>
        <style>
            body { font-family: system-ui, sans-serif; background: #0f172a; color: #f8fafc; text-align: center; padding: 100px 20px; }
            .box { max-width: 400px; margin: 0 auto; background: #1e293b; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); border: 1px solid #334155; }
            input { width: 100%; padding: 10px; margin: 15px 0; border-radius: 6px; border: 1px solid #475569; background: #0f172a; color: white; text-align: center; box-sizing: border-box; }
            button { background: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%; }
            button:hover { background: #2563eb; }
        </style>
    </head>
    <body>
        <div class="box">
            <h2>Sitiame Deploy</h2>
            <p style="color: #94a3b8; font-size: 14px;">Entrez le jeton de sécurité pour accéder au diagnostic de déploiement LWS.</p>
            <form method="GET">
                <input type="password" name="token" placeholder="Jeton de sécurité" required>
                <button type="submit">Valider</button>
            </form>
        </div>
    </body>
    </html>';
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$outputMessage = '';
$outputType = 'info';

$baseDir = dirname(__DIR__);

// --- RECURSIVE CHMOD FUNCTION ---
function fixPermissionsRecursive($dir, &$countDir = 0, &$countFiles = 0, &$errors = []) {
    $exclude = ['.git', 'node_modules', '.idea', '.vscode'];
    
    if (!is_dir($dir)) return;
    
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if (in_array($item, $exclude)) continue;
        
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        
        if (is_dir($path)) {
            // Dossiers: 755
            $currentPerms = decoct(fileperms($path) & 0777);
            if ($currentPerms !== '755' && $currentPerms !== '775') {
                if (@chmod($path, 0755)) {
                    $countDir++;
                } else {
                    $errors[] = "Dossier : Impossible de chmod 755 [{$path}] (actuel: {$currentPerms})";
                }
            }
            fixPermissionsRecursive($path, $countDir, $countFiles, $errors);
        } else {
            // Fichiers: 644
            $currentPerms = decoct(fileperms($path) & 0777);
            if ($currentPerms !== '644') {
                if (@chmod($path, 0644)) {
                    $countFiles++;
                } else {
                    $errors[] = "Fichier : Impossible de chmod 644 [{$path}] (actuel: {$currentPerms})";
                }
            }
        }
    }
}

// --- ACTIONS EXECUTION ---
if ($action === 'fix-permissions') {
    $countDir = 0;
    $countFiles = 0;
    $errors = [];
    
    // Essayer de remettre le dossier racine à 755 et deploy.php à 644
    @chmod($baseDir, 0755);
    @chmod(__FILE__, 0644);
    
    fixPermissionsRecursive($baseDir, $countDir, $countFiles, $errors);
    
    // Forcer le dossier storage et bootstrap/cache à être inscriptibles
    $writableFolders = [
        $baseDir . '/storage',
        $baseDir . '/storage/app',
        $baseDir . '/storage/framework',
        $baseDir . '/storage/framework/cache',
        $baseDir . '/storage/framework/sessions',
        $baseDir . '/storage/framework/views',
        $baseDir . '/storage/logs',
        $baseDir . '/bootstrap/cache'
    ];
    
    $writableFixed = 0;
    foreach ($writableFolders as $wf) {
        if (is_dir($wf)) {
            if (@chmod($wf, 0775) || @chmod($wf, 0755)) {
                $writableFixed++;
            }
        }
    }
    
    $outputMessage = "Permissions réparées avec succès !<br>";
    $outputMessage .= "• Dossiers corrigés en 755 : <strong>$countDir</strong><br>";
    $outputMessage .= "• Fichiers corrigés en 644 : <strong>$countFiles</strong><br>";
    $outputMessage .= "• Dossiers système inscriptibles (storage, cache) ajustés : <strong>$writableFixed</strong>";
    if (count($errors) > 0) {
        $outputMessage .= "<br><br><strong style='color:#ef4444;'>Certains fichiers n'ont pas pu être modifiés (probablement appartenant à un autre utilisateur système) :</strong><br>" . implode('<br>', array_slice($errors, 0, 10));
        if (count($errors) > 10) {
            $outputMessage .= "<br>... et " . (count($errors) - 10) . " autres.";
        }
    }
    $outputType = 'success';
} elseif ($action === 'clear-cache') {
    // Tenter de nettoyer le cache de Laravel si bootstrap est possible
    try {
        if (file_exists($baseDir . '/vendor/autoload.php') && file_exists($baseDir . '/bootstrap/app.php')) {
            require_once $baseDir . '/vendor/autoload.php';
            $app = require_once $baseDir . '/bootstrap/app.php';
            $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
            $status = $kernel->call('config:clear');
            $status += $kernel->call('route:clear');
            $status += $kernel->call('view:clear');
            $status += $kernel->call('cache:clear');
            
            $outputMessage = "Cache vidé avec succès via l'Artisan Console Laravel !";
            $outputType = 'success';
        } else {
            $outputMessage = "Impossible de vider le cache : autoload.php ou bootstrap/app.php manquant. L'application n'est pas installée correctement.";
            $outputType = 'error';
        }
    } catch (\Throwable $e) {
        $outputMessage = "Erreur lors du vidage de cache : " . $e->getMessage() . "<br><pre style='font-size:11px; text-align:left;'>" . $e->getTraceAsString() . "</pre>";
        $outputType = 'error';
    }
}

// --- DIAGNOSTICS ---
$phpVersion = PHP_VERSION;
$phpCompat = version_compare($phpVersion, '8.2.0', '>=');

$envExists = file_exists($baseDir . '/.env');
$envKeySet = false;
$dbConnected = false;
$dbError = '';

if ($envExists) {
    $envContent = file_get_contents($baseDir . '/.env');
    if (preg_match('/APP_KEY=base64:[A-Za-z0-9+\/=]+/', $envContent)) {
        $envKeySet = true;
    }
    
    // Tenter de lire les infos DB manuellement pour tester la connexion
    $dbHost = ''; $dbName = ''; $dbUser = ''; $dbPass = '';
    foreach (explode("\n", $envContent) as $line) {
        $line = trim($line);
        if (strpos($line, 'DB_HOST=') === 0) $dbHost = substr($line, 8);
        if (strpos($line, 'DB_DATABASE=') === 0) $dbName = substr($line, 12);
        if (strpos($line, 'DB_USERNAME=') === 0) $dbUser = substr($line, 12);
        if (strpos($line, 'DB_PASSWORD=') === 0) $dbPass = substr($line, 12);
    }
    // Nettoyer les quotes
    $dbHost = trim($dbHost, '"\' ');
    $dbName = trim($dbName, '"\' ');
    $dbUser = trim($dbUser, '"\' ');
    $dbPass = trim($dbPass, '"\' ');
    
    if ($dbHost && $dbName && $dbUser) {
        try {
            $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 2
            ]);
            $dbConnected = true;
        } catch (\Throwable $e) {
            $dbError = $e->getMessage();
        }
    } else {
        $dbError = "Paramètres de connexion DB incomplets dans le .env.";
    }
}

$storageWritable = is_writable($baseDir . '/storage');
$cacheWritable = is_writable($baseDir . '/bootstrap/cache');
$vendorExists = file_exists($baseDir . '/vendor/autoload.php');

$laravelTestBoot = false;
$laravelBootError = '';
if ($phpCompat && $vendorExists && file_exists($baseDir . '/bootstrap/app.php')) {
    try {
        // Test de chargement sécurisé (sans l'exécuter complètement)
        require_once $baseDir . '/vendor/autoload.php';
        $app = require_once $baseDir . '/bootstrap/app.php';
        $laravelTestBoot = true;
    } catch (\Throwable $e) {
        $laravelBootError = $e->getMessage() . " dans le fichier " . basename($e->getFile()) . " à la ligne " . $e->getLine();
    }
}

// Lecture des logs
$logPath = $baseDir . '/storage/logs/laravel.log';
$logLines = "Aucun log d'erreur Laravel trouvé ou lisible.";
if (file_exists($logPath) && is_readable($logPath)) {
    $lines = file($logPath);
    if ($lines) {
        $lastLines = array_slice($lines, -60);
        $logLines = htmlspecialchars(implode("", $lastLines));
    } else {
        $logLines = "Le fichier laravel.log est vide.";
    }
}

// Vérifier les permissions actuelles de l'index.php
$indexPerms = file_exists($baseDir . '/public/index.php') ? decoct(fileperms($baseDir . '/public/index.php') & 0777) : 'Introuvable';
$htaccessExists = file_exists($baseDir . '/.htaccess') || file_exists($baseDir . '/public_html/.htaccess') || file_exists($baseDir . '/www/.htaccess');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sitiame Capital - Diagnostic LWS</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; padding: 20px; line-height: 1.5; }
        .container { max-width: 1000px; margin: 0 auto; }
        h1 { color: #f8fafc; font-size: 28px; border-bottom: 2px solid #1e293b; padding-bottom: 15px; margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between; }
        .logo-text { font-weight: 800; background: linear-gradient(135deg, #60a5fa, #3b82f6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .badge { font-size: 12px; padding: 4px 10px; border-radius: 9999px; font-weight: bold; }
        .badge-success { background: #065f46; color: #34d399; }
        .badge-danger { background: #7f1d1d; color: #fca5a5; }
        .badge-warning { background: #78350f; color: #fcd34d; }
        .card { background: #1e293b; border-radius: 12px; border: 1px solid #334155; padding: 25px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.15); }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media(max-width: 768px) { .grid { grid-template-columns: 1fr; } }
        .status-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #334155; }
        .status-row:last-child { border-bottom: none; }
        .status-label { font-weight: 600; color: #94a3b8; }
        .btn { background: #3b82f6; color: white; border: none; padding: 10px 18px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: background 0.2s; display: inline-flex; align-items: center; text-decoration: none; font-size: 14px; }
        .btn:hover { background: #2563eb; }
        .btn-warning { background: #f59e0b; color: #0f172a; }
        .btn-warning:hover { background: #d97706; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; border: 1px solid transparent; font-size: 14px; }
        .alert-success { background: #065f46; border-color: #047857; color: #ecfdf5; }
        .alert-error { background: #7f1d1d; border-color: #b91c1c; color: #fef2f2; }
        .alert-info { background: #1e3a8a; border-color: #1d4ed8; color: #eff6ff; }
        pre.console { background: #020617; border: 1px solid #1e293b; padding: 15px; border-radius: 8px; color: #38bdf8; font-family: 'Courier New', Courier, monospace; font-size: 13px; overflow-x: auto; max-height: 350px; white-space: pre-wrap; word-break: break-all; }
        .error-callout { background: #7f1d1d; border-left: 4px solid #f87171; padding: 12px; border-radius: 0 4px 4px 0; margin-top: 10px; font-size: 13px; color: #fecaca; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <span>Sitiame <span class="logo-text">LWS Diagnostic</span></span>
            <span class="badge <?php echo $phpCompat ? 'badge-success' : 'badge-danger'; ?>">PHP <?php echo $phpVersion; ?></span>
        </h1>

        <?php if ($outputMessage): ?>
            <div class="alert alert-<?php echo $outputType; ?>">
                <?php echo $outputMessage; ?>
            </div>
        <?php endif; ?>

        <div class="grid">
            <!-- SERVEUR & CONFIGURATION -->
            <div class="card">
                <h3 style="margin-top:0; color:#38bdf8; border-bottom:1px solid #334155; padding-bottom:8px;">Diagnostic Système</h3>
                
                <div class="status-row">
                    <span class="status-label">Version PHP Système</span>
                    <span>
                        <strong><?php echo $phpVersion; ?></strong>
                        <?php if ($phpCompat): ?>
                            <span class="badge badge-success">OK (8.2+)</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Requis PHP 8.2+</span>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="status-row">
                    <span class="status-label">Dossiers inscriptibles</span>
                    <span>
                        <?php if ($storageWritable && $cacheWritable): ?>
                            <span class="badge badge-success">Oui</span>
                        <?php else: ?>
                            <span class="badge badge-danger">
                                <?php if (!$storageWritable) echo "storage NON "; ?>
                                <?php if (!$cacheWritable) echo "cache NON"; ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="status-row">
                    <span class="status-label">Permissions index.php</span>
                    <span>
                        <code><?php echo $indexPerms; ?></code>
                        <?php if ($indexPerms === '644'): ?>
                            <span class="badge badge-success">Correct (644)</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Incorrect (Doit être 644)</span>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="status-row">
                    <span class="status-label">Redirection .htaccess Racine</span>
                    <span>
                        <?php if ($htaccessExists): ?>
                            <span class="badge badge-success">Trouvé</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Manquant (Erreur 500 possible)</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- APPLICATION LARAVEL -->
            <div class="card">
                <h3 style="margin-top:0; color:#38bdf8; border-bottom:1px solid #334155; padding-bottom:8px;">Diagnostic Application</h3>
                
                <div class="status-row">
                    <span class="status-label">Fichier .env existant</span>
                    <span>
                        <?php if ($envExists): ?>
                            <span class="badge badge-success">Oui</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Non (.env requis)</span>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="status-row">
                    <span class="status-label">Clé d'application configurée</span>
                    <span>
                        <?php if ($envKeySet): ?>
                            <span class="badge badge-success">Oui</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Manquante</span>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="status-row">
                    <span class="status-label">Connexion Base de Données</span>
                    <span>
                        <?php if ($dbConnected): ?>
                            <span class="badge badge-success">Connecté</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Déconnecté</span>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="status-row">
                    <span class="status-label">Bootstrap de Laravel</span>
                    <span>
                        <?php if ($laravelTestBoot): ?>
                            <span class="badge badge-success">Opérationnel</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Échec</span>
                        <?php endif; ?>
                    </span>
                </div>

                <?php if ($dbError): ?>
                    <div class="error-callout">
                        <strong>Erreur BDD:</strong> <?php echo htmlspecialchars($dbError); ?>
                    </div>
                <?php endif; ?>

                <?php if ($laravelBootError): ?>
                    <div class="error-callout" style="background:#7c2d12; border-left-color:#fb923c; color:#ffedd5;">
                        <strong>Erreur de Démarrage Laravel :</strong><br><?php echo htmlspecialchars($laravelBootError); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ACTIONS DE CORRECTION -->
        <div class="card" style="text-align: center;">
            <h3 style="margin-top:0; color:#f59e0b; border-bottom:1px solid #334155; padding-bottom:8px; text-align:left;">Actions de Réparation</h3>
            <p style="text-align:left; color:#94a3b8; font-size:14px; margin-bottom:20px;">
                Utilisez ces outils si vous rencontrez des erreurs de permissions de sécurité (LWS bloque le site si les dossiers ont des droits d'écriture globaux comme 777). Le bouton ci-dessous appliquera de manière récursive la configuration recommandée (Dossiers en 755 et Fichiers en 644).
            </p>
            <a href="?token=<?php echo SECURITY_TOKEN; ?>&action=fix-permissions" class="btn btn-warning" style="margin-right: 15px;">
                ⚙️ Réparer Récursivement les Permissions (755/644)
            </a>
            <a href="?token=<?php echo SECURITY_TOKEN; ?>&action=clear-cache" class="btn">
                🧹 Vider les Caches de Configuration Laravel
            </a>
        </div>

        <!-- LOGS D'ERREUR -->
        <div class="card">
            <h3 style="margin-top:0; color:#38bdf8; border-bottom:1px solid #334155; padding-bottom:8px;">Derniers logs d'erreur (storage/logs/laravel.log)</h3>
            <pre class="console"><?php echo $logLines; ?></pre>
        </div>
    </div>
</body>
</html>
