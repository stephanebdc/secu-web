<?php
// Gestion 20250402 — sécurisé
session_start();

// --- Protection CSRF ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

function verifyCsrf() {
    global $csrfToken;
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($csrfToken, $_POST['csrf_token'])
    ) {
        http_response_code(403);
        die('Token CSRF invalide.');
    }
}

// FIX: Validation stricte des IPs avant toute écriture dans .htaccess
function isValidIp($ip) {
    return filter_var(trim($ip), FILTER_VALIDATE_IP) !== false;
}

$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'logs';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrf();

    $action = $_POST['action'];

    if ($action === 'addblackpath') {
        $path = trim($_POST['path'] ?? '');
        if ($path !== '') {
            $blacklistFile = __DIR__ . '/blacklist.txt';
            $blacklist = file_exists($blacklistFile) ? file($blacklistFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
            if (!in_array($path, $blacklist)) {
                $content = file_exists($blacklistFile) ? file_get_contents($blacklistFile) : '';
                $prefix = ($content !== '' && substr($content, -1) !== "\n") ? "\n" : "";
                file_put_contents($blacklistFile, $prefix . $path . "\n", FILE_APPEND);
                $message = "Chemin ajouté à la blacklist : $path";

                $logFile = __DIR__ . '/404_visitor_log.txt';
                if (file_exists($logFile)) {
                    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    $newLines = array_filter($lines, fn($line) => trim($line) !== $path);
                    file_put_contents($logFile, count($newLines) > 0 ? implode("\n", $newLines) . "\n" : "");
                }
            } else {
                $message = "Chemin déjà présent dans la blacklist.";
            }
        } else {
            $message = "Chemin vide.";
        }
        $activeTab = 'addblackpath';

    } elseif ($action === 'deleteLogPath') {
        $path = trim($_POST['path'] ?? '');
        if ($path !== '') {
            $logFile = __DIR__ . '/404_visitor_log.txt';
            if (file_exists($logFile)) {
                $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $newLines = array_filter($lines, fn($line) => trim($line) !== $path);
                file_put_contents($logFile, count($newLines) > 0 ? implode("\n", $newLines) . "\n" : "");
                $message = "Chemin effacé du log : $path";
            } else {
                $message = "Fichier log non trouvé.";
            }
        } else {
            $message = "Chemin vide.";
        }
        $activeTab = 'addblackpath';

    } elseif ($action === 'desactivate') {
        $path = trim($_POST['path'] ?? '');
        if ($path !== '') {
            $blacklistFile = __DIR__ . '/blacklist.txt';
            if (file_exists($blacklistFile)) {
                $blacklist = file($blacklistFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $newList = array_filter($blacklist, fn($line) => trim($line) !== $path);
                file_put_contents($blacklistFile, implode("\n", $newList) . (count($newList) > 0 ? "\n" : ""));
            }
            $desactivatedFile = __DIR__ . '/desactivated.txt';
            $desactivated = file_exists($desactivatedFile) ? file($desactivatedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
            if (!in_array($path, $desactivated)) {
                file_put_contents($desactivatedFile, $path . "\n", FILE_APPEND);
            }
            $message = "Chemin désactivé : $path";
        }
        $activeTab = 'desactivatepath';

    } elseif ($action === 'reactivate') {
        $path = trim($_POST['path'] ?? '');
        if ($path !== '') {
            $desactivatedFile = __DIR__ . '/desactivated.txt';
            if (file_exists($desactivatedFile)) {
                $desactivated = file($desactivatedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $newList = array_filter($desactivated, fn($line) => trim($line) !== $path);
                file_put_contents($desactivatedFile, implode("\n", $newList) . (count($newList) > 0 ? "\n" : ""));
            }
            $blacklistFile = __DIR__ . '/blacklist.txt';
            $blacklist = file_exists($blacklistFile) ? file($blacklistFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
            if (!in_array($path, $blacklist)) {
                $content = file_exists($blacklistFile) ? file_get_contents($blacklistFile) : '';
                $prefix = ($content !== '' && substr($content, -1) !== "\n") ? "\n" : "";
                file_put_contents($blacklistFile, $prefix . $path . "\n", FILE_APPEND);
            }
            $message = "Chemin réactivé : $path";
        }
        $activeTab = 'desactivatepath';

    } elseif ($action === 'deleteDesactivated') {
        $path = trim($_POST['path'] ?? '');
        if ($path !== '') {
            $desactivatedFile = __DIR__ . '/desactivated.txt';
            if (file_exists($desactivatedFile)) {
                $lines = file($desactivatedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $newLines = array_filter($lines, fn($line) => trim($line) !== $path);
                file_put_contents($desactivatedFile, count($newLines) > 0 ? implode("\n", $newLines) . "\n" : "");
                $message = "Chemin effacé du fichier desactivated.txt : $path";
            } else {
                $message = "Fichier desactivated.txt non trouvé.";
            }
        } else {
            $message = "Chemin vide.";
        }
        $activeTab = 'desactivatepath';

    } elseif ($action === 'banIP') {
        $ip = trim($_POST['ip'] ?? '');
        // FIX: Validation stricte de l'IP avant écriture dans .htaccess
        if ($ip !== '' && isValidIp($ip)) {
            $htaccessFile = __DIR__ . '/.htaccess';
            if (file_exists($htaccessFile)) {
                $lines = file($htaccessFile);
                $found = false;
                foreach ($lines as $line) {
                    if (stripos($line, "deny from") !== false && strpos($line, $ip) !== false) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $content = file_get_contents($htaccessFile);
                    $prefix = ($content !== '' && substr($content, -1) !== "\n") ? "\n" : "";
                    file_put_contents($htaccessFile, $prefix . "deny from $ip\n", FILE_APPEND);
                    $message = "IP bannie : $ip";
                } else {
                    $message = "IP déjà bannie.";
                }
            } else {
                $message = ".htaccess non trouvé.";
            }
        } elseif ($ip === '') {
            $message = "IP vide.";
        } else {
            $message = "Format d'IP invalide.";
        }
        $activeTab = 'unban';

    } elseif ($action === 'rebannirIP') {
        $ip = trim($_POST['ip'] ?? '');
        // FIX: Validation stricte de l'IP
        if ($ip !== '' && isValidIp($ip)) {
            $htaccessFile = __DIR__ . '/.htaccess';
            if (file_exists($htaccessFile)) {
                $lines = file($htaccessFile);
                $newLines = array_map(function($line) use ($ip) {
                    $trimLine = trim($line);
                    if (preg_match('/^#deny from\s+' . preg_quote($ip, '/') . '$/i', $trimLine)) {
                        return 'deny from ' . $ip . "\n";
                    }
                    return $line;
                }, $lines);
                file_put_contents($htaccessFile, implode("", $newLines));
                $message = "IP rebannie : $ip";
            } else {
                $message = ".htaccess non trouvé.";
            }
        } elseif ($ip === '') {
            $message = "IP vide.";
        } else {
            $message = "Format d'IP invalide.";
        }
        $activeTab = 'unban';

    } elseif ($action === 'unban') {
        if (isset($_POST['ips']) && is_array($_POST['ips'])) {
            // FIX: Filtrer uniquement les IPs valides
            $ips = array_filter($_POST['ips'], fn($ip) => isValidIp($ip));
            $htaccessFile = __DIR__ . '/.htaccess';
            if (file_exists($htaccessFile)) {
                $lines = file($htaccessFile);
                $newLines = array_map(function($line) use ($ips) {
                    $trimLine = trim($line);
                    if (preg_match('/^(deny from)\s+(\S+)/i', $trimLine, $matches)) {
                        $ip = $matches[2];
                        if (in_array($ip, $ips) && strpos($trimLine, '#') !== 0) {
                            return '#' . $line;
                        }
                    }
                    return $line;
                }, $lines);
                file_put_contents($htaccessFile, implode("", $newLines));
                $message = "IP(s) débannie(s).";
            } else {
                $message = ".htaccess non trouvé.";
            }
        }
        $activeTab = 'unban';

    } elseif ($action === 'checkAndUnban') {
        if (isset($_POST['ips']) && is_array($_POST['ips'])) {
            // FIX: Filtrer uniquement les IPs valides avant envoi à l'API
            $ipsToCheck = array_filter($_POST['ips'], fn($ip) => isValidIp($ip));
            $pipouFile = __DIR__ . '/pipou.ini';
            if (!file_exists($pipouFile)) {
                $message = "Fichier pipou.ini introuvable.";
                $activeTab = 'unban';
                goto end_switch;
            }
            $pipou = parse_ini_file($pipouFile);
            $api_key = $pipou['api_key'] ?? '';
            if (empty($api_key)) {
                $message = "Clé API non configurée.";
                $activeTab = 'unban';
                goto end_switch;
            }
            $abuseipdb_endpoint = 'https://api.abuseipdb.com/api/v2/check';
            $headers = ['Key: ' . $api_key, 'Accept: application/json'];
            $ipsToUnban = [];

            foreach ($ipsToCheck as $ipToCheck) {
                $curlObj = curl_init();
                curl_setopt($curlObj, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curlObj, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($curlObj, CURLOPT_URL, $abuseipdb_endpoint . '?ipAddress=' . urlencode($ipToCheck));
                curl_setopt($curlObj, CURLOPT_HTTPGET, 1);
                curl_setopt($curlObj, CURLOPT_TIMEOUT, 10);
                $curl_response = curl_exec($curlObj);
                if ($curl_response !== false) {
                    $response_data = json_decode($curl_response, true);
                    if (isset($response_data['data']['abuseConfidenceScore']) && $response_data['data']['abuseConfidenceScore'] == 0) {
                        $ipsToUnban[] = $ipToCheck;
                    }
                }
                curl_close($curlObj);
            }

            if (!empty($ipsToUnban)) {
                $htaccessFile = __DIR__ . '/.htaccess';
                if (file_exists($htaccessFile)) {
                    $lines = file($htaccessFile);
                    $newLines = array_map(function($line) use ($ipsToUnban) {
                        $trimLine = trim($line);
                        if (preg_match('/^(deny from)\s+(\S+)/i', $trimLine, $matches)) {
                            $ip = $matches[2];
                            if (in_array($ip, $ipsToUnban) && strpos($trimLine, '#') !== 0) {
                                return '#' . $line;
                            }
                        }
                        return $line;
                    }, $lines);
                    file_put_contents($htaccessFile, implode("", $newLines));
                    $message = "IP(s) avec score 0 débannie(s).";
                } else {
                    $message = ".htaccess non trouvé.";
                }
            } else {
                $message = "Aucune IP à débannir.";
            }
        }
        $activeTab = 'unban';
    }
}
end_switch:
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Interface de gestion de sécurité</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
        }
        .sidebar {
            width: 200px;
            background-color: #f2f2f2;
            height: 100vh;
            padding-top: 20px;
            box-sizing: border-box;
        }
        .sidebar a {
            display: block;
            padding: 10px 15px;
            text-decoration: none;
            color: #333;
            margin-bottom: 5px;
        }
        .sidebar a.active {
            background-color: #333;
            color: #fff;
        }
        .content {
            flex-grow: 1;
            padding: 20px;
        }
        iframe {
            width: 100%;
            height: 300px;
            border: 1px solid #ccc;
            margin-bottom: 20px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
            color: #333;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .message {
            padding: 10px;
            background-color: #e0ffe0;
            border: 1px solid #00aa00;
            margin-bottom: 20px;
        }
        .add-button {
            background-color: #4CAF50;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .add-button:hover { background-color: #45a049; }
        .delete-button {
            background-color: #f44336;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .delete-button:hover { background-color: #da190b; }
        .deactivate-button {
            background-color: #ff9800;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .deactivate-button:hover { background-color: #e68a00; }
        .reactivate-button {
            background-color: #2196F3;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .reactivate-button:hover { background-color: #1976D2; }
        .unban-button {
            background-color: #2196F3;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .unban-button:hover { background-color: #1976D2; }
        .ban-button {
            background-color: #f44336;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .ban-button:hover { background-color: #da190b; }
        .check-button {
            background: linear-gradient(45deg, #4CAF50, #FFEB3B, #FF9800, #F44336, #2196F3, #9C27B0);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            position: relative;
            overflow: hidden;
        }
        .check-button:hover {
            background: linear-gradient(45deg, #388E3C, #FBC02D, #FF5722, #E91E63, #03A9F4, #673AB7);
        }
    </style>
    <link rel="apple-touch-icon" sizes="57x57" href="/apple-icon-57x57.png">
    <link rel="apple-touch-icon" sizes="60x60" href="/apple-icon-60x60.png">
    <link rel="apple-touch-icon" sizes="72x72" href="/apple-icon-72x72.png">
    <link rel="apple-touch-icon" sizes="76x76" href="/apple-icon-76x76.png">
    <link rel="apple-touch-icon" sizes="114x114" href="/apple-icon-114x114.png">
    <link rel="apple-touch-icon" sizes="120x120" href="/apple-icon-120x120.png">
    <link rel="apple-touch-icon" sizes="144x144" href="/apple-icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/apple-icon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-icon-180x180.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/android-icon-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/manifest.json">
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="msapplication-TileImage" content="/ms-icon-144x144.png">
    <meta name="theme-color" content="#ffffff">
</head>
<body>
    <div class="sidebar">
        <a href="?tab=logs" class="<?php echo ($activeTab == 'logs') ? 'active' : ''; ?>">📄 Logs</a>
        <a href="?tab=addblackpath" class="<?php echo ($activeTab == 'addblackpath') ? 'active' : ''; ?>">➕ Ajouter Chemin Blacklist</a>
        <a href="?tab=desactivatepath" class="<?php echo ($activeTab == 'desactivatepath') ? 'active' : ''; ?>">🔄 Désactiver/Réactiver Chemin</a>
        <a href="?tab=unban" class="<?php echo ($activeTab == 'unban') ? 'active' : ''; ?>">🔓 Unban IP</a>
    </div>
    <div class="content">
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($activeTab == 'logs'): ?>
            <h2>Logs</h2>
            <h3>perdus_logs.html</h3>
            <iframe src="perdus_logs.html"></iframe>
            <h3>stats.html</h3>
            <iframe src="stats.html"></iframe>

        <?php elseif ($activeTab == 'addblackpath'): ?>
            <h2>Ajouter un chemin à la blacklist</h2>
            <p>Liste des chemins issus du fichier 404_visitor_log.txt :</p>
            <?php
                $logFile = __DIR__ . '/404_visitor_log.txt';
                $blacklistFile = __DIR__ . '/blacklist.txt';
                $logPaths = file_exists($logFile) ? file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
                $logPaths = array_unique($logPaths);
                $currentBlacklist = file_exists($blacklistFile) ? file($blacklistFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
                if (count($logPaths) > 0):
            ?>
                <table>
                    <tr>
                        <th>Chemin</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($logPaths as $path): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($path); ?></td>
                        <td>
                            <?php if (!in_array($path, $currentBlacklist)): ?>
                                <form method="POST" style="display:inline; margin-right:10px;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                    <input type="hidden" name="action" value="addblackpath">
                                    <input type="hidden" name="path" value="<?php echo htmlspecialchars($path); ?>">
                                    <input type="submit" value="Ajouter" class="add-button">
                                </form>
                            <?php else: ?>
                                <span style="color:gray;">Déjà en blacklist</span>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="deleteLogPath">
                                <input type="hidden" name="path" value="<?php echo htmlspecialchars($path); ?>">
                                <input type="submit" value="Effacer" class="delete-button">
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>Aucun chemin trouvé dans le log.</p>
            <?php endif; ?>

        <?php elseif ($activeTab == 'desactivatepath'): ?>
            <h2>Désactiver ou Réactiver un chemin</h2>
            <h3>Chemins actifs (blacklist.txt)</h3>
            <?php
            $blacklistFile = __DIR__ . '/blacklist.txt';
            $blacklist = file_exists($blacklistFile) ? file($blacklistFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
            if (count($blacklist) > 0):
            ?>
                <div style="max-height: 70vh; overflow-y: auto;">
                    <table>
                        <tr>
                            <th style="width: 70%;">Chemin</th>
                            <th style="width: 30%;">Action</th>
                        </tr>
                        <?php foreach ($blacklist as $path): ?>
                        <tr>
                            <td style="max-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($path); ?>">
                                <?php echo htmlspecialchars($path); ?>
                            </td>
                            <td>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                    <input type="hidden" name="action" value="desactivate">
                                    <input type="hidden" name="path" value="<?php echo htmlspecialchars($path); ?>">
                                    <input type="submit" value="Désactiver" class="deactivate-button">
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php else: ?>
                <p>Aucun chemin actif dans la blacklist.</p>
            <?php endif; ?>

            <h3>Chemins désactivés (desactivated.txt)</h3>
            <?php
                $desactivatedFile = __DIR__ . '/desactivated.txt';
                $desactivated = file_exists($desactivatedFile) ? file($desactivatedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
                if (count($desactivated) > 0):
            ?>
                <table>
                    <tr>
                        <th>Chemin</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($desactivated as $path): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($path); ?></td>
                        <td>
                            <form method="POST" style="display:inline; margin-right:10px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="reactivate">
                                <input type="hidden" name="path" value="<?php echo htmlspecialchars($path); ?>">
                                <input type="submit" value="Réactiver" class="reactivate-button">
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="deleteDesactivated">
                                <input type="hidden" name="path" value="<?php echo htmlspecialchars($path); ?>">
                                <input type="submit" value="Effacer" class="delete-button">
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>Aucun chemin désactivé.</p>
            <?php endif; ?>

        <?php elseif ($activeTab == 'unban'): ?>
            <h2>Débannir des IP</h2>

            <?php
            $htaccessFile = __DIR__ . '/.htaccess';
            $activeBans = [];
            $commentedBans = [];

            if (file_exists($htaccessFile)) {
                $lines = file($htaccessFile);
                foreach ($lines as $line) {
                    $trimLine = trim($line);
                    if (preg_match('/^deny from\s+(\S+)/i', $trimLine, $matches)) {
                        $activeBans[] = $matches[1];
                    } elseif (preg_match('/^#deny from\s+(\S+)/i', $trimLine, $matches)) {
                        $commentedBans[] = $matches[1];
                    }
                }
            }
            ?>

            <h3>IPs bannies</h3>
            <?php if (count($activeBans) > 0): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="action" value="unban">
                    <table>
                        <tr>
                            <th>IP</th>
                            <th>Sélection</th>
                            <th>Action</th>
                        </tr>
                        <?php foreach ($activeBans as $ip): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ip); ?></td>
                            <td><input type="checkbox" name="ips[]" value="<?php echo htmlspecialchars($ip); ?>"></td>
                            <td>
                                <input type="submit" value="Débannir" class="unban-button">
                                <button type="submit" name="action" value="checkAndUnban" class="check-button">Vérifier</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </form>
            <?php else: ?>
                <p>Aucune IP bannie trouvée.</p>
            <?php endif; ?>

            <h3>IPs commentées (à rebannir)</h3>
            <?php if (count($commentedBans) > 0): ?>
                <table>
                    <tr>
                        <th>IP</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($commentedBans as $ip): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ip); ?></td>
                        <td>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="rebannirIP">
                                <input type="hidden" name="ip" value="<?php echo htmlspecialchars($ip); ?>">
                                <input type="submit" value="Rebannir" class="ban-button">
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>Aucune IP commentée trouvée.</p>
            <?php endif; ?>

            <h3>Ajouter une IP</h3>
            <form method="POST" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="banIP">
                <input type="text" name="ip" placeholder="Entrez l'IP à bannir" style="padding:8px; margin-right:10px;">
                <input type="submit" value="Bannir IP" class="ban-button">
            </form>

        <?php endif; ?>

    </div>
</body>
</html>
