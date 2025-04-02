<?php


function getUserIP() {
    return $_SERVER['REMOTE_ADDR'];
}
// Fonction pour obtenir la page référente
function getReferrer() {
    return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'Direct';
}
// Fonction pour obtenir la page demandée
function getRequestedPage() {
    return $_SERVER['REQUEST_URI'];
}


// Fonction pour obtenir l'adresse IP du visiteur shiro maj 20250305 flock
function logVisitorInfo($ip, $referrer, $requestedPage) {
    $date = date('Y-m-d H:i:s');
    $logEntry = "<tr><td>$date</td><td>$ip</td><td>$referrer</td><td>$requestedPage</td></tr>\n";

    // Enregistrer la page demandée dans un fichier texte
    // file_put_contents('404_visitor_log.txt', $requestedPage . "\n", FILE_APPEND);
	
	$logFile = '404_visitor_log.txt';
	    $fpLog = fopen($logFile, 'a'); // Ouvrir en mode ajout ('a')
	    if ($fpLog) {
	        flock($fpLog, LOCK_EX); // Verrouillage exclusif
	        fwrite($fpLog, "$requestedPage\n");
	        fflush($fpLog); // S'assure que tout est écrit avant de relâcher le verrou
	        flock($fpLog, LOCK_UN); // Déverrouiller
	        fclose($fpLog);
	    }

    // Fichier HTML pour les logs
    $filePath = 'perdus_logs.html';

    // Contenu initial si le fichier n'existe pas
    $initialContent = "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Logs des visiteurs perdus</title>
    <script src='https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js'></script>
    <script src='https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js'></script>
    <link rel='stylesheet' href='https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css'>
    <script>
        $(document).ready(function() {
            $('#visitorTable').DataTable({
                'order': [[0, 'desc']],
                'pageLength': 25
            });
        });
    </script>
</head>
<body>
    <h2>Perdus</h2>
    <table id='visitorTable' class='display'>
        <thead>
            <tr>
                <th>Date</th>
                <th>IP</th>
                <th>Referrer</th>
                <th>Requested Page</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</body>
</html>";

    // Ouvrir le fichier en mode lecture/écriture ('c+' crée le fichier s'il n'existe pas)
    $fp = fopen($filePath, 'c+');
    if ($fp === false) {
        die("Erreur lors de l'ouverture de $filePath.");
    }

    // Verrouiller le fichier pour éviter les écritures concurrentes
    if (!flock($fp, LOCK_EX)) {
        die("Impossible de verrouiller $filePath.");
    }

    // Lire le contenu actuel
    $currentContent = stream_get_contents($fp);
    if (trim($currentContent) === '') {
        $currentContent = $initialContent;
    }

    // Insérer la nouvelle entrée avant </tbody>
    if (strpos($currentContent, '</tbody>') !== false) {
        $currentContent = str_replace('</tbody>', $logEntry . '</tbody>', $currentContent);
    } else {
        // Si </tbody> est absent (problème de fichier corrompu), on l'ajoute
        $currentContent .= "<tbody>" . $logEntry . "</tbody></table></body></html>";
    }

    // Réécriture sécurisée
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, $currentContent);
    fflush($fp);

    // Déverrouiller et fermer le fichier
    flock($fp, LOCK_UN);
    fclose($fp);
}


// Fonction pour vérifier la blacklist
function isBlacklisted($requestedPage) {
    $blacklist = file('blacklist.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return in_array($requestedPage, $blacklist);
}

// Fonction pour ajouter une règle deny dans .htaccess
function addDenyRule($ip) {
    $denyRule = "deny from $ip\n";
    file_put_contents('.htaccess', $denyRule, FILE_APPEND);
}

// Récupérer les informations du visiteur
$ip = getUserIP();
$referrer = getReferrer();
$requestedPage = getRequestedPage();

// Logger les informations
logVisitorInfo($ip, $referrer, $requestedPage);



// Vérifier si la page demandée est dans la blacklist
if (isBlacklisted($requestedPage)) {
    // Ajouter une règle deny dans .htaccess
    addDenyRule($ip);
    header("Location: /403_.html");
	exit();
} else {
    // Si la page n'est pas dans la blacklist, tenter de remplacer .html par .php
    $requestedPageModified = str_replace('.html', '.php', $requestedPage);
	
    
    if (isBlacklisted($requestedPageModified)) {
        // Ajouter une règle deny dans .htaccess
        addDenyRule($ip);
        header("Location: /403_.html");
        exit();
    } else {
        // Si le chemin modifié n'est pas non plus dans la blacklist
		$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
		$host = $_SERVER['HTTP_HOST'];  // Récupère le domaine (ex: plasticthreat.nihon.best)
		header("Location: $protocol://$host/yon-maru-yon.html");
		exit();
    }
}
