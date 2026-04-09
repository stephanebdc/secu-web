<?php

$statsFile = __DIR__ . '/stats.html';
$ip        = $_SERVER['REMOTE_ADDR'];
$dateTime  = date('Y-m-d H:i:s');
$referer   = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'Direct';
$papage    = $_SERVER['REQUEST_URI'];

// FIX: Échapper toutes les données contrôlées par l'utilisateur avant écriture en HTML (anti-XSS)
$safeIp     = htmlspecialchars($ip,      ENT_QUOTES, 'UTF-8');
$safeReferer = htmlspecialchars($referer, ENT_QUOTES, 'UTF-8');
$safePage   = htmlspecialchars($papage,  ENT_QUOTES, 'UTF-8');

$newEntry = "<tr><td>$safeIp</td><td>$dateTime</td><td>$safeReferer depuis $safePage</td></tr>";

$initialContent = "<html><head>
<script src='https://code.jquery.com/jquery-3.6.0.min.js'></script>
<script src='https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js'></script>
<link rel='stylesheet' href='https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css'>
</head><body>
<table id='statsTable' class='display'>
  <thead>
    <tr><th>IP</th><th>Date et Heure</th><th>Referer</th></tr>
  </thead>
  <tbody></tbody>
</table>
<script>
\$(document).ready(function() { \$('#statsTable').DataTable(); });
</script>
</body><!-- nouveau --></html>";

$fp = fopen($statsFile, 'c+');
if ($fp === false) {
    return; // FIX: ne pas die() — ne pas révéler le chemin serveur
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
    $currentContent = str_replace('</tbody>', $newEntry . '</tbody>', $currentContent);
} else {
    $currentContent = str_replace('<tbody>', '<tbody>' . $newEntry, $currentContent);
}

rewind($fp);
ftruncate($fp, 0);
fwrite($fp, $currentContent);
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);
