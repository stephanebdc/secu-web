<?php
// ATTENTION : Ce fichier est le moteur d'installation.
// Ne pas l'appeler directement — utilisez install.php
session_start();

// Vérifier si l'installation est déjà terminée
if (isset($_SESSION['installation_complete'])) {
    header("Location: gestion.php");
    exit();
}

// Gestion du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login']) && isset($_POST['password'])) {

    // 1 — .htaccess base
    $htaccess_file = __DIR__ . '/.htaccess';
    if (!file_exists($htaccess_file)) {
        file_put_contents($htaccess_file, "Order Deny,Allow\n");
    } else {
        $content = file_get_contents($htaccess_file);
        if (strpos($content, 'Order Deny,Allow') === false) {
            $content = "Order Deny,Allow\n" . $content;
            file_put_contents($htaccess_file, $content);
        }
    }

    // 2 — Gestion du fichier index
    $index_files = ['index.php', 'index.htm', 'index.html'];
    $existing_index = null;
    foreach ($index_files as $file) {
        if (file_exists($file)) { $existing_index = $file; break; }
    }
    $stats_code = "include 'debut.php';";

    if ($existing_index === null) {
        if (file_exists('sample_index')) {
            rename('sample_index', 'index.php');
        }
    } elseif ($existing_index === 'index.php') {
        $content = file_get_contents('index.php');
        $lines   = file('index.php', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!isset($lines[0], $lines[1]) || trim($lines[1]) !== $stats_code) {
            if (strpos(trim($content), '<?php') === 0) {
                $new_content = preg_replace('/^<\?php\s*/', "<?php\n$stats_code\n", $content);
            } elseif (strpos(trim($content), '<?') === 0) {
                $new_content = preg_replace('/^<\?\s*/', "<?php\n$stats_code\n", $content);
            } else {
                $new_content = "<?php\n$stats_code\n?>\n" . $content;
            }
            file_put_contents('index.php', $new_content);
        }
    } elseif (in_array($existing_index, ['index.htm', 'index.html'])) {
        $old_content = file_get_contents($existing_index);
        file_put_contents('index.php', "<?php\n$stats_code\n?>\n" . $old_content);
        rename($existing_index, 'OLD___index.html');
    }

    // 3 — .htpasswd (hors document root)
    $login    = trim($_POST['login']);
    $password = password_hash(trim($_POST['password']), PASSWORD_BCRYPT);
    $htpasswd_path = dirname(getcwd()) . '/.htpasswd';
    file_put_contents($htpasswd_path, "$login:$password\n");

    // 4 — Règles de protection .htaccess
    // FIX: pipou.ini et setup.token également protégés
    $protection_rules = <<<EOT

<Files "stats.html">
    AuthType Basic
    AuthName "Restricted Access"
    AuthUserFile $htpasswd_path
    Require valid-user
</Files>

<Files "perdus_logs.html">
    AuthType Basic
    AuthName "Restricted Access"
    AuthUserFile $htpasswd_path
    Require valid-user
</Files>

<Files "404_visitor_log.txt">
    AuthType Basic
    AuthName "Restricted Access"
    AuthUserFile $htpasswd_path
    Require valid-user
</Files>

<Files "blacklist.txt">
    AuthType Basic
    AuthName "Restricted Access"
    AuthUserFile $htpasswd_path
    Require valid-user
</Files>

<Files "gestion.php">
    AuthType Basic
    AuthName "Restricted Access"
    AuthUserFile $htpasswd_path
    Require valid-user
</Files>

<Files "debut.php">
    AuthType Basic
    AuthName "Restricted Access"
    AuthUserFile $htpasswd_path
    Require valid-user
</Files>

<Files "sample_index">
    AuthType Basic
    AuthName "Restricted Access"
    AuthUserFile $htpasswd_path
    Require valid-user
</Files>

<Files "desactivated.txt">
    AuthType Basic
    AuthName "Restricted Access"
    AuthUserFile $htpasswd_path
    Require valid-user
</Files>

<Files "debut404wpclassictheme.php">
    AuthType Basic
    AuthName "Restricted Access"
    AuthUserFile $htpasswd_path
    Require valid-user
</Files>

<Files "ifwordpress.php">
    AuthType Basic
    AuthName "Restricted Access"
    AuthUserFile $htpasswd_path
    Require valid-user
</Files>

