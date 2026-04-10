<?php
/**
 * install.php — Installeur du système de sécurité web
 * Remplace installation_beta3.php
 */
session_start();

define('SETUP_TOKEN_FILE', __DIR__ . '/setup.token');

// --- Token d'installation ---
// Généré une seule fois à la première visite, stocké dans setup.token
if (!file_exists(SETUP_TOKEN_FILE)) {
    $newToken = bin2hex(random_bytes(20));
    file_put_contents(SETUP_TOKEN_FILE, $newToken);
    chmod(SETUP_TOKEN_FILE, 0600);
}
$validToken = trim(file_get_contents(SETUP_TOKEN_FILE));

// --- Authentification par token ---
$authError = '';
if (empty($_SESSION['install_auth'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_token'])) {
        if (hash_equals($validToken, trim($_POST['setup_token']))) {
            session_regenerate_id(true);
            $_SESSION['install_auth']  = true;
            $_SESSION['csrf_token']    = bin2hex(random_bytes(32));
        } else {
            $authError = 'Token incorrect. Vérifiez le fichier <code>setup.token</code> sur le serveur.';
        }
    }
    if (empty($_SESSION['install_auth'])) {
        renderAuthPage($validToken, $authError);
        exit;
    }
}

// --- Protection CSRF ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

function verifyCsrf() {
    global $csrfToken;
    if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, $_POST['csrf_token'])) {
        http_response_code(403);
        die('Token CSRF invalide. <a href="install.php">Recommencer</a>');
    }
}

// --- Fichiers requis ---
$requiredFiles = [
    'debut.php',
    '404.php',
    'gestion-template.php',
    'sample_index',
    'blacklist.txt',
    'debut404wpclassictheme.php',
    'ifwordpress.php',
    'blacklistverif.php',
    'installation-de-gestion.php',
];

// --- Étape courante ---
$step       = isset($_GET['step']) ? max(1, min(8, intval($_GET['step']))) : 1;
$stepLabels = [
    1 => 'Vérification des fichiers',
    2 => 'Sauvegarde .htaccess',
    3 => 'Module WordPress',
    4 => 'Vérification blacklist',
    5 => 'Clé API AbuseIPDB',
    6 => 'Identifiants admin',
    7 => 'Installation',
    8 => 'Terminé',
];

renderLayout($step, $stepLabels);

switch ($step) {
    case 1: stepFilesCheck($requiredFiles, $csrfToken);     break;
    case 2: stepHtaccessBackup($csrfToken);                 break;
    case 3: stepWordpress($csrfToken);                      break;
    case 4: stepBlacklist($csrfToken);                      break;
    case 5: stepApiKey($csrfToken);                         break;
    case 6: stepAdminCredentials($csrfToken);               break;
    case 7: stepInstall($csrfToken);                        break;
    case 8: stepDone();                                     break;
}

echo '</div></div></body></html>';

// =============================================================================
// FONCTIONS
// =============================================================================

