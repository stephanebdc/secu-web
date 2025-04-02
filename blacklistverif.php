<?php
// Nom du fichier de blacklist
$blacklist_file = 'blacklist.txt';

// Fonction pour lire la blacklist
function read_blacklist($filename) {
    $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return $lines ? $lines : [];
}

// Fonction pour réécrire la blacklist
function write_blacklist($filename, $lines) {
    $data = implode("\n", $lines);
    file_put_contents($filename, $data);
}

// Traitement des actions
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'delete' && isset($_GET['path'])) {
    $path_to_delete = $_GET['path'];
    $lines = read_blacklist($blacklist_file);
    // Retirer uniquement les lignes identiques à celle sélectionnée
    $lines = array_filter($lines, function($line) use ($path_to_delete) {
        return trim($line) !== $path_to_delete;
    });
    write_blacklist($blacklist_file, $lines);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if ($action === 'info' && isset($_GET['path'])) {
    $path_requested = $_GET['path'];
    // Pour la vérification, on retire la partie query string le cas échéant
    $clean_path = preg_replace('/\?.*/', '', $path_requested);
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . $clean_path;
    if (!file_exists($fullPath)) {
        echo "Fichier/dossier non trouvé.";
        exit;
    }
    $info = [
        "Nom"                => basename($fullPath),
        "Chemin complet"     => $fullPath,
        "Type"               => is_dir($fullPath) ? 'Dossier' : 'Fichier',
        "Date de création"   => date("Y-m-d H:i:s", filectime($fullPath)),
        "Date de modification"=> date("Y-m-d H:i:s", filemtime($fullPath)),
        "Droits d'accès"     => substr(sprintf('%o', fileperms($fullPath)), -4),
        "Taille"             => is_file($fullPath) ? filesize($fullPath) . " octets" : "N/A"
    ];
    echo "<h2>Infos pour : " . htmlspecialchars($path_requested) . "</h2>";
    echo "<table border='1'>";
    foreach($info as $key => $value) {
        echo "<tr><th>" . htmlspecialchars($key) . "</th><td>" . htmlspecialchars($value) . "</td></tr>";
    }
    echo "</table>";
    echo "<p><a href='" . $_SERVER['PHP_SELF'] . "'>Retour</a></p>";
    exit;
}

// Lecture de la blacklist et vérification de l'existence des chemins
$lines = read_blacklist($blacklist_file);
$activePaths = [];
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '') continue;
    // Pour vérifier sur le système de fichiers, on retire la partie query string si présente
    $clean_line = preg_replace('/\?.*/', '', $line);
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . $clean_line;
    if (file_exists($fullPath)) {
        $activePaths[] = $line;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Vérification de la blacklist</title>
</head>
<body>
<h1>Chemins actifs dans la blacklist</h1>
<?php if (empty($activePaths)): ?>
    <p>Aucun chemin actif trouvé.</p>
<?php else: ?>
    <table border="1" cellspacing="0" cellpadding="4">
        <tr>
            <th>Chemin</th>
            <th>Actions</th>
        </tr>
        <?php foreach($activePaths as $path): ?>
            <tr>
                <td><?php echo htmlspecialchars($path); ?></td>
                <td>
                    <a href="?action=delete&amp;path=<?php echo urlencode($path); ?>">Supprimer de la liste</a>
                    &nbsp;|&nbsp;
                    <a href="?action=info&amp;path=<?php echo urlencode($path); ?>">Infos</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
</body>
</html>
