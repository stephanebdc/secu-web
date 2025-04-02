<?php
// installation.php date du 2 avril version 2
session_start();

$installationPassword = "nomusicNOLIFE83264";

// Vérification du mot de passe
if (!isset($_SESSION['password_validated']) || $_SESSION['password_validated'] !== true) {
    // Si le mot de passe a été soumis via le formulaire
    if (isset($_POST['password'])) {
        // Vérifier si le mot de passe soumis est correct
        if ($_POST['password'] === $installationPassword) {
            $_SESSION['password_validated'] = true; // Marquer la validation dans la session
        } else {
            // Mot de passe incorrect : afficher un message et le formulaire
            echo '<p>Mot de passe incorrect.</p>';
            echo '<form method="post">
            <label>Quelle est la couleur variable et piquante du cheval rose de Anne-Charlotte Hassan Cohen Martin ? </label>
            <input type="password" name="password" required>
            <input type="submit" value="Valider">
          </form>';
    exit;
}
} else {
        // Aucun mot de passe soumis : afficher le formulaire
        echo '<form method="post">
                <label>Quelle est la couleur variable et piquante du cheval rose de Anne-Charlotte Hassan Cohen Martin ?</label>
                <input type="password" name="password" required>
                <input type="submit" value="Valider">
              </form>';
        exit; // Arrêter l'exécution ici
    }
}

// Détermination de l'étape actuelle (défaut : étape 1)
$step = isset($_GET['step']) ? intval($_GET['step']) : 1;

// Liste des fichiers à vérifier (étape 1)
$requiredFiles = [
    'debut.php',
    '404.php',
    'gestion-template.php',
    'sample_index',
    'blacklist.txt',
    'debut404wpclassictheme.php',
    'ifwordpress.php',
    'blacklistverif.php',
	'installation-de-gestion.php'
];

/**
 * Affiche une barre de progression verticale.
 * Les étapes réalisées (numéro inférieur ou égal à l'étape courante)
 * sont marquées par la classe "active".
 */