function renderAuthPage($token, $error) {
    ?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installeur — Authentification</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Segoe UI',Arial,sans-serif;background:#f0f2f5;min-height:100vh;display:flex;align-items:center;justify-content:center;}
        .card{background:white;padding:2rem;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,0.1);width:100%;max-width:460px;}
        h1{font-size:1.5rem;color:#1a1a2e;margin-bottom:.3rem;}
        .sub{color:#666;font-size:.9rem;margin-bottom:1.5rem;}
        .token-box{background:#f8f9fa;border:1px dashed #adb5bd;border-radius:6px;padding:1rem;margin-bottom:1.5rem;}
        .token-label{font-size:.78rem;color:#888;margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.04em;}
        .token-value{font-family:monospace;font-size:.9rem;word-break:break-all;color:#1a1a2e;}
        .hint{font-size:.8rem;color:#666;margin-top:.6rem;}
        .form-group{margin-bottom:1rem;}
        .form-group label{display:block;font-weight:600;font-size:.875rem;margin-bottom:.35rem;}
        input[type=text]{width:100%;padding:.625rem .75rem;border:1px solid #ddd;border-radius:6px;font-size:.95rem;font-family:monospace;}
        input[type=text]:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.15);}
        .btn{width:100%;margin-top:.5rem;padding:.75rem;background:#2563eb;color:white;border:none;border-radius:6px;font-size:1rem;cursor:pointer;font-weight:600;}
        .btn:hover{background:#1d4ed8;}
        .error{background:#fee2e2;border:1px solid #fca5a5;color:#b91c1c;padding:.7rem 1rem;border-radius:6px;margin-bottom:1rem;font-size:.9rem;}
    </style>
</head>
<body>
<div class="card">
    <h1>🔒 Installation Sécurité Web</h1>
    <p class="sub">Entrez le token d'installation pour commencer.</p>
    <div class="token-box">
        <div class="token-label">Votre token d'installation</div>
        <div class="token-value"><?= htmlspecialchars($token) ?></div>
        <p class="hint">Ce token est aussi disponible dans le fichier <code>setup.token</code> sur le serveur.</p>
    </div>
    <?php if ($error): ?>
    <div class="error"><?= $error ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label for="setup_token">Token d'installation</label>
            <input type="text" id="setup_token" name="setup_token" placeholder="Collez le token ici" autocomplete="off" required>
        </div>
        <button type="submit" class="btn">Accéder à l'installation →</button>
    </form>
</div>
</body>
</html><?php
}

function renderLayout($currentStep, $stepLabels) {
    ?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installeur — Étape <?= $currentStep ?>/<?= count($stepLabels) ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Segoe UI',Arial,sans-serif;background:#f0f2f5;color:#333;}
        .layout{display:flex;min-height:100vh;}

        /* Sidebar */
        .sidebar{width:250px;background:#1a1a2e;color:#c0c8d8;padding:1.5rem;flex-shrink:0;position:sticky;top:0;height:100vh;}
        .sidebar-title{font-size:.75rem;text-transform:uppercase;letter-spacing:.08em;color:#6b7280;margin-bottom:1.5rem;}
        .step-item{display:flex;align-items:flex-start;margin-bottom:.9rem;opacity:.4;transition:opacity .2s;}
        .step-item.active,.step-item.done{opacity:1;}
        .step-num{width:26px;height:26px;border-radius:50%;border:2px solid #374151;color:#9ca3af;font-size:.75rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-right:.75rem;margin-top:1px;}
        .step-item.active .step-num{background:#3b82f6;border-color:#3b82f6;color:white;}
        .step-item.done .step-num{background:#10b981;border-color:#10b981;color:white;}
        .step-label{font-size:.85rem;line-height:1.4;}
        .step-item.active .step-label{color:white;font-weight:600;}
        .step-item.done .step-label{color:#9ca3af;}

        /* Main */
        .main{flex:1;padding:2.5rem 3rem;max-width:860px;}
        .main h1{font-size:1.75rem;color:#1a1a2e;margin-bottom:.35rem;}
        .main .subtitle{color:#6b7280;font-size:.95rem;margin-bottom:2rem;}

        /* Cards */
        .card{background:white;border-radius:8px;padding:1.5rem;margin-bottom:1.5rem;box-shadow:0 1px 4px rgba(0,0,0,.08);}

        /* Alerts */
        .alert{padding:.8rem 1rem;border-radius:6px;margin-bottom:.75rem;font-size:.9rem;display:flex;align-items:flex-start;gap:.5rem;}
        .alert-success{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;}
        .alert-error{background:#fee2e2;border:1px solid #fca5a5;color:#b91c1c;}
        .alert-warning{background:#fffbeb;border:1px solid #fcd34d;color:#78350f;}
        .alert-info{background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;}

        /* Forms */
        .form-group{margin-bottom:1.1rem;}
        .form-group label{display:block;font-weight:600;font-size:.875rem;margin-bottom:.35rem;color:#374151;}
        .form-group input[type=text],.form-group input[type=password],.form-group input[type=email]{width:100%;padding:.625rem .75rem;border:1px solid #d1d5db;border-radius:6px;font-size:.95rem;}
        .form-group input:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12);}
        .form-hint{font-size:.8rem;color:#9ca3af;margin-top:.25rem;}

        /* Buttons */
        .btn{display:inline-flex;align-items:center;gap:.4rem;padding:.6rem 1.25rem;border-radius:6px;font-size:.9rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;transition:background .15s;}
        .btn-primary{background:#2563eb;color:white;} .btn-primary:hover{background:#1d4ed8;}
        .btn-success{background:#059669;color:white;} .btn-success:hover{background:#047857;}
        .btn-secondary{background:#6b7280;color:white;} .btn-secondary:hover{background:#4b5563;}
        .btn-warning{background:#d97706;color:white;} .btn-warning:hover{background:#b45309;}
        .btn-danger{background:#dc2626;color:white;} .btn-danger:hover{background:#b91c1c;}
        .btn-sm{padding:.35rem .75rem;font-size:.8rem;}
        .btn-row{display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;margin-top:1.25rem;}

        /* Table */
        table{width:100%;border-collapse:collapse;font-size:.875rem;}
        th{background:#f9fafb;padding:.6rem .75rem;text-align:left;border-bottom:2px solid #e5e7eb;font-weight:600;color:#374151;}
        td{padding:.5rem .75rem;border-bottom:1px solid #f3f4f6;color:#374151;}
        tr:last-child td{border-bottom:none;}
        tr:hover td{background:#fafafa;}

        /* Status icons */
        .ok{color:#059669;font-weight:700;}
        .miss{color:#dc2626;font-weight:700;}

        /* External link */
        a.ext{color:#2563eb;font-size:.85rem;}
        a.ext:hover{text-decoration:underline;}

        code{background:#f3f4f6;padding:.1rem .3rem;border-radius:3px;font-size:.85rem;}
    </style>
</head>
<body>
<div class="layout">
    <div class="sidebar">
        <div class="sidebar-title">Installation</div>
        <?php
        $total = count($stepLabels);
        for ($i = 1; $i <= $total; $i++):
            $class = '';
            if ($i < $currentStep) $class = 'done';
            elseif ($i == $currentStep) $class = 'active';
            $icon = ($i < $currentStep) ? '✓' : $i;
        ?>
        <div class="step-item <?= $class ?>">
            <div class="step-num"><?= $icon ?></div>
            <div class="step-label"><?= htmlspecialchars($stepLabels[$i]) ?></div>
        </div>
        <?php endfor; ?>
    </div>
    <div class="main">
    <?php
}

// ------------------------------------------------------------------
// Étape 1 : Vérification des fichiers
// ------------------------------------------------------------------
function stepFilesCheck($requiredFiles, $csrfToken) {
    echo '<h1>Vérification des fichiers</h1>';
    echo '<p class="subtitle">Contrôle de présence des fichiers requis avant l\'installation.</p>';

    $missing = [];
    echo '<div class="card"><table>';
    echo '<tr><th>Fichier</th><th>Statut</th></tr>';
    foreach ($requiredFiles as $file) {
        $ok = file_exists($file);
        if (!$ok) $missing[] = $file;
        $status = $ok
            ? '<span class="ok">✓ Présent</span>'
            : '<span class="miss">✗ Manquant</span>';
        echo '<tr><td><code>' . htmlspecialchars($file) . '</code></td><td>' . $status . '</td></tr>';
    }
    echo '</table></div>';

    if (empty($missing)) {
        echo '<div class="alert alert-success"><span>✓</span> Tous les fichiers requis sont présents.</div>';
        echo '<div class="btn-row"><a href="install.php?step=2" class="btn btn-primary">Continuer →</a></div>';
    } else {
        echo '<div class="alert alert-error"><span>✗</span> ' . count($missing) . ' fichier(s) manquant(s). Ajoutez-les avant de continuer.</div>';
    }
}

// ------------------------------------------------------------------
// Étape 2 : Sauvegarde .htaccess
// ------------------------------------------------------------------
function stepHtaccessBackup($csrfToken) {
    echo '<h1>Sauvegarde .htaccess</h1>';
    echo '<p class="subtitle">Sauvegarde préventive du .htaccess existant.</p>';

    if (!file_exists('.htaccess')) {
        echo '<div class="alert alert-info"><span>ℹ</span> Aucun .htaccess trouvé — rien à sauvegarder.</div>';
    } else {
        $backupName = 'ANCIEN-htaccess-' . date('YmdHis');
        if (copy('.htaccess', $backupName)) {
            $denyRule = "\n# Sauvegarde protégée\n<Files \"$backupName\">\n    Order Deny,Allow\n    deny from all\n</Files>\n";
            file_put_contents('.htaccess', $denyRule, FILE_APPEND);
            echo '<div class="alert alert-success"><span>✓</span> Sauvegarde créée : <code>' . htmlspecialchars($backupName) . '</code> (accès bloqué par .htaccess)</div>';
        } else {
            echo '<div class="alert alert-error"><span>✗</span> Impossible de créer la sauvegarde. Vérifiez les permissions.</div>';
        }
    }

    echo '<div class="btn-row"><a href="install.php?step=3" class="btn btn-primary">Continuer →</a></div>';
}

// ------------------------------------------------------------------
// Étape 3 : Module WordPress
// ------------------------------------------------------------------
function stepWordpress($csrfToken) {
    echo '<h1>Module WordPress</h1>';
    echo '<p class="subtitle">Intégration optionnelle avec un site WordPress.</p>';

    $isWp = file_exists('wp-config.php') || is_dir('wp-content');

    // Stocker le flag wordpress dans pipou.ini (sans écraser les autres clés)
    $pipouFile = __DIR__ . '/pipou.ini';
    $pipou = file_exists($pipouFile) ? (parse_ini_file($pipouFile) ?: []) : [];
    $pipou['wordpress'] = $isWp ? '1' : '0';
    $ini = '';
    foreach ($pipou as $k => $v) { $ini .= "$k = $v\n"; }
    file_put_contents($pipouFile, $ini);

    if ($isWp) {
        echo '<div class="alert alert-info"><span>ℹ</span> Installation WordPress détectée.</div>';
        echo '<div class="card">';
        echo '<p>Pour injecter le logger 404 dans votre thème, ouvrez le module WordPress dans un nouvel onglet :</p>';
        echo '<div class="btn-row" style="margin-top:1rem;">';
        echo '<a href="ifwordpress.php" target="_blank" class="btn btn-warning">Ouvrir le module WordPress ↗</a>';
        echo '</div>';
        echo '<p style="margin-top:.75rem;font-size:.85rem;color:#9ca3af;">Une fois le module configuré, revenez ici pour continuer.</p>';
        echo '</div>';
    } else {
        echo '<div class="alert alert-success"><span>✓</span> Pas de WordPress détecté — blacklist automatique des chemins WP activée.</div>';
    }

    echo '<div class="btn-row"><a href="install.php?step=4" class="btn btn-primary">Continuer →</a></div>';
}

// ------------------------------------------------------------------
// Étape 4 : Vérification blacklist
// ------------------------------------------------------------------
function stepBlacklist($csrfToken) {
    // Traitement d'une suppression de la blacklist
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_bl') {
        verifyCsrf();
        $pathToRemove = trim($_POST['path'] ?? '');
        if ($pathToRemove !== '' && file_exists('blacklist.txt')) {
            $lines = file('blacklist.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $newLines = array_filter($lines, fn($l) => trim($l) !== $pathToRemove);
            file_put_contents('blacklist.txt', count($newLines) ? implode("\n", $newLines) . "\n" : "");
        }
        // Redirect pour éviter le re-POST
        header("Location: install.php?step=4");
        exit;
    }

    echo '<h1>Vérification de la blacklist</h1>';
    echo '<p class="subtitle">Détection des chemins blacklistés qui correspondent à des fichiers réels (faux positifs potentiels).</p>';

    if (!file_exists('blacklist.txt')) {
        echo '<div class="alert alert-info"><span>ℹ</span> Aucun fichier blacklist.txt — étape ignorée.</div>';
        echo '<div class="btn-row"><a href="install.php?step=5" class="btn btn-primary">Continuer →</a></div>';
        return;
    }

    $lines    = file('blacklist.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $docRoot  = realpath($_SERVER['DOCUMENT_ROOT']);
    $conflicts = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $cleanLine = preg_replace('/\?.*/', '', $line);
        $fullPath  = $docRoot !== false ? realpath($docRoot . $cleanLine) : false;
        if ($fullPath !== false && $docRoot !== false
            && strncmp($fullPath, $docRoot, strlen($docRoot)) === 0
            && file_exists($fullPath)
        ) {
            $conflicts[] = $line;
        }
    }

    if (empty($conflicts)) {
        echo '<div class="alert alert-success"><span>✓</span> Aucun conflit détecté dans la blacklist.</div>';
    } else {
        echo '<div class="alert alert-warning"><span>⚠</span> ' . count($conflicts) . ' chemin(s) blacklisté(s) correspondent à des fichiers existants.</div>';
        echo '<div class="card"><table>';
        echo '<tr><th>Chemin blacklisté</th><th>Action</th></tr>';
        foreach ($conflicts as $path) {
            echo '<tr><td><code>' . htmlspecialchars($path) . '</code></td><td>';
            echo '<form method="post" style="display:inline;">';
            echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">';
            echo '<input type="hidden" name="action" value="remove_bl">';
            echo '<input type="hidden" name="path" value="' . htmlspecialchars($path) . '">';
            echo '<button type="submit" class="btn btn-secondary btn-sm">Retirer</button>';
            echo '</form></td></tr>';
        }
        echo '</table></div>';
    }

    echo '<div class="btn-row"><a href="install.php?step=5" class="btn btn-primary">Continuer →</a></div>';
}

// ------------------------------------------------------------------
// Étape 5 : Clé API AbuseIPDB
// ------------------------------------------------------------------
function stepApiKey($csrfToken) {
    $saved = false;
    $apiError = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api_key'])) {
        verifyCsrf();
        // FIX: format ini correct (plus de $api_key = "val", mais api_key = val)
        $apiKey = trim($_POST['api_key'] ?? '');
        $apiKey = preg_replace('/[^a-zA-Z0-9\-_]/', '', $apiKey);
        if (strlen($apiKey) >= 20) {
            // Merge dans pipou.ini sans écraser les autres clés (ex: wordpress)
            $pipouFile = __DIR__ . '/pipou.ini';
            $existing = file_exists($pipouFile) ? (parse_ini_file($pipouFile) ?: []) : [];
            $existing['api_key'] = $apiKey;
            $iniContent = '';
            foreach ($existing as $k => $v) { $iniContent .= "$k = $v\n"; }
            if (file_put_contents($pipouFile, $iniContent) !== false) {
                $saved = true;
            } else {
                $apiError = 'Erreur lors de l\'écriture du fichier pipou.ini.';
            }
        } else {
            $apiError = 'Clé API invalide (minimum 20 caractères alphanumériques).';
        }
    }

    echo '<h1>Clé API AbuseIPDB</h1>';
    echo '<p class="subtitle">Configuration de la clé API pour la vérification des IPs suspectes.</p>';

    if ($saved) {
        echo '<div class="alert alert-success"><span>✓</span> Clé API enregistrée avec succès.</div>';
        echo '<div class="btn-row"><a href="install.php?step=6" class="btn btn-primary">Continuer →</a></div>';
        return;
    }

    // Vérifier si déjà configurée
    $existing = '';
    if (file_exists(__DIR__ . '/pipou.ini')) {
        $ini = parse_ini_file(__DIR__ . '/pipou.ini');
        $existing = $ini['api_key'] ?? '';
    }

    if ($existing) {
        echo '<div class="alert alert-success"><span>✓</span> Clé API déjà configurée.</div>';
    }

    if ($apiError) {
        echo '<div class="alert alert-error"><span>✗</span> ' . htmlspecialchars($apiError) . '</div>';
    }

    echo '<div class="card">';
    echo '<p>Obtenez une clé gratuite sur <a href="https://www.abuseipdb.com/register?plan=free" target="_blank" class="ext">AbuseIPDB ↗</a></p>';
    echo '<br><form method="post">';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">';
    echo '<div class="form-group">';
    echo '<label>Clé API AbuseIPDB</label>';
    echo '<input type="text" name="api_key" placeholder="ex: a3f7c2..." autocomplete="off" value="' . htmlspecialchars($existing) . '">';
    echo '<div class="form-hint">Caractères autorisés : lettres, chiffres, tirets, underscores.</div>';
    echo '</div>';
    echo '<div class="btn-row"><button type="submit" class="btn btn-primary">Enregistrer</button></div>';
    echo '</form></div>';

    echo '<div class="btn-row"><a href="install.php?step=6" class="btn btn-secondary">Passer cette étape</a></div>';
}

// ------------------------------------------------------------------
// Étape 6 : Identifiants administrateur
// ------------------------------------------------------------------
function stepAdminCredentials($csrfToken) {
    $credError = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
        verifyCsrf();
        $login    = trim($_POST['admin_login'] ?? '');
        $password = $_POST['admin_password'] ?? '';

        if (!preg_match('/^[a-zA-Z0-9_\-\.]{3,32}$/', $login)) {
            $credError = 'Login invalide (3–32 caractères : lettres, chiffres, -, _, .).';
        } elseif (strlen($password) < 8) {
            $credError = 'Le mot de passe doit faire au moins 8 caractères.';
        } else {
            $_SESSION['admin_login']    = $login;
            $_SESSION['admin_password'] = $password;
            echo '<h1>Identifiants administrateur</h1>';
            echo '<div class="alert alert-success"><span>✓</span> Identifiants enregistrés en session.</div>';
            echo '<div class="btn-row"><a href="install.php?step=7" class="btn btn-success">Procéder à l\'installation →</a></div>';
            return;
        }
    }

    echo '<h1>Identifiants administrateur</h1>';
    echo '<p class="subtitle">Définissez le login et mot de passe pour l\'interface de gestion (protégée par HTTP Basic Auth).</p>';

    if ($credError) {
        echo '<div class="alert alert-error"><span>✗</span> ' . htmlspecialchars($credError) . '</div>';
    }

    echo '<div class="card"><form method="post">';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">';
    echo '<div class="form-group"><label>Login</label>';
    echo '<input type="text" name="admin_login" placeholder="ex: admin" autocomplete="off" required>';
    echo '<div class="form-hint">3 à 32 caractères : lettres, chiffres, tirets, underscores, points.</div></div>';
    echo '<div class="form-group"><label>Mot de passe</label>';
    echo '<input type="password" name="admin_password" placeholder="Minimum 8 caractères" required>';
    echo '</div>';
    echo '<div class="btn-row"><button type="submit" class="btn btn-primary">Valider les identifiants</button></div>';
    echo '</form></div>';
}

// ------------------------------------------------------------------
// Étape 7 : Installation finale
// ------------------------------------------------------------------
function stepInstall($csrfToken) {
    echo '<h1>Installation finale</h1>';
    echo '<p class="subtitle">Déploiement du système de sécurité.</p>';

    if (empty($_SESSION['admin_login']) || empty($_SESSION['admin_password'])) {
        echo '<div class="alert alert-error"><span>✗</span> Identifiants admin manquants. <a href="install.php?step=6">Retour à l\'étape 6</a></div>';
        return;
    }

    // Confirmation avant de lancer
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['confirm_install'])) {
        echo '<div class="alert alert-warning"><span>⚠</span> Cette action va modifier le <code>.htaccess</code>, créer <code>.htpasswd</code>, déployer <code>gestion.php</code> et les pages d\'erreur.</div>';
        echo '<div class="card"><form method="post">';
        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">';
        echo '<input type="hidden" name="confirm_install" value="1">';
        echo '<p style="margin-bottom:1rem;">Tout est configuré. Lancez l\'installation :</p>';
        echo '<div class="btn-row"><button type="submit" class="btn btn-success">🚀 Lancer l\'installation</button></div>';
        echo '</form></div>';
        return;
    }

    verifyCsrf();

    $login    = $_SESSION['admin_login'];
    $password = $_SESSION['admin_password'];
    $htFile   = __DIR__ . '/.htaccess';
    $log      = [];
    $errors   = [];

    // 1 — .htaccess base
    if (!file_exists($htFile)) {
        file_put_contents($htFile, "Order Deny,Allow\n");
        $log[] = '.htaccess créé';
    } else {
        $content = file_get_contents($htFile);
        if (strpos($content, 'Order Deny,Allow') === false) {
            file_put_contents($htFile, "Order Deny,Allow\n" . $content);
        }
        $log[] = '.htaccess vérifié';
    }

    // 2 — Fichier index
    $indexFiles  = ['index.php', 'index.htm', 'index.html'];
    $existingIdx = null;
    foreach ($indexFiles as $f) {
        if (file_exists($f)) { $existingIdx = $f; break; }
    }
    $statsCode = "include 'debut.php';";

    if ($existingIdx === null && file_exists('sample_index')) {
        rename('sample_index', 'index.php');
        $log[] = 'sample_index renommé en index.php';
    } elseif ($existingIdx === 'index.php') {
        $content = file_get_contents('index.php');
        $lines   = file('index.php', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!isset($lines[1]) || trim($lines[1]) !== $statsCode) {
            if (strpos(trim($content), '<?php') === 0) {
                $nc = preg_replace('/^<\?php\s*/', "<?php\n$statsCode\n", $content);
            } else {
                $nc = "<?php\n$statsCode\n?>\n" . $content;
            }
            file_put_contents('index.php', $nc);
            $log[] = 'index.php mis à jour';
        } else {
            $log[] = 'index.php déjà configuré';
        }
    } elseif (in_array($existingIdx, ['index.htm', 'index.html'])) {
        $old = file_get_contents($existingIdx);
        file_put_contents('index.php', "<?php\n$statsCode\n?>\n" . $old);
        rename($existingIdx, 'OLD___' . $existingIdx);
        $log[] = 'index HTML converti en PHP';
    }

    // 3 — .htpasswd (hors webroot)
    $htpasswdPath = dirname(getcwd()) . '/.htpasswd';
    $hash = password_hash($password, PASSWORD_BCRYPT);
    if (file_put_contents($htpasswdPath, "$login:$hash\n") !== false) {
        $log[] = '.htpasswd créé';
    } else {
        $errors[] = 'Impossible d\'écrire .htpasswd dans ' . htmlspecialchars(dirname(getcwd()));
    }

    // 4 — Règles de protection
    $protectedFiles = [
        'stats.html', 'perdus_logs.html', '404_visitor_log.txt',
        'blacklist.txt', 'gestion.php', 'debut.php', 'sample_index',
        'desactivated.txt', 'debut404wpclassictheme.php', 'ifwordpress.php',
        'blacklistverif.php', 'pipou.ini',
    ];
    $rules = "\n";
    foreach ($protectedFiles as $f) {
        $rules .= "<Files \"$f\">\n    AuthType Basic\n    AuthName \"Restricted\"\n    AuthUserFile $htpasswdPath\n    Require valid-user\n</Files>\n\n";
    }
    $rules .= "<Files \"gestion-template.php\">\n    Order Deny,Allow\n    deny from all\n</Files>\n\n";
    $rules .= "<Files \"setup.token\">\n    Order Deny,Allow\n    deny from all\n</Files>\n\n";
    $rules .= "ErrorDocument 404 /404.php\nErrorDocument 403 /403_.html\n";
    file_put_contents($htFile, $rules, FILE_APPEND);
    $log[] = 'Règles de protection .htaccess ajoutées';

    // 5 — Règles de réécriture
    $rewrite = "\nRewriteEngine On\n"
        . "RewriteCond %{THE_REQUEST} ^[A-Z]{3,}\\s([^.]+)\\.php [NC]\n"
        . "RewriteRule ^ %1.html [R=301,L]\n"
        . "RewriteCond %{REQUEST_FILENAME} !-f\n"
        . "RewriteRule ^([^/]+)\\.html$ \$1.php [L]\n";
    $htContent = file_get_contents($htFile);
    if (strpos($htContent, 'RewriteEngine On') === false) {
        file_put_contents($htFile, $rewrite, FILE_APPEND);
        $log[] = 'Règles de réécriture ajoutées';
    } else {
        $log[] = 'Règles de réécriture déjà présentes';
    }

    // 6 — Pages d'erreur (HTTP_HOST échappé)
    $host = htmlspecialchars($_SERVER['HTTP_HOST'], ENT_QUOTES, 'UTF-8');

    file_put_contents('yon-maru-yon.html',
        '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>404</title>'
        . '<style>body{font-family:Arial,sans-serif;text-align:center;padding:50px;background:#f5f5f5;}'
        . 'h1{color:#d9534f;font-size:48px;}p{color:#666;}</style>'
        . '</head><body><h1>404</h1><p>Page non trouvée.</p>'
        . '<p><a href="/">Retour à l\'accueil</a></p>'
        . '<p style="font-size:12px;color:#aaa;">© ' . $host . '</p>'
        . '</body></html>'
    );
    $log[] = 'yon-maru-yon.html créé';

    file_put_contents('403_.html',
        '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>403</title>'
        . '<style>body{font-family:Arial,sans-serif;text-align:center;padding:50px;background:#f5f5f5;}'
        . 'h1{color:#d9534f;font-size:48px;}p{color:#666;}</style>'
        . '</head><body><h1>403</h1><p>Accès interdit.</p>'
        . '<p><a href="/">Retour à l\'accueil</a></p>'
        . '<p style="font-size:12px;color:#aaa;">© ' . $host . '</p>'
        . '</body></html>'
    );
    $log[] = '403_.html créé';

    // 7 — gestion.php
    $tplContent = file_get_contents(__DIR__ . '/gestion-template.php');
    if ($tplContent !== false) {
        file_put_contents('gestion.php', $tplContent);
        $log[] = 'gestion.php déployé';
    } else {
        $errors[] = 'gestion-template.php introuvable';
    }

    // 8 — Bloquer les scripts d'installation
    // install.php : AuthType Basic (pas deny from all) pour rester accessible à l'étape 8
    $blockRules = "\n<Files \"install.php\">\n    AuthType Basic\n    AuthName \"Restricted\"\n    AuthUserFile $htpasswdPath\n    Require valid-user\n</Files>\n"
        . "<Files \"installation-de-gestion.php\">\n    Order Deny,Allow\n    deny from all\n</Files>\n"
        . "<Files \"installation_beta3.php\">\n    Order Deny,Allow\n    deny from all\n</Files>\n";
    file_put_contents($htFile, $blockRules, FILE_APPEND);
    $log[] = 'Scripts d\'installation bloqués dans .htaccess';

    // Nettoyage session
    unset($_SESSION['admin_login'], $_SESSION['admin_password']);

    // Affichage
    echo '<div class="card">';
    foreach ($log as $entry) {
        echo '<div class="alert alert-success"><span>✓</span> ' . htmlspecialchars($entry) . '</div>';
    }
    foreach ($errors as $err) {
        echo '<div class="alert alert-error"><span>✗</span> ' . htmlspecialchars($err) . '</div>';
    }
    echo '</div>';

    if (empty($errors)) {
        echo '<div class="alert alert-success" style="font-size:1rem;font-weight:700;"><span>🎉</span> Installation réussie !</div>';
        echo '<div class="btn-row"><a href="install.php?step=8" class="btn btn-success">Finaliser →</a></div>';
    }
}

// ------------------------------------------------------------------
// Étape 8 : Fin
// ------------------------------------------------------------------
function stepDone() {
    echo '<h1>Installation terminée</h1>';
    echo '<p class="subtitle">Le système de sécurité est opérationnel.</p>';

    echo '<div class="alert alert-success"><span>✓</span> Tous les composants ont été installés et configurés.</div>';

    echo '<div class="card">';
    echo '<h3 style="margin-bottom:1rem;color:#1a1a2e;">Que faire maintenant ?</h3>';
    echo '<ul style="line-height:2.2;list-style:none;padding:0;">';
    echo '<li>→ <a href="/gestion.html" class="ext">Accéder à l\'interface de gestion ↗</a></li>';
    echo '<li>→ Supprimez <code>install.php</code> du serveur (ou il sera auto-supprimé ci-dessous)</li>';
    echo '<li>→ Vérifiez que <code>setup.token</code> a bien été supprimé</li>';
    echo '</ul></div>';

    // Auto-suppression
    $cleaned = [];
    if (file_exists(SETUP_TOKEN_FILE) && unlink(SETUP_TOKEN_FILE)) {
        $cleaned[] = 'setup.token supprimé';
    }
    if (file_exists(__FILE__) && unlink(__FILE__)) {
        $cleaned[] = 'install.php supprimé';
    }
    foreach ($cleaned as $item) {
        echo '<div class="alert alert-info"><span>✓</span> ' . htmlspecialchars($item) . '</div>';
    }

    echo '<div class="btn-row" style="margin-top:1rem;">';
    echo '<a href="/gestion.html" class="btn btn-success">Aller à l\'interface de gestion →</a>';
    echo '</div>';
}