<Files "blacklistverif.php">
    AuthType Basic
    AuthName "Restricted Access"
    AuthUserFile $htpasswd_path
    Require valid-user
</Files>

<Files "pipou.ini">
    AuthType Basic
    AuthName "Restricted Access"
    AuthUserFile $htpasswd_path
    Require valid-user
</Files>

<Files "setup.token">
    Order Deny,Allow
    deny from all
</Files>

<Files "gestion-template.php">
    Order Deny,Allow
    deny from all
</Files>

ErrorDocument 404 /404.php
ErrorDocument 403 /403_.html
EOT;

    file_put_contents($htaccess_file, $protection_rules, FILE_APPEND);

    // 5 — Pages d'erreur
    // FIX: Échapper HTTP_HOST pour éviter toute injection dans les fichiers HTML générés
    $host = htmlspecialchars($_SERVER['HTTP_HOST'], ENT_QUOTES, 'UTF-8');

    $yon_maru_yon_content = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found</title>
    <style>
        body { background-color: #f5f5f5; color: #333; font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        h1 { color: #d9534f; font-size: 48px; }
        p { color: #f0ad4e; font-size: 18px; }
        .message { background-color: #fffbea; border: 1px solid #ddd; display: inline-block; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .footer { color: #888; font-size: 14px; }
    </style>
</head>
<body>
    <h1>404</h1>
    <div class="message"><p>Page non trouvée.</p></div>
    <div class="footer"><a href="/">© ' . $host . '</a></div>
</body>
</html>';
    file_put_contents('yon-maru-yon.html', $yon_maru_yon_content);

    $forbidden_content = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Forbidden</title>
    <style>
        body { background-color: #f5f5f5; color: #333; font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        h1 { color: #d9534f; font-size: 48px; }
        p { color: #f0ad4e; font-size: 18px; }
        .message { background-color: #fffbea; border: 1px solid #ddd; display: inline-block; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .footer { color: #888; font-size: 14px; }
    </style>
</head>
<body>
    <h1>403</h1>
    <div class="message"><p>Accès interdit.</p></div>
    <div class="footer">© ' . $host . '</div>
</body>
</html>';
    file_put_contents('403_.html', $forbidden_content);

    // 6 — Règles de réécriture
    $rewrite_rules = "\nRewriteEngine On\n" .
        "RewriteCond %{THE_REQUEST} ^[A-Z]{3,}\\s([^.]+)\\.php [NC]\n" .
        "RewriteRule ^ %1.html [R=301,L]\n" .
        "RewriteCond %{REQUEST_FILENAME} !-f\n" .
        "RewriteRule ^([^/]+)\\.html$ $1.php [L]\n";

    $htaccess_content = file_get_contents($htaccess_file);
    if (strpos($htaccess_content, "RewriteEngine On") === false) {
        file_put_contents($htaccess_file, $rewrite_rules, FILE_APPEND);
    }

    // 7 — gestion.php
    $gestion_content = file_get_contents(__DIR__ . '/gestion-template.php');
    if ($gestion_content !== false) {
        file_put_contents('gestion.php', $gestion_content);
    }

    // 8 — Bloquer l'accès aux scripts d'installation
    $final_rules = "\n<Files \"installation-de-gestion.php\">\n" .
        "Order Deny,Allow\n" .
        "deny from all\n" .
        "</Files>\n";
    file_put_contents($htaccess_file, $final_rules, FILE_APPEND);

    $_SESSION['installation_complete'] = true;

    // Redirection vers l'interface de gestion
    echo '<script>setTimeout(function(){ window.location.href = "gestion.html"; }, 2000);</script>';
    echo '<p>Installation terminée. Redirection en cours...</p>';
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Sécurité Web</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 500px; margin: 50px auto; padding: 20px; background-color: #f5f5f5; }
        form { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        input[type="text"], input[type="password"] { width: 100%; padding: 8px; margin: 10px 0; box-sizing: border-box; }
        input[type="submit"] { background-color: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        input[type="submit"]:hover { background-color: #45a049; }
    </style>
</head>
<body>
    <form method="POST">
        <h2>Installation du module de sécurité</h2>
        <label>Login administrateur :</label>
        <input type="text" name="login" required>
        <label>Mot de passe :</label>
        <input type="password" name="password" required>
        <input type="submit" value="Valider">
    </form>
</body>
</html>