function render_progress_bar($currentStep) {
    $steps = [
        1 => "Vérification des fichiers",
        2 => "Sauvegarde .htaccess",
        3 => "Site WordPress ?",
        4 => "Vérification de la blacklist",
        5 => "Inscription et configuration de l'API Key",
		6 => "Installation de gestion",
		7 => "Installation terminée"
    ];
    echo '<div id="progress-container">';
    echo '<ul>';
    foreach($steps as $num => $label) {
        $class = ($currentStep >= $num) ? 'active' : '';
        echo "<li class='$class'>Étape $num : " . htmlspecialchars($label) . "</li>";
    }
    echo '</ul>';
    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Installation du système</title>
    <style>
        body { font-family: Arial, sans-serif; }
        /* Barre de progression verticale */
        #progress-container {
            position: fixed;
            top: 20px;
            left: 20px;
            width: 220px;
        }
        #progress-container ul {
            list-style: none;
            padding: 0;
			margin-top: 45px;
        }
        #progress-container li {
            margin: 20px 0;
            padding: 5px 10px;
            border-left: 4px solid #ccc;
        }
        #progress-container li.active {
            border-left-color: #4CAF50;
            font-weight: bold;
        }
        /* Zone de contenu */
        .content {
            margin-left: 260px;
            padding: 20px;
        }
        /* Boutons */
        .button {
            display: inline-block;
            padding: 8px 16px;
            margin: 5px;
            background-color: #4CAF50;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .button:hover { background-color: #45a049; }
        .vertical-buttons button {
            display: block;
            margin: 10px 0;
            padding: 10px 16px;
            cursor: pointer;
        }
    </style>
    <script>
        // Ouvre une URL dans une nouvelle fenêtre
        function openInNewWindow(url) {
            window.open(url, '_blank', 'width=800,height=600');
        }
    </script>
</head>
<body>
    <h1>Installation du système</h1>
	<br>
	<br>
	<br>
    <?php render_progress_bar($step); ?>
    <div class="content">
    <?php
    switch($step) {
        case 1:
            // ÉTAPE 1 : Vérification des fichiers
            echo "<h2>Étape 1 : Vérification des fichiers</h2>";
            $missing = [];
            foreach ($requiredFiles as $file) {
                if (!file_exists($file)) {
                    $missing[] = $file;
                }
            }
            if (!empty($missing)) {
                echo "<p>Les fichiers suivants sont manquants :</p><ul>";
                foreach ($missing as $m) {
                    echo "<li>" . htmlspecialchars($m) . "</li>";
                }
                echo "</ul>";
                echo "<p>Veuillez ajouter les fichiers manquants avant de continuer.</p>";
            } else {
                echo "<p>Tous les fichiers requis sont présents.</p>";
                echo '<a class="button" href="?step=2">Continuer</a>';
            }
            break;

        case 2:
            // ÉTAPE 2 : Sauvegarde du .htaccess
			echo "<h2>Étape 2 : Sauvegarde du .htaccess</h2>";
			if (file_exists('.htaccess')) {
			    $backupName = 'ANCIEN-htaccess-' . date('YmdHis');
			    if (copy('.htaccess', $backupName)) {
			        echo "<p>Sauvegarde réalisée : " . htmlspecialchars($backupName) . "</p>";

			        // Ajouter une ligne pour bloquer l'accès à la sauvegarde
			        $denyRule = "\n# Protection du fichier de sauvegarde\n<Files \"$backupName\">\n    deny from all\n</Files>\n";
        
			        if (file_put_contents('.htaccess', $denyRule, FILE_APPEND)) {
			            echo "<p>Règle ajoutée au .htaccess pour protéger la sauvegarde.</p>";
			        } else {
			            echo "<p>Erreur lors de l'ajout de la règle de protection.</p>";
			        }
			    } else {
			        echo "<p>Erreur lors de la sauvegarde du .htaccess.</p>";
			    }
			} else {
			    echo "<p>Aucun fichier .htaccess trouvé.</p>";
			}
			echo '<a class="button" href="?step=3">Continuer</a>';
			break;
        case 3:
            // ÉTAPE 3 : Vérifier si le site est un WordPress
            echo "<h2>Étape 3 : Le site est-il un WordPress ?</h2>";
            echo "<p>Si votre site est un WordPress, cliquez sur <strong>OUI</strong> pour lancer les vérifications spécifiques.</p>";
            echo '<div class="vertical-buttons">';
            echo '<button onclick="openInNewWindow(\'ifwordpress.php\')">OUI</button>';
            echo '<a class="button" href="?step=4">Non il est pas sous wordpress, on continue</a>';
            echo '</div>';
            break;

        case 4:
            // ÉTAPE 4 : Vérification de la blacklist
            echo "<h2>Étape 4 : Vérification de la blacklist</h2>";
            echo "<p>Souhaitez-vous vérifier les liens blacklist ?</p>";
			echo "<p>En fait cela verifie si il n'y a pas de fichers qui vont causer des faux positifs...</p>";
            echo '<div class="vertical-buttons">';
            echo '<button onclick="openInNewWindow(\'blacklistverif.php\')">OUI</button>';
            echo '<a class="button" href="?step=5">Continuer</a>';
            echo '</div>';
            break;
			
			case 5:
			    // ÉTAPE 5 : Inscription et configuration de l'API Key
			    echo "<h2>Étape 5 : Inscription et configuration de l'API Key</h2>";
			    echo "<p>Pour continuer, inscrivez-vous sur <a href='https://www.abuseipdb.com/register?plan=free' target='_blank'>AbuseIPDB</a> (c'est gratuit et sans engagement), puis saisissez votre API Key ci-dessous.</p>";
    
			    // Afficher le formulaire si la clé n'a pas encore été soumise
			    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['api_key'])) {
			        echo "<form method='post' action='?step=5'>";
			        echo "<label for='api_key'>Entrez votre API Key :</label>";
			        echo "<input type='text' name='api_key' id='api_key' required>";
			        echo "<input type='submit' value='Continuer'>";
			        echo "</form>";
			    } else {
			        // Traitement du formulaire
			        $user_api_key = trim($_POST['api_key']);
			        // Création du contenu du fichier pipou.ini
			        $config_content = '$api_key = "' . addslashes($user_api_key) . '"' . "\n";
			        if (file_put_contents('pipou.ini', $config_content) !== false) {
			            echo "<p>Configuration enregistrée avec succès.</p>";
			            echo '<a class="button" href="?step=6">Continuer</a>';
			        } else {
			            echo "<p>Erreur lors de l'écriture du fichier pipou.ini.</p>";
			        }
			    }
			    break;
			

        case 6:
            // ÉTAPE 6 : Installation de gestion
            echo "<h2>Étape 5 : Installation de gestion sécu</h2>";
            echo "<p>Lancez l'installation du système de gestion.</p>";
            echo '<div class="vertical-buttons">';
            echo '<button onclick="openInNewWindow(\'installation-de-gestion.php\')">Lancer installation-de-gestion.php</button>';
            echo '<a class="button" href="?step=7">Terminer (il faut cliquer)</a>';
			echo '</div>';
            break;

		

        case 7:
            // FIN : Installation terminée
            echo "<h2>Installation terminée</h2>";
			
			
			$moimeme = __FILE__;

			if (unlink($moimeme)) {
			    echo "Installation terminée.";
			} else {
			    echo "Erreur lors de la suppression du script.";
			}
            echo "<p>Le système est maintenant installé.</p>";
            break;

        default:
            echo "<p>Étape non définie.</p>";
    }
    ?>
    </div>
</body>
</html>
