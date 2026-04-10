<?php
/**
 * Module WordPress — Injection du logger 404 dans le thème actif
 * Accessible uniquement depuis une session d'installation valide.
 */

session_start();

// FIX: Suppression du mot de passe en dur — on vérifie la session de l'installeur
if (empty($_SESSION['install_auth'])) {
    http_response_code(403);
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Accès refusé</title></head><body>";
    echo "<p>Accès non autorisé. Ce module doit être lancé depuis l'installeur.</p>";
    echo "</body></html>";
    exit;
}

if (!isset($_POST['action'])) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="utf-8">
        <title>Module WordPress</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 2rem; max-width: 600px; }
            h1 { color: #333; }
            .btn { padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
            .btn:hover { background: #45a049; }
            select { padding: 8px; font-size: 1rem; width: 100%; margin: 10px 0; }
        </style>
    </head>
    <body>
        <h1>Module WordPress</h1>
        <?php
        $themesDir = 'wp-content/themes';
        if (!is_dir($themesDir)) {
            echo "<p>Le dossier <code>wp-content/themes</code> n'existe pas. Ce site n'est pas un WordPress.</p>";
        } else {
            $themes = array_filter(scandir($themesDir), function($d) use ($themesDir) {
                return $d !== '.' && $d !== '..' && is_dir($themesDir . '/' . $d);
            });
            if (empty($themes)) {
                echo "<p>Aucun thème trouvé.</p>";
            } else {
                ?>
                <p>Sélectionnez le thème actif pour y injecter le logger 404 :</p>
                <form method="post">
                    <input type="hidden" name="action" value="install">
                    <select name="theme">
                        <?php foreach ($themes as $theme): ?>
                            <option value="<?php echo htmlspecialchars($theme); ?>"><?php echo htmlspecialchars($theme); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <br>
                    <button type="submit" class="btn">Installer le module</button>
                </form>
                <?php
            }
        }
        ?>
    </body>
    </html>
    <?php
    exit;
}

// Action d'installation
if ($_POST['action'] === 'install') {
    if (!isset($_POST['theme']) || empty($_POST['theme'])) {
        echo "Aucun thème sélectionné.";
        exit;
    }

    // FIX: Validation du nom de thème (pas de traversal, pas de caractères spéciaux)
    $selectedTheme = basename($_POST['theme']); // basename() empêche le path traversal
    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $selectedTheme)) {
        echo "Nom de thème invalide.";
        exit;
    }

    $themesDir  = 'wp-content/themes';
    $themePath  = "$themesDir/$selectedTheme";
    $targetFile = "$themePath/404.php";

    if (!is_dir($themePath)) {
        echo "Thème non trouvé.";
        exit;
    }
    if (!file_exists($targetFile)) {
        echo "Le fichier 404.php n'existe pas dans le thème sélectionné.";
        exit;
    }

    $content = file_get_contents($targetFile);
    if ($content === false) {
        echo "Impossible de lire le fichier 404.php.";
        exit;
    }

    $snippetFile = __DIR__ . '/debut404wpclassictheme.php';
    if (!file_exists($snippetFile)) {
        echo "Fichier de snippet introuvable.";
        exit;
    }
    $codeSnippet = file_get_contents($snippetFile);

    if (strpos($content, $codeSnippet) !== false) {
        echo "Le code est déjà installé dans le fichier.";
    } else {
        $content    = preg_replace('/^<\?php/', '', $content, 1);
        $newContent = $codeSnippet . $content;
        if (file_put_contents($targetFile, $newContent) === false) {
            echo "Erreur lors de l'écriture dans le fichier 404.php.";
            exit;
        }
        echo "✓ Code installé avec succès dans $targetFile.<br>";
    }

    // Auto-désactivation via .htaccess
    $htaccessFile = __DIR__ . "/.htaccess";
    $scriptName   = basename(__FILE__);
    $denyRule     = "\n<Files \"$scriptName\">\n    Order Allow,Deny\n    Deny from all\n</Files>\n";
    if (file_put_contents($htaccessFile, $denyRule, FILE_APPEND) === false) {
        echo "Avertissement : impossible de désactiver le script via .htaccess.";
    } else {
        echo "✓ Script désactivé.";
    }
    exit;
}
?>
