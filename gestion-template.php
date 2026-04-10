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

$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
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

// --- Données du dashboard ---
function getDashboardData() {
    $data = [
        'bans_actifs'    => 0,
        'bans_commentes' => 0,
        'blacklist'      => 0,
        'desactivated'   => 0,
        'log404_total'   => 0,
        'log404_recent'  => [],
        'bans_list'      => [],
    ];

    // IPs depuis .htaccess
    $htFile = __DIR__ . '/.htaccess';
    if (file_exists($htFile)) {
        foreach (file($htFile) as $line) {
            $t = trim($line);
            if (preg_match('/^deny from\s+(\S+)/i', $t, $m))  { $data['bans_actifs']++;    $data['bans_list'][] = $m[1]; }
            if (preg_match('/^#deny from\s+(\S+)/i', $t))      { $data['bans_commentes']++; }
        }
    }

    // Blacklist
    $bl = __DIR__ . '/blacklist.txt';
    if (file_exists($bl)) {
        $lines = file($bl, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $data['blacklist'] = count($lines);
    }

    // Désactivés
    $dv = __DIR__ . '/desactivated.txt';
    if (file_exists($dv)) {
        $data['desactivated'] = count(file($dv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    }

    // Log 404
    $lf = __DIR__ . '/404_visitor_log.txt';
    if (file_exists($lf)) {
        $lines = file($lf, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $data['log404_total']  = count($lines);
        $data['log404_recent'] = array_slice(array_reverse($lines), 0, 10);
    }

    // Garder seulement les 8 dernières IPs bannies
    $data['bans_list'] = array_slice(array_reverse($data['bans_list']), 0, 8);

    return $data;
}

// Extrait les lignes <tr> d'un fichier HTML de log et les retourne sous forme de tableau
function parseHtmlLog($file, $maxRows = 300) {
    if (!file_exists($file)) return [];
    $content = file_get_contents($file);
    // Extraire uniquement le <tbody> pour éviter les lignes d'en-tête
    $body = '';
    if (preg_match('/<tbody>(.*?)<\/tbody>/is', $content, $m)) {
        $body = $m[1];
    } else {
        $body = $content;
    }
    preg_match_all('/<tr>(.*?)<\/tr>/is', $body, $matches);
    $rows = [];
    foreach ($matches[1] as $row) {
        preg_match_all('/<td>(.*?)<\/td>/is', $row, $cells);
        if (!empty($cells[1])) {
            $rows[] = array_map(function($cell) {
                return html_entity_decode(strip_tags($cell), ENT_QUOTES, 'UTF-8');
            }, $cells[1]);
        }
    }
    // Les plus récents en premier (dernières lignes du fichier)
    return array_slice(array_reverse($rows), 0, $maxRows);
}
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
        /* Dashboard */
        .dash-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }
        .dash-card {
            background: white;
            border-radius: 10px;
            padding: 20px 16px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            text-align: center;
            border-top: 4px solid #ccc;
        }
        .dash-card.red   { border-top-color: #ef4444; }
        .dash-card.blue  { border-top-color: #3b82f6; }
        .dash-card.amber { border-top-color: #f59e0b; }
        .dash-card.green { border-top-color: #10b981; }
        .dash-card.gray  { border-top-color: #6b7280; }
        .dash-icon { font-size: 1.6rem; margin-bottom: 6px; }
        .dash-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1a1a2e;
            line-height: 1;
            margin-bottom: 4px;
        }
        .dash-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #9ca3af;
            font-weight: 600;
        }
        .dash-section { margin-bottom: 24px; }
        .dash-section h3 {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #9ca3af;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .dash-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        .dash-table td { padding: 7px 10px; border-bottom: 1px solid #f3f4f6; font-family: monospace; color: #374151; }
        .dash-table tr:last-child td { border: none; }
        .dash-table tr:hover td { background: #f9fafb; }
        .dash-empty { color: #9ca3af; font-size: 0.85rem; padding: 10px 0; }
        .dash-badge {
            display: inline-block;
            background: #fee2e2;
            color: #b91c1c;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 700;
            margin-left: 6px;
        }

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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <script>
    $(document).ready(function() {
        if ($('#logTable404').length)  $('#logTable404').DataTable({ order: [[0,'desc']], pageLength: 25, language: { search: 'Filtrer :', lengthMenu: 'Afficher _MENU_ entrées', info: '_START_–_END_ sur _TOTAL_', paginate: { previous: '←', next: '→' } } });
        if ($('#logTableStats').length) $('#logTableStats').DataTable({ order: [[1,'desc']], pageLength: 25, language: { search: 'Filtrer :', lengthMenu: 'Afficher _MENU_ entrées', info: '_START_–_END_ sur _TOTAL_', paginate: { previous: '←', next: '→' } } });
    });
    </script>
</head>
<body>
    <div class="sidebar">
        <a href="?tab=dashboard" class="<?php echo ($activeTab == 'dashboard') ? 'active' : ''; ?>">📊 Tableau de bord</a>
        <a href="?tab=logs" class="<?php echo ($activeTab == 'logs') ? 'active' : ''; ?>">📄 Logs</a>
        <a href="?tab=addblackpath" class="<?php echo ($activeTab == 'addblackpath') ? 'active' : ''; ?>">➕ Ajouter Chemin Blacklist</a>
        <a href="?tab=desactivatepath" class="<?php echo ($activeTab == 'desactivatepath') ? 'active' : ''; ?>">🔄 Désactiver/Réactiver Chemin</a>
        <a href="?tab=unban" class="<?php echo ($activeTab == 'unban') ? 'active' : ''; ?>">🔓 Unban IP</a>
    </div>
    <div class="content">
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($activeTab == 'dashboard'):
            $d = getDashboardData();
        ?>
            <h2>Tableau de bord</h2>

            <div class="dash-grid">
                <div class="dash-card red">
                    <div class="dash-icon">🚫</div>
                    <div class="dash-value"><?= $d['bans_actifs'] ?></div>
                    <div class="dash-label">IPs bannies</div>
                </div>
                <div class="dash-card gray">
                    <div class="dash-icon">💤</div>
                    <div class="dash-value"><?= $d['bans_commentes'] ?></div>
                    <div class="dash-label">IPs débanniées</div>
                </div>
                <div class="dash-card blue">
                    <div class="dash-icon">📋</div>
                    <div class="dash-value"><?= number_format($d['blacklist']) ?></div>
                    <div class="dash-label">Chemins blacklist</div>
                </div>
                <div class="dash-card amber">
                    <div class="dash-icon">⏸</div>
                    <div class="dash-value"><?= $d['desactivated'] ?></div>
                    <div class="dash-label">Désactivés</div>
                </div>
                <div class="dash-card green">
                    <div class="dash-icon">👁</div>
                    <div class="dash-value"><?= number_format($d['log404_total']) ?></div>
                    <div class="dash-label">404 loggés</div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

                <div class="dash-section">
                    <h3>Derniers chemins 404 détectés</h3>
                    <?php if (!empty($d['log404_recent'])): ?>
                    <table class="dash-table">
                        <?php foreach ($d['log404_recent'] as $path): ?>
                        <tr>
                            <td title="<?= htmlspecialchars($path) ?>">
                                <?= htmlspecialchars(strlen($path) > 48 ? substr($path, 0, 48) . '…' : $path) ?>
                            </td>
                            <td style="width:90px;text-align:right;">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="action" value="addblackpath">
                                    <input type="hidden" name="path" value="<?= htmlspecialchars($path) ?>">
                                    <button type="submit" style="background:#ef4444;color:white;border:none;padding:2px 7px;border-radius:3px;font-size:0.72rem;cursor:pointer;">+ BL</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <p style="margin-top:8px;"><a href="?tab=addblackpath" style="font-size:0.8rem;color:#6b7280;">Voir tous les chemins →</a></p>
                    <?php else: ?>
                    <p class="dash-empty">Aucun 404 enregistré.</p>
                    <?php endif; ?>
                </div>

                <div class="dash-section">
                    <h3>IPs bannies récentes</h3>
                    <?php if (!empty($d['bans_list'])): ?>
                    <table class="dash-table">
                        <?php foreach ($d['bans_list'] as $ip): ?>
                        <tr>
                            <td><?= htmlspecialchars($ip) ?><span class="dash-badge">BAN</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <p style="margin-top:8px;"><a href="?tab=unban" style="font-size:0.8rem;color:#6b7280;">Gérer les IPs →</a></p>
                    <?php else: ?>
                    <p class="dash-empty">Aucune IP bannie.</p>
                    <?php endif; ?>
                </div>

            </div>

        <?php elseif ($activeTab == 'logs'):
            $rows404  = parseHtmlLog(__DIR__ . '/perdus_logs.html');
            $rowsStats = parseHtmlLog(__DIR__ . '/stats.html');
        ?>
            <h2>Logs 404 <small style="font-size:.75rem;color:#9ca3af;font-weight:400;">(<?= count($rows404) ?> entrées affichées)</small></h2>
            <?php if (!empty($rows404)): ?>
            <div style="overflow-x:auto;margin-bottom:2rem;">
                <table id="logTable404" class="display" style="width:100%;font-size:.85rem;">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>IP</th>
                            <th>Referrer</th>
                            <th>Page demandée</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows404 as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                            <td><?= htmlspecialchars($cell) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p style="color:#9ca3af;margin-bottom:2rem;">Aucune entrée dans perdus_logs.html.</p>
            <?php endif; ?>

            <h2>Stats visites <small style="font-size:.75rem;color:#9ca3af;font-weight:400;">(<?= count($rowsStats) ?> entrées affichées)</small></h2>
            <?php if (!empty($rowsStats)): ?>
            <div style="overflow-x:auto;">
                <table id="logTableStats" class="display" style="width:100%;font-size:.85rem;">
                    <thead>
                        <tr>
                            <th>IP</th>
                            <th>Date et heure</th>
                            <th>Referer / Page</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rowsStats as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                            <td><?= htmlspecialchars($cell) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p style="color:#9ca3af;">Aucune entrée dans stats.html.</p>
            <?php endif; ?>

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
