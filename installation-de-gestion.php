<?php
// ATTENTION Pour installer le systeme ne pas lancer ce fichier directement, utilisez installation-beta(-num-de-version).php
// 1- Démarrer la session PHP
session_start();

// Vérifier si l'installation est déjà terminée
if (isset($_SESSION['installation_complete'])) {
    header("Location: gestion.php");
    exit();
}

// Gestion du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login']) && isset($_POST['password'])) {
    // 2- Gestion du fichier .htaccess
    $htaccess_file = '.htaccess';
    if (!file_exists($htaccess_file)) {
        $initial_content = "Order Deny,Allow\n";
        file_put_contents($htaccess_file, $initial_content);
    } else {
        $content = file_get_contents($htaccess_file);
        if (strpos($content, 'Order Deny,Allow') !== 0) {
            $content = "Order Deny,Allow\n" . $content;
            file_put_contents($htaccess_file, $content);
        }
    }
	
	
	// Vérification de l'existence d'un fichier index
	$index_files = ['index.php', 'index.htm', 'index.html'];
	$existing_index = null;
	foreach ($index_files as $file) {
	    if (file_exists($file)) {
	        $existing_index = $file;
	        break;
	    }
	}

	// Code à insérer pour les stats
	$stats_code = "include 'debut.php';";

	// Cas 1 : Aucun fichier index n'existe
	if ($existing_index === null) {
	    if (file_exists('sample_index')) {
	        rename('sample_index', 'index.php');
	        echo "sample_index renommé en index.php";
	    } else {
	        echo "Aucun fichier index trouvé et sample_index n'existe pas";
	    }
	}
	// Cas 2 : Un index.php existe
	elseif ($existing_index === 'index.php') {
	    $content = file_get_contents('index.php');
	    $lines = file('index.php', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

	    // Vérifier si les deux premières lignes sont déjà présentes
	    if (isset($lines[0], $lines[1]) && trim($lines[0]) === '<?php' && trim($lines[1]) === "include 'debut.php';") {
	        echo "Rien à faire, le code est déjà présent";
	    } else {
	        // Vérifier si le fichier commence par une balise PHP
	        if (strpos(trim($content), '<?php') === 0) {
	            // Insérer après '<?php'
	            $new_content = preg_replace('/^<\?php\s*/', "<?php\n$stats_code\n", $content);
	        } elseif (strpos(trim($content), '<?') === 0) {
	            // Remplacer '<?' par '<?php' suivi du code (si balises courtes)
	            $new_content = preg_replace('/^<\?\s*/', "<?php\n$stats_code\n", $content);
	        } else {
	            // Ajouter le code PHP au début
	            $new_content = "<?php\n$stats_code\n?>\n" . $content;
	        }
	        file_put_contents('index.php', $new_content);
	        echo "Code inséré dans index.php";
	    }
	}
	// Cas 3 : Un index.htm ou index.html existe
	elseif ($existing_index === 'index.htm' || $existing_index === 'index.html') {
	    $old_content = file_get_contents($existing_index);
	    $new_index_content = "<?php\n$stats_code\n?>\n" . $old_content;
	    file_put_contents('index.php', $new_index_content);
	    rename($existing_index, 'OLD___index.html');
	    echo "index.php créé et ancien fichier renommé en OLD___index.html";
	}


    // 3- Création du fichier .htpasswd
    $login = trim($_POST['login']);
    $password = password_hash(trim($_POST['password']), PASSWORD_BCRYPT);
    $htpasswd_path = dirname(getcwd()) . '/.htpasswd';
    $htpasswd_content = "$login:$password\n";
    file_put_contents($htpasswd_path, $htpasswd_content);

    // 4- Ajout des règles de protection dans .htaccess
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

<Files gestion-template.php>
Order Deny,Allow
deny from all
</Files>

ErrorDocument 404 /404.php
ErrorDocument 403 /403_.html
EOT;

    file_put_contents($htaccess_file, $protection_rules, FILE_APPEND);

   
    // 6- Création du fichier yon-maru-yon.html
    $yon_maru_yon_content = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Four Hundred Four </title>
    <style>
        body {
            background-color: #f5f5f5;
            color: #333;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
        }
        h1 {
            color: #d9534f;
            font-size: 48px;
        }
        p {
            color: #f0ad4e;
            font-size: 18px;
        }
        .message {
            background-color: #fffbea;
            border: 1px solid #ddd;
            display: inline-block;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            color: #888;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <h1>404</h1>
    <div class="message">
        <p>Thank you for visiting my site. page not found</p>
    </div>
	<div class="footer">
	    <a href="/">© ' . $_SERVER['HTTP_HOST'] . '</a>
	</div>
</body>
</html>';
    file_put_contents('yon-maru-yon.html', $yon_maru_yon_content);

    // 7- Création du fichier 403_.html
    $forbidden_content = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Four Hundred Three </title>
    <style>
        body {
            background-color: #f5f5f5;
            color: #333;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
        }
        h1 {
            color: #d9534f;
            font-size: 48px;
        }
        p {
            color: #f0ad4e;
            font-size: 18px;
        }
        .message {
            background-color: #fffbea;
            border: 1px solid #ddd;
            display: inline-block;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            color: #888;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <h1>403</h1>
    <div class="message">
        <p>Thank you for visiting my site. maintenance? Forbidden? </p>
    </div>
    <div class="footer">
        © ' . $_SERVER['HTTP_HOST'] . '
    </div>
</body>
</html>';
    file_put_contents('403_.html', $forbidden_content);

    // 8- Ajout des règles de réécriture dans .htaccess
    $rewrite_rules = "\nRewriteEngine On\n" .
        "RewriteCond %{THE_REQUEST} ^[A-Z]{3,}\\s([^.]+)\\.php [NC]\n" .
        "RewriteRule ^ %1.html [R=301,L]\n" .
        "RewriteCond %{REQUEST_FILENAME} !-f\n" .
        "RewriteRule ^([^/]+)\\.html$ $1.php [L]\n";
	
	 $htaccess_content = file_get_contents($htaccess_file);
	 if (strpos($htaccess_content, "RewriteEngine On") !== false &&
	         strpos($htaccess_content, "RewriteCond %{THE_REQUEST} ^[A-Z]{3,}\\s([^.]+)\\.php [NC]") !== false &&
	         strpos($htaccess_content, "RewriteRule ^ %1.html [R=301,L]") !== false &&
	         strpos($htaccess_content, "RewriteCond %{REQUEST_FILENAME} !-f") !== false &&
	         strpos($htaccess_content, "RewriteRule ^([^/]+)\\.html$ $1.php [L]") !== false) {
        
	         echo "Les règles de réécriture sont déjà présentes. Aucun ajout nécessaire. suite de l'installation dans 3 secondes";
			 flush(); // Force l'affichage immédiat du message
			 sleep(3); // Attendre 3 secondes
			 
			  } else {
	
    file_put_contents($htaccess_file, $rewrite_rules, FILE_APPEND);
      }
	  
	
    // 9- Création du fichier gestion.php
    $gestion_content = file_get_contents(__DIR__ . '/gestion-template.php'); // Assurez-vous d'avoir le contenu complet séparément
    file_put_contents('gestion.php', $gestion_content);

    // 10- Finalisation : bloquer l'accès au fichier d'installation
    $final_rules = "\n<Files installation-de-gestion.php>\n" .
        "Order Deny,Allow\n" .
        "deny from all\n" .
        "</Files>\n";
    file_put_contents($htaccess_file, $final_rules, FILE_APPEND);

    // Marquer l'installation comme complète
    $_SESSION['installation_complete'] = true;
	
    // Div masqué contenant l'iframe
	echo '<div style="display: none;">';
	echo '<iframe id="preview-iframe" style="width: 300px; height: 200px; border: 1px solid #ccc;"></iframe>';
	echo '</div>';

	// JavaScript pour charger les pages dans l'iframe et rediriger après
	echo '<script>
	    // Charger index.php immédiatement
	    document.getElementById("preview-iframe").src = "index.php";
    
	    // Charger la page 404 après 5 secondes, puis rediriger
	    setTimeout(function() {
	        document.getElementById("preview-iframe").src = "pouetpouet.html";
        
	        // Rediriger vers gestion.html après un délai supplémentaire (par exemple 1 seconde)
	        setTimeout(function() {
	            window.location.href = "gestion.html";
	        }, 1000); // Délai pour laisser pouetpouet.html se charger
	    }, 5000); // Délai de 5 secondes avant de charger pouetpouet.html
	</script>';
	sleep(6);
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
        body {
            font-family: Arial, sans-serif;
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        form {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 8px;
            margin: 10px 0;
            box-sizing: border-box;
        }
        input[type="submit"] {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <form method="POST">
        <h2>Installation du module de sécurité</h2>
        <label>Login :</label>
        <input type="text" name="login" required>
        <label>Password :</label>
        <input type="password" name="password" required>
        <input type="submit" value="Valider">
    </form>
	<h2>après l'installation vous serez directement dans l'interface de gestion.</h2>
</body>
</html>