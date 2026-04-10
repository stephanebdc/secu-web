<?php

function getUserIP() {
    return $_SERVER['REMOTE_ADDR'];
}

function getReferrer() {
    return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'Direct';
}

function getRequestedPage() {
    return $_SERVER['REQUEST_URI'];
}

function logVisitorInfo($ip, $referrer, $requestedPage) {
    $date = date('Y-m-d H:i:s');

    // FIX: Échapper toutes les données utilisateur avant écriture en HTML (anti-XSS)
    $safeIp       = htmlspecialchars($ip,           ENT_QUOTES, 'UTF-8');
    $safeReferrer = htmlspecialchars($referrer,      ENT_QUOTES, 'UTF-8');
    $safePage     = htmlspecialchars($requestedPage, ENT_QUOTES, 'UTF-8');
    $logEntry     = "<tr><td>$date</td><td>$safeIp</td><td>$safeReferrer</td><td>$safePage</td></tr>\n";

    // Log texte brut
    $logFile = __DIR__ . '/404_visitor_log.txt';
    $fpLog = fopen($logFile, 'a');
    if ($fpLog) {
        flock($fpLog, LOCK_EX);
        fwrite($fpLog, $requestedPage . "\n");
        fflush($fpLog);
        flock($fpLog, LOCK_UN);
        fclose($fpLog);
    }

    // Log HTML DataTables
    $filePath = __DIR__ . '/perdus_logs.html';
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
        \$(document).ready(function() {
            \$('#visitorTable').DataTable({
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

    $fp = fopen($filePath, 'c+');
    if ($fp === false) {
        return;
    }
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return;
    }

    $currentContent = stream_get_contents($fp);
    if (trim($currentContent) === '') {
        $currentContent = $initialContent;
    }

    if (strpos($currentContent, '</tbody>') !== false) {
        $currentContent = str_replace('</tbody>', $logEntry . '</tbody>', $currentContent);
    } else {
        $currentContent .= "<tbody>" . $logEntry . "</tbody></table></body></html>";
    }

    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, $currentContent);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

function isBlacklisted($requestedPage) {
    $file = __DIR__ . '/blacklist.txt';
    if (!file_exists($file)) return false;
    $blacklist = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return in_array($requestedPage, $blacklist);
}

// FIX: Validation stricte de l'IP avant écriture dans .htaccess (anti-injection)
function addDenyRule($ip) {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return;
    }
    $htaccessFile = __DIR__ . '/.htaccess';
    $fp = fopen($htaccessFile, 'a');
    if ($fp) {
        flock($fp, LOCK_EX);
        fwrite($fp, "deny from $ip\n");
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

$ip            = getUserIP();
$referrer      = getReferrer();
$requestedPage = getRequestedPage();

logVisitorInfo($ip, $referrer, $requestedPage);

if (isBlacklisted($requestedPage)) {
    addDenyRule($ip);
    header("Location: /403_.html");
    exit();
} else {
    $requestedPageModified = str_replace('.html', '.php', $requestedPage);
    if (isBlacklisted($requestedPageModified)) {
        addDenyRule($ip);
        header("Location: /403_.html");
        exit();
    } else {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        header("Location: $protocol://$host/yon-maru-yon.html");
        exit();
    }
}
